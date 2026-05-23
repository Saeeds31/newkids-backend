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
