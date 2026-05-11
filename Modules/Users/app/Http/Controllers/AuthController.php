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



    public function checkMobile(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:11',
        ]);

        $user = User::where('mobile', $request->mobile)->first();

        if ($user) {
            return response()->json(['status' => 'login']);
        }

        $this->sendOtp($request->mobile);
        $otp = Otp::where('mobile', $request->mobile)->first();
        return response()->json([
            'token' => $otp->token,
            'status' => 'register'
        ]);
    }

    // 2) ورود با پسورد
    public function loginWithPassword(Request $request)
    {
        $data = $request->validate([
            'mobile' => 'required|digits:11',
            'password' => 'required|min:6',
        ]);

        $user = User::where('mobile', $data['mobile'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    // 3) ارسال OTP (هم برای لاگین هم برای ثبت‌نام)

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

    public  function sendOtpAgain(Request $request)
    {
        $request->validate(['mobile' => 'required|digits:11']);
        $this->sendOtp($request->mobile);
        $otp = Otp::where('mobile', $request->mobile)->first();
        return response()->json([
            'message' => 'OTP sent',
            'success' => true,
            'token' => $otp->token
        ]);
    }
    // 4) بررسی OTP
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
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        $user = User::where('mobile', $mobile)->first();
        if ($user) {
            $token = $user->createToken('auth_token')->plainTextToken;
            $otp->delete();
            return response()->json([
                'user' => $user,
                'token' => $token,
                'status' => 'login'
            ]);
        }

        return response()->json(['status' => 'need_register']);
    }
    // 5) ثبت‌نام بعد از تایید OTP

    public function register(Request $request, NotificationService $notifications)
    {
        $data = $request->validate([
            'mobile'   => 'required|digits:11|unique:users,mobile',
            'password' => 'required|min:6',
            'full_name' => 'required|string|min:3',
        ]);

        // بررسی OTP معتبر
        $mobile = trim($data['mobile']);
        $otp = Otp::where('mobile', $mobile)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'OTP not verified or expired'], 422);
        }

        $user = User::create([
            'mobile'    => $mobile,
            'password'  => Hash::make($data['password']),
            'full_name' => $data['full_name'],
        ]);

        $customerRoleId = Role::where('slug', 'customer')->value('id');
        $user->roles()->sync([$customerRoleId]);

        Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
        ]);
        $notifications->create(
            " ثبت نام  کاربر",
            " کاربر  {$user->full_name}در سیستم ثبت نام  شد",
            "notifications_user",
            ['users' => $user->id]
        );
        $otp->delete(); // حذف OTP بعد از ثبت‌نام
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
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
