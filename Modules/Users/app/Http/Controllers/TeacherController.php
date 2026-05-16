<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Http\Requests\TeacherStoreRequest;
use Modules\Users\Models\Role;
use Modules\Users\Models\User;

class TeacherController extends Controller
{

    /**
     * نمایش لیست تمام معلمان
     */
    public function index(Request $request)
    {
        $teachers = User::withRole('teacher')
            ->with('roles')
            ->when($request->get('search'), function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->when($request->get('is_active') !== null, function ($query) use ($request) {
                return $query->where('is_active', $request->boolean('is_active'));
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $teachers
        ], 200);
    }

    /**
     * ثبت معلم جدید
     */
    public function store(TeacherStoreRequest  $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // ایجاد کاربر جدید
            $teacherData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'mobile' => $validated['mobile'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ];

            // مدیریت آپلود آواتار
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('teachers/avatars', 'public');
                $teacherData['avatar'] = $avatarPath;
            }

            $teacher = User::create($teacherData);

            // اختصاص نقش teacher
            $teacherRole = Role::where('slug', 'teacher')->first();
            $specialRole = Role::create([
                'name' => "نقش ویژه معلم با آیدی " . $teacher->id,
                'is_system' => true,
                'slug' => "role_teacher_" . $teacher->id
            ]);
            $teacher->roles()->attach($specialRole);
            if ($teacherRole) {
                $teacher->roles()->attach($teacherRole->id);
            }
            if ($specialRole) {
                $teacher->roles()->attach($specialRole->id);
            }
           
            DB::commit();

            // بارگذاری روابط
            $teacher->load('roles');

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "ثبت معلم جدید",
                "معلم {$teacher->full_name} با موفقیت در سیستم ثبت شد",
                "notification_teacher",
                [
                    'teacher_id' => $teacher->id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'معلم با موفقیت ایجاد شد',
                'data' => $teacher
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت معلم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * نمایش اطلاعات یک معلم
     */
    public function show($id)
    {
        $teacher = User::withRole('teacher')
            ->with(['roles', 'students'])
            ->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        // آمار معلم
        $statistics = [
            'total_students' => $teacher->students()->count(),
            'total_classes' => $teacher->classes()->count(),
            'total_tasks' => $teacher->assignedTasks()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $teacher,
            'statistics' => $statistics
        ], 200);
    }

    /**
     * بروزرسانی اطلاعات معلم
     */
    public function update(Request $request, $id, NotificationService $notifications)
    {
        $teacher = User::withRole('teacher')->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:50|min:2',
            'last_name' => 'sometimes|required|string|max:50|min:2',
            'mobile' => [
                'sometimes',
                'required',
                'string',
                'size:11',
                'regex:/^09[0-9]{9}$/',
                Rule::unique('users', 'mobile')->ignore($teacher->id)
            ],
            'password' => 'nullable|string|min:6|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            // آماده‌سازی داده‌ها برای بروزرسانی
            $updateData = [];

            if (isset($validated['first_name'])) $updateData['first_name'] = $validated['first_name'];
            if (isset($validated['last_name'])) $updateData['last_name'] = $validated['last_name'];
            if (isset($validated['mobile'])) $updateData['mobile'] = $validated['mobile'];
            if (isset($validated['is_active'])) $updateData['is_active'] = $validated['is_active'];

            if (isset($validated['password']) && !empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            // مدیریت آپلود آواتار جدید
            if ($request->hasFile('avatar')) {
                // حذف آواتار قبلی
                if ($teacher->avatar && Storage::disk('public')->exists($teacher->avatar)) {
                    Storage::disk('public')->delete($teacher->avatar);
                }
                $updateData['avatar'] = $request->file('avatar')->store('teachers/avatars', 'public');
            }

            // بروزرسانی معلم
            $teacher->update($updateData);

            DB::commit();

            $teacher->load('roles');

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "بروزرسانی اطلاعات معلم",
                "اطلاعات معلم {$teacher->full_name} بروزرسانی شد",
                "notification_teacher",
                [
                    'teacher_id' => $teacher->id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'اطلاعات معلم با موفقیت بروزرسانی شد',
                'data' => $teacher
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در بروزرسانی: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف معلم (غیرفعال کردن یا حذف کامل)
     */
    public function destroy($id, Request $request, NotificationService $notifications)
    {
        $teacher = User::withRole('teacher')->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $permanent = $request->get('permanent', false);
        $teacherName = $teacher->full_name;

        DB::beginTransaction();

        try {
            if ($permanent) {
                // حذف کامل
                if ($teacher->avatar && Storage::disk('public')->exists($teacher->avatar)) {
                    Storage::disk('public')->delete($teacher->avatar);
                }
                $teacher->forceDelete();
                $message = 'معلم برای همیشه حذف شد';
            } else {
                // فقط غیرفعال کردن
                $teacher->update(['is_active' => false]);
                $message = 'معلم با موفقیت غیرفعال شد';
            }

            DB::commit();

            $notifications->create(
                "حذف معلم",
                "معلم {$teacherName} از سیستم حذف شد",
                "notification_teacher",
                [
                    'teacher_id' => $id,
                    'maker' => $request->user()->full_name,
                    'permanent' => $permanent
                ]
            );

            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * فعال کردن معلم
     */
    public function activate($id, NotificationService $notifications)
    {
        $teacher = User::withRole('teacher')->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $teacher->update(['is_active' => true]);

        $notifications->create(
            "فعالسازی معلم",
            "معلم {$teacher->full_name} فعال شد",
            "notification_teacher",
            [
                'teacher_id' => $teacher->id,
                'maker' => request()->user()->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'معلم با موفقیت فعال شد',
            'data' => $teacher
        ], 200);
    }

    /**
     * دریافت دانش‌آموزان تحت نظر یک معلم
     */
    public function getStudents($id)
    {
        $teacher = User::withRole('teacher')->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $students = $teacher->students()
            ->with(['class.grade'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students,
            'teacher' => $teacher->only(['id', 'first_name', 'last_name', 'full_name'])
        ], 200);
    }

    /**
     * دریافت کلاس‌های تحت تدریس معلم
     */
    public function getClasses($id)
    {
        $teacher = User::withRole('teacher')->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $classes = $teacher->classes()
            ->with(['grade'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $classes,
            'teacher' => $teacher->only(['id', 'first_name', 'last_name', 'full_name'])
        ], 200);
    }

    /**
     * دریافت وظایف محول شده به معلم
     */
    public function getTasks($id, Request $request)
    {
        $teacher = User::withRole('teacher')->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $tasks = $teacher->assignedTasks()
            ->with(['task', 'class.grade'])
            ->when($request->get('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'teacher' => $teacher->only(['id', 'first_name', 'last_name', 'full_name'])
        ], 200);
    }

    /**
     * دریافت آمار کلی معلمان
     */
    public function statistics()
    {
        $statistics = [
            'total_teachers' => User::withRole('teacher')->count(),
            'active_teachers' => User::withRole('teacher')->active()->count(),
            'inactive_teachers' => User::withRole('teacher')->where('is_active', false)->count(),
            'recent_teachers' => User::withRole('teacher')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'first_name', 'last_name', 'mobile', 'created_at'])
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics
        ], 200);
    }
}
