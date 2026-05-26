<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Users\Http\Requests\UserStoreRequest;
use Modules\Users\Http\Requests\UserUpdateRequest;
use Modules\Users\Models\Role;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;

class UsersController extends Controller
{
    public function adminInfo(Request $request)
    {
        $user = $request->user();
        $permissions = $user->permissions;
        return response()->json([
            'message' => 'اطلاعات ادمین',
            'user' => $user,
            'permissions' => $permissions
        ]);
    }
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        // اعتبارسنجی ورودی‌ها
        $validated = $request->validate([
            'full_name'     => 'nullable|string|max:255',
            'password'      => 'nullable|string|min:6',
            'national_code' => 'nullable|string|max:10|unique:users,national_code,' . $user->id,
            'birth_date'    => 'nullable|date',
        ]);

        // اگر پسورد فرستاده شده بود، هش کنیم
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']); // اگر نبود، حذفش کنیم تا مقدار قبلی تغییر نکنه
        }

        // بروزرسانی کاربر
        $user->update($validated);

        return response()->json([
            'message' => 'پروفایل با موفقیت بروزرسانی شد.',
            'user'    => $user
        ]);
    }

    // لیست کاربران
    public function userProfile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user,
            'message' => 'اطلاعات کاربر'
        ]);
    }
    public function index(Request $request)
    {
        $query = User::with(['roles',  'wallet']);

        // اگر پارامتر search ارسال شده باشد
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->where('last_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $users = $query->whereHas('roles', function ($query) {
            $query->whereNotIn('slug', ['superAdmin']);
        })->paginate(20);

        return response()->json($users);
    }
    public function getSupporter(Request $request)
    {
        $query = User::with(['roles',  'wallet']);
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $users = $query->whereHas('roles', function ($query) {
            $query->whereNotIn('slug', ['employer', 'superAdmin']);
        })->paginate(20);

        return response()->json($users);
    }

    // لیست مدیران
    public function managerIndex(Request $request)
    {
        $query = User::with(['roles', 'wallet']);
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }
        $users = $query->whereHas('roles', function ($query) {
            $query->whereNotIn('slug', ['user', 'superAdmin']);
        })->get();
        return response()->json($users);
    }
    // ساخت کاربر جدید
    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();
        $userRoleId = Role::where('slug', 'user')->value('id');
        if (!$userRoleId) {
            $newRole =  Role::create([
                'name' => 'کاربر',
                'is_system' => true,
                'slug' => "user"
            ]);
            $userRoleId = $newRole->id;
        }
        if ($request->hasFile('avatar')) {
            $userAvatar = $request->file('avatar')->store('users/avatars', 'public');
            $data['avatar'] = $userAvatar;
        }

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $user->roles()->sync([$userRoleId]);
        Wallet::create([
            'user_id' => $user->id,
            'balance' =>  0,
        ]);
        return response()->json($user->load(['roles', 'wallet']), 201);
    }

    // نمایش یک کاربر
    public function show(User $user)
    {
        return response()->json($user->load(['roles', 'wallet']));
    }

    // ویرایش کاربر
    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();
        if (isset($data['mobile'])) {
            unset($data['mobile']);
        }
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if ($request->hasFile('avatar')) {
            // حذف آواتار قبلی
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }
        $user->update($data);
        return response()->json($user->load(['roles',  'wallet']));
    }

    // حذف کاربر
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}
