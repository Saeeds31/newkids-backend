<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Models\Otp;
use Modules\Users\Models\Role;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;

class AuthController extends Controller
{
    public function CheckLogin(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string|size:11'
        ]);

        $user = User::with(['roles'])->where('mobile', $validated['mobile'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'شماره پیدا نشد'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'اطلاعات ورود',
            'role' => $user->roles->select(['slug', 'name']),
        ], 200);
    }


    public function loginWithPassword(Request $request)
    {
        $data = $request->validate([
            'mobile' => 'required|digits:11',
            'password' => 'required|min:6',
        ]);

        $user = User::with(['roles'])->where('mobile', $data['mobile'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'شماره پیدا نشد'
            ], 404);
        }
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'رمز عبور اشتباه است',
                'success' => false
            ], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        $permissions = $user->permissions;
        return response()->json([
            'user' => $user,
            'success' => true,
            'permissions' => $permissions,
            'role' => $user->roles->select(['slug', 'name']),
            'token' => $token,
        ]);
    }
    public function publicSendToken(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string|size:11'
        ]);
        $user = User::where('mobile', $validated['mobile'])->first();
        if ($user) {
            $this->sendOtp($request->mobile);
            return response()->json([
                'success' => true,
                'message' => 'کد یکبار مصرف ارسال شد.'
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'شما مجاز به انجام این عملیات نیستید.'
        ], 403);
    }


    public function sendOtp($mobile)
    {
        $mobile = trim($mobile);
        $token = rand(100000, 999999);

        Otp::updateOrCreate(
            ['mobile' => $mobile],
            ['token' => $token, 'expires_at' => now()->addMinutes(5)]
        );
        $smsService = new SmsService();
        $smsService->sendToKavenegar('verify', $mobile, $token);


        return true;
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'mobile' => 'required|digits:11',
            'token'  => 'required|digits:6',
        ]);

        $mobile = trim($data['mobile']);
        $otp = Otp::where('mobile', $mobile)
            ->where('token', $data['token'])
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'کد یکبار مصرف منقضی شده است',
                'success' => false
            ], 422);
        }

        $user = User::where('mobile', $mobile)->first();
        if ($user) {
            $token = $user->createToken('auth_token')->plainTextToken;
            $otp->delete();
            $permissions = $user->permissions;
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'permissions' => $permissions,
            ]);
        }

        return response()->json(['status' => 'need_register']);
    }
 

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'با موفقیت خارج شد']);
    }
    public function adminSendToken(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string|size:11'
        ]);
        $user = User::where('mobile', $validated['mobile'])->first();
        if ($user) {
            if ($user->roles()->where('slug', 'customer')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما مجاز به انجام این عملیات نیستید.'
                ], 403);
            } else {
                $this->sendOtp($request->mobile);
                return response()->json([
                    'success' => true,
                    'message' => 'کد یکبار مصرف ارسال شد.'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'شما مجاز به انجام این عملیات نیستید.'
        ], 403);
    }

    public function employerLogin(Request $request)
    {

        $data = $request->validate([
            'mobile' => 'required|digits:11',
            'token'  => 'required|digits:6',
        ]);
        $mobile = trim($data['mobile']);
        $otp = Otp::where('mobile', $mobile)
            ->where('token', $data['token'])
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json(
                [
                    'message' => 'کد اعتبار خود را از دست داده است مجدد تلاش کنید',
                    'success' => false
                ],
                422
            );
        }

        $user = User::where('mobile', $mobile)->first();
        if ($user->roles()->where('slug', 'employer')->doesntExist()) {
            return response()->json([
                'success' => false,
                'message' => 'شما مجاز به انجام این عملیات نیستید.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'token' => $token,
            "success" => true,
            'message' => 'خوش آمدید'
        ]);
    }
    public function adminLogin(Request $request)
    {

        $data = $request->validate([
            'mobile' => 'required|digits:11',
            'token'  => 'required|digits:6',
        ]);
        $mobile = trim($data['mobile']);
        $otp = Otp::where('mobile', $mobile)
            ->where('token', $data['token'])
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json(
                [
                    'message' => 'کد اعتبار خود را از دست داده است مجدد تلاش کنید',
                    'success' => false
                ],
                422
            );
        }

        $user = User::where('mobile', $mobile)->first();
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'token' => $token,
            "success" => true,
            'message' => 'خوش آمدید'
        ]);
    }
    public function logoutUserFront(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'با موفقیت خارج شدید']);
    }
}
