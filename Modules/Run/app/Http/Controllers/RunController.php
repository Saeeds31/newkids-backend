<?php

namespace Modules\Run\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;
use Modules\Users\Models\User;

class RunController extends Controller
{

    public function runShop()
    {
        $user = User::create([
            'first_name' => 'دکتر احسان ',
            'last_name' => 'امیریان',
            'is_active' => true,
            'full_name' => 'super admin',
            'mobile' => '09113894304',
            'password' => Hash::make('superAdmin#123'),
        ]);
        $roleSuperAdmin = Role::create([
            'name' => 'سوپر ادمین',
            'is_system' => true,
            'slug' => 'superAdmin',
        ]);
        Role::create([
            'name' => 'مدیریت',
            'is_system' => true,
            'slug' => 'manager',
        ]);
        Role::create([
            'name' => 'معلم',
            'is_system' => true,
            'slug' => 'teacher',
        ]);

        Role::create([
            'name' => 'والدین',
            'is_system' => true,
            'slug' => 'parent',
        ]);

        $user->roles()->sync([$roleSuperAdmin]);
        return response()->json(['message' => 'تنظیمات اولیه انجام شد پرمیژن ها را اجرا کنید']);
    }
    public function setSuperAdminPermissions()
    {
        $superAdminRole = Role::where('slug', 'superAdmin')->first();
        if (!$superAdminRole) {
            throw new \Exception('نقش superAdmin پیدا نشد. لطفاً ابتدا آن را ایجاد کنید.');
        }
        $allPermissions = Permission::all();
        if ($allPermissions->isEmpty()) {
            throw new \Exception('هیچ پرمیژنی در دیتابیس وجود ندارد.');
        }
        $superAdminRole->permissions()->syncWithoutDetaching($allPermissions->pluck('id')->toArray());
        return response()->json(['message' => "همه نقش ها به سوپر ادمین اختصاص یافت", 'success' => true]);
    }
    public function setManagerPermissions()
    {
        $managerRole = Role::where('slug', 'manager')->first();
        if (!$managerRole) {
            throw new \Exception('نقش manager پیدا نشد. لطفاً ابتدا آن را ایجاد کنید.');
        }
        $allPermissions = Permission::all();
        if ($allPermissions->isEmpty()) {
            throw new \Exception('هیچ پرمیژنی در دیتابیس وجود ندارد.');
        }
        $managerRole->permissions()->syncWithoutDetaching($allPermissions->pluck('id')->toArray());
        return response()->json(['message' => "همه نقش ها به  مدیر اختصاص یافت", 'success' => true]);
    }
    public function setTeacherPermissions()
    {
        $teacher = Role::where('slug', 'teacher')->first();

        if (!$teacher) {
            throw new \Exception('نقش teacher پیدا نشد. لطفاً ابتدا آن را ایجاد کنید.');
        }

        // لیست پرمیژن‌های مورد نظر برای معلم
        $requiredPermissions = [
            'dashboard_view',
            'class_view',
            'grade_view',
            'message_view',
            'message_store',
            'message_edit',
            'message_delete',
            'skills_view',
            'student_view',
            'subject_view',
            'task_view',
            'task_store',
            'task_update',
            'traits_view',
        ];

        // پیدا کردن پرمیژن‌هایی که از قبل وجود دارند
        $existingPermissions = Permission::whereIn('name', $requiredPermissions)
            ->get()
            ->keyBy('name');

        // پیدا کردن پرمیژن‌هایی که وجود ندارند
        $missingPermissions = array_diff($requiredPermissions, $existingPermissions->keys()->toArray());

        // ایجاد پرمیژن‌های جدید
        foreach ($missingPermissions as $permissionName) {
            Permission::create([
                'name' => $permissionName,
                'label' => $this->generateLabelForPermission($permissionName), // تابع کمکی برای تولید label
            ]);
        }

        // بعد از ایجاد، دوباره همه پرمیژن‌ها را دریافت می‌کنیم
        $allPermissionIds = Permission::whereIn('name', $requiredPermissions)
            ->pluck('id')
            ->toArray();

        // اختصاص پرمیژن‌ها به نقش teacher
        $teacher->permissions()->syncWithoutDetaching($allPermissionIds);

        return response()->json([
            'message' => "پرمیژن‌های مشخص شده به نقش teacher اختصاص یافتند",
            'success' => true,
        ]);
    }
    public function setParentPermissions()
    {
        $parent = Role::where('slug', 'parent')->first();

        if (!$parent) {
            throw new \Exception('نقش parent پیدا نشد. لطفاً ابتدا آن را ایجاد کنید.');
        }

        // لیست پرمیژن‌های مورد نظر برای معلم
        $requiredPermissions = [
            'dashboard_view',
            'report_view',
            'message_view',
            'message_store',
            'message_edit',
            'message_delete',
            'student_view',
            'task_view',
        ];

        // پیدا کردن پرمیژن‌هایی که از قبل وجود دارند
        $existingPermissions = Permission::whereIn('name', $requiredPermissions)
            ->get()
            ->keyBy('name');

        // پیدا کردن پرمیژن‌هایی که وجود ندارند
        $missingPermissions = array_diff($requiredPermissions, $existingPermissions->keys()->toArray());

        // ایجاد پرمیژن‌های جدید
        foreach ($missingPermissions as $permissionName) {
            Permission::create([
                'name' => $permissionName,
                'label' => $this->generateLabelForPermission($permissionName), // تابع کمکی برای تولید label
            ]);
        }

        // بعد از ایجاد، دوباره همه پرمیژن‌ها را دریافت می‌کنیم
        $allPermissionIds = Permission::whereIn('name', $requiredPermissions)
            ->pluck('id')
            ->toArray();

        // اختصاص پرمیژن‌ها به نقش parent
        $parent->permissions()->syncWithoutDetaching($allPermissionIds);

        return response()->json([
            'message' => "پرمیژن‌های مشخص شده به نقش parent اختصاص یافتند",
            'success' => true,
        ]);
    }
    // تابع کمکی برای تولید label مناسب بر اساس name
    private function generateLabelForPermission($permissionName)
    {
        $labels = [
            'dashboard_view' => 'مشاهده داشبورد',
            'class_view' => 'مشاهده کلاس',
            'grade_view' => 'مشاهده نمرات',
            'message_view' => 'مشاهده پیام‌ها',
            'report_view' => 'مشاهده گزارش ها',
            'message_store' => 'ارسال پیام',
            'message_edit' => 'ویرایش پیام',
            'message_delete' => 'حذف پیام',
            'skills_view' => 'مشاهده مهارت‌ها',
            'student_view' => 'مشاهده دانش‌آموزان',
            'subject_view' => 'مشاهده دروس',
            'task_view' => 'مشاهده وظایف',
            'task_store' => 'ایجاد وظیفه',
            'task_update' => 'بروزرسانی وظیفه',
            'traits_view' => 'مشاهده ویژگی‌ها',
        ];

        return $labels[$permissionName] ?? $permissionName;
    }
    public function setPermissions()
    {
        $models = [
            'class'   => 'کلاس',
            'grade'   => 'پایه',
            'message'   => 'پیام',
            'skills'   => 'مهارت',
            'student'   => 'دانش آموز',
            'subject'   => 'موضوع',
            'task'   => 'وظیفه',
            'traits'   => 'ویژگی',
            'wallet'   => 'کیف پول',
            'Setting'   => 'تنظیمات',
            'Role'   => 'نقش',
            'User'   => 'کاربران',
        ];
        $actions = [
            'view'   => 'مشاهده',
            'store'  => 'ثبت',
            'update' => 'ویرایش',
            'delete' => 'حذف',

        ];
        foreach ($models as $model => $persianName) {
            $modelLower = strtolower($model);
            foreach ($actions as $action => $actionLabel) {
                Permission::updateOrCreate(
                    ['name' => "{$modelLower}_{$action}"],
                    ['label' => "{$actionLabel} {$persianName}"]
                );
            }
        }
        $others = [
            'notifications_user' => 'اعلان کاربران',
            'notification_task' => 'اعلان وظایف',
            'notification_class' => 'اعلان کلاس ها',
            'notification_student' => 'اعلان دانش آموزان',
        ];
        foreach ($others as $pername => $perPersianName) {
            Permission::updateOrCreate(
                ['name' => $pername],
                ['label' => $perPersianName]
            );
        }
        return response()->json(['message' => "همه دسترسی ها بروز رسانی شد", 'success' => true]);
    }
}
