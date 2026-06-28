<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Http\Requests\TeacherStoreRequest;
use Modules\Users\Http\Requests\TeacherUpdateRequest;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;
use Modules\Users\Models\Teacher;
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
    public function store(TeacherStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // ۱. ایجاد کاربر جدید
            $userData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'mobile' => $validated['mobile'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ];

            // مدیریت آپلود آواتار
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('teachers/avatars', 'public');
                $userData['avatar'] = $avatarPath;
            }

            $user = User::create($userData);

            // ۲. ایجاد رکورد teacher مرتبط با کاربر
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'national_code' => $validated['national_code'],
                'education' => $validated['education'],
                'education_field' => $validated['education_field'],
                'job_history' => $validated['job_history'] ?? '',
            ]);

            // ۳. اتصال تخصص‌ها به معلم (اگر وجود داشته باشند)
            if (!empty($validated['expertise_ids'])) {
                $teacher->expertises()->attach($validated['expertise_ids']);
            }

            // ۴. ایجاد نقش‌ها و دسترسی‌ها
            $teacherRole = Role::where('slug', 'teacher')->first();
            $specialRole = Role::create([
                'name' => "نقش ویژه معلم با آیدی " . $user->id,
                'is_system' => true,
                'slug' => "role_teacher_" . $user->id
            ]);

            if ($teacherRole) {
                $user->roles()->attach($teacherRole->id);
            }
            if ($specialRole) {
                $user->roles()->attach($specialRole->id);
            }

            $specialPer = Permission::create([
                'name' => "notification_user_" . $user->id,
                'label' => "ناتفیکیشن های مربی " . $user->full_name,
            ]);
            $specialRole->permissions()->attach($specialPer);

            DB::commit();

            // بارگذاری روابط
            $user->load('roles');
            $teacher->load('expertises');

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "ثبت معلم جدید",
                "معلم {$user->full_name} با موفقیت در سیستم ثبت شد",
                "notification_teacher",
                [
                    'teacher_id' => $teacher->id,
                    'user_id' => $user->id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'معلم با موفقیت ایجاد شد',
                'data' => [
                    'user' => $user,
                    'teacher' => $teacher,
                    'expertises' => $teacher->expertises
                ]
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
    public function update(TeacherUpdateRequest $request, $id, NotificationService $notifications)
    {
        // پیدا کردن کاربر با نقش teacher
        $user = User::withRole('teacher')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // ۱. بروزرسانی اطلاعات کاربر
            $userUpdateData = [];

            if (isset($validated['first_name'])) $userUpdateData['first_name'] = $validated['first_name'];
            if (isset($validated['last_name'])) $userUpdateData['last_name'] = $validated['last_name'];
            if (isset($validated['mobile'])) $userUpdateData['mobile'] = $validated['mobile'];
            if (isset($validated['is_active'])) $userUpdateData['is_active'] = $validated['is_active'];

            if (isset($validated['password']) && !empty($validated['password'])) {
                $userUpdateData['password'] = Hash::make($validated['password']);
            }

            // مدیریت آپلود آواتار جدید
            if ($request->hasFile('avatar')) {
                // حذف آواتار قبلی
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $userUpdateData['avatar'] = $request->file('avatar')->store('teachers/avatars', 'public');
            }

            // بروزرسانی کاربر
            $user->update($userUpdateData);

            // ۲. بروزرسانی اطلاعات مدل Teacher
            $teacher = $user->teacher;

            if (!$teacher) {
                // اگر معلم وجود نداشت، ایجاد کن (حالت خاص)
                $teacher = Teacher::create([
                    'user_id' => $user->id,
                    'national_code' => $validated['national_code'] ?? '',
                    'education' => $validated['education'] ?? '',
                    'education_field' => $validated['education_field'] ?? '',
                    'job_history' => $validated['job_history'] ?? '',
                ]);
            } else {
                // بروزرسانی اطلاعات معلم
                $teacherUpdateData = [];

                if (isset($validated['national_code'])) $teacherUpdateData['national_code'] = $validated['national_code'];
                if (isset($validated['education'])) $teacherUpdateData['education'] = $validated['education'];
                if (isset($validated['education_field'])) $teacherUpdateData['education_field'] = $validated['education_field'];
                if (isset($validated['job_history'])) $teacherUpdateData['job_history'] = $validated['job_history'];

                if (!empty($teacherUpdateData)) {
                    $teacher->update($teacherUpdateData);
                }
            }

            // ۳. بروزرسانی تخصص‌ها (اگر وجود داشته باشند)
            if (isset($validated['expertise_ids'])) {
                // sync جایگزین تخصص‌های قبلی با تخصص‌های جدید می‌شود
                $teacher->expertises()->sync($validated['expertise_ids']);
            }

            DB::commit();

            // بارگذاری روابط
            $user->load('roles');
            $teacher->load('expertises');

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "بروزرسانی اطلاعات معلم",
                "اطلاعات معلم {$user->full_name} بروزرسانی شد",
                "notification_teacher",
                [
                    'teacher_id' => $teacher->id,
                    'user_id' => $user->id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'اطلاعات معلم با موفقیت بروزرسانی شد',
                'data' => [
                    'user' => $user,
                    'teacher' => $teacher,
                    'expertises' => $teacher->expertises
                ]
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
        // پیدا کردن کاربر با نقش teacher
        $user = User::withRole('teacher')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $permanent = $request->get('permanent', false);
        $teacherName = $user->full_name;
        $teacher = $user->teacher; // دریافت رکورد معلم

        DB::beginTransaction();

        try {
            if ($permanent) {
                // ============ حذف کامل (Permanent Delete) ============

                // ۱. حذف آواتار کاربر
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                // ۲. حذف تخصص‌های معلم (جدول واسط)
                if ($teacher) {
                    $teacher->expertises()->detach(); // حذف روابط در جدول expertise_teacher
                }

                // ۳. حذف رکورد teacher
                if ($teacher) {
                    $teacher->forceDelete();
                }

                // ۴. حذف نقش‌های اختصاصی معلم 
                $specialRole = Role::where('slug', 'role_teacher_' . $user->id)->first();
                if ($specialRole) {
                    // حذف دسترسی‌های مربوط به نقش
                    $specialRole->permissions()->detach();
                    $specialRole->delete();
                }

                // ۵. حذف دسترسی‌های اختصاصی 
                $specialPermission = Permission::where('name', 'notification_user_' . $user->id)->first();
                if ($specialPermission) {
                    $specialPermission->delete();
                }

                // ۶. حذف کاربر
                $user->forceDelete();

                $message = 'معلم و تمام اطلاعات مرتبط برای همیشه حذف شد';
            } else {
                // ============ غیرفعال کردن (Soft Delete) ============

                // ۱. غیرفعال کردن کاربر
                $user->update(['is_active' => false]);

                // ۲. اگر از SoftDeletes در مدل Teacher استفاده می‌کنید
                if ($teacher && method_exists($teacher, 'delete')) {
                    $teacher->delete(); // soft delete
                }



                $message = 'معلم با موفقیت غیرفعال شد';
            }

            DB::commit();

            // ثبت نوتیفیکیشن
            $notifications->create(
                $permanent ? "حذف دائمی معلم" : "غیرفعال سازی معلم",
                $permanent
                    ? "معلم {$teacherName} و تمام اطلاعات مرتبط برای همیشه از سیستم حذف شد"
                    : "معلم {$teacherName} با موفقیت غیرفعال شد",
                "notification_teacher",
                [
                    'teacher_id' => $id,
                    'maker' => $request->user()->full_name,
                    'permanent' => $permanent
                ]
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user_id' => $id,
                    'teacher_name' => $teacherName,
                    'permanent' => $permanent,
                    'deleted_at' => $permanent ? now() : null
                ]
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
     * دریافت دانش‌آموزان تحت نظر یک معلم
     */
    public function getStudents(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $students = $user->students()
            ->with(['class.grade'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students,
            'message' => 'لیست دانش آموزان شما'
        ], 200);
    }

    /**
     * دریافت کلاس‌های تحت تدریس معلم
     */
    public function getClasses(Request $request)
    {
        $teacher = $request->user();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        // گرفتن کلاس‌ها با زمان‌بندی‌های مربوطه
        $classes = $teacher->teachingClasses()
            ->with(['grade'])
            ->get();

        // برای هر کلاس، زمان‌بندی‌های تدریس رو اضافه کن
        foreach ($classes as $class) {
            $class->teaching_times = $teacher->classSubjectTimes()
                ->where('class_id', $class->id)
                ->with(['subject'])
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $classes,
            'message' => "لیست کلاس‌ها و زمان‌های تدریس"
        ], 200);
    }
    /**
     * دریافت وظایف محول شده به معلم
     */
    public function getTasks(Request $request)
    {
        $teacher = $request->user();
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
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'message' => 'لیست وظایف شما'
        ], 200);
    }
}
