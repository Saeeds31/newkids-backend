<?php

namespace Modules\Student\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Class\Models\Classes;
use Modules\Notifications\Services\NotificationService;
use Modules\Student\Http\Requests\StudentStoreRequest;
use Modules\Student\Http\Requests\StudentUpdateRequest;
use Modules\Student\Models\Student;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;
use Modules\Users\Models\User;

class StudentController extends Controller
{
    /**
     * نمایش لیست تمام دانش‌آموزان
     */
    public function index()
    {
        $students = Student::with(['class.grade', 'parent'])->paginate(30);

        return response()->json([
            'success' => true,
            'data' => $students
        ], 200);
    }

    /**
     * ذخیره دانش‌آموز جدید
     */
    public function store(StudentStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();
        // 1. ابتدا کاربر والد را ایجاد می‌کنیم
        $parentData = [
            'first_name' => $validated['parent_first_name'],
            'last_name' => $validated['parent_last_name'],
            'mobile' => $validated['parent_mobile'],
            'password' => Hash::make($validated['parent_password']),
            'is_active' => true,
        ];

        // مدیریت آپلود آواتار والد
        if ($request->hasFile('parent_avatar')) {
            $parentAvatarPath = $request->file('parent_avatar')->store('users/avatars', 'public');
            $parentData['avatar'] = $parentAvatarPath;
        }

        // ایجاد کاربر والد
        $parent = User::create($parentData);

        // اختصاص نقش والد به کاربر
        $parentRole = Role::where('slug', 'parent')->first();
        if ($parentRole) {
            $parent->roles()->attach($parentRole->id);
        }

        // 2. حالا دانش‌آموز را ایجاد می‌کنیم
        $studentData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'national_code' => $validated['national_code'],
            'class_id' => $validated['class_id'],
            'birth_date' => $validated['birth_date'],
            'parent_id' => $parent->id, // اتصال به والد ایجاد شده
        ];

        // مدیریت آپلود آواتار دانش‌آموز
        if ($request->hasFile('student_avatar')) {
            $studentAvatarPath = $request->file('student_avatar')->store('students/avatars', 'public');
            $studentData['avatar'] = $studentAvatarPath;
        }

        // تولید کد دانش‌آموزی خودکار
        $studentData['student_code'] = $this->generateStudentCode();

        // ایجاد دانش‌آموز
        $student = Student::create($studentData);

        // بارگذاری روابط
        $student->load(['class.grade', 'parent']);

        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "ثبت دانش‌آموز جدید",
            "دانش‌آموز {$student->first_name} {$student->last_name} با والد {$parent->first_name} {$parent->last_name} در سیستم ثبت شد",
            "notification_student",
            [
                'student_id' => $student->id,
                'parent_id' => $parent->id,
                'maker' => $maker->full_name,
                'class_id' => $student->class_id
            ]
        );
        $parentRole = Role::where('slug', 'parent')->first();
        if ($parentRole) {
            $parent->roles()->attach($parentRole->id);
        }
        $specialPer = Permission::create([
            'name' =>  "notification_student_" . $student->id,
            'label' => "ناتفیکیشن های دانش آموز" . $student->full_name,
        ]);
        $parentRole->permissions()->attach($specialPer);
        return response()->json([
            'success' => true,
            'message' => 'دانش‌آموز و والد با موفقیت ایجاد شدند',
            'data' => [
                'student' => $student,
                'parent' => $parent
            ]
        ], 201);
    }


    /**
     * نمایش یک دانش‌آموز خاص
     */
    public function show($id)
    {
        $student = Student::with(['class.grade', 'parent'])->find($id);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'دانش‌آموز مورد نظر یافت نشد'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $student
        ], 200);
    }

    /**
     * بروزرسانی دانش‌آموز
     */
    public function update(StudentUpdateRequest $request, $id, NotificationService $notifications)
    {
        $student = Student::with(['parent'])->find($id);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'دانش‌آموز مورد نظر یافت نشد'
            ], 404);
        }

        $validated = $request->validated();

        // 1. بروزرسانی اطلاعات دانش‌آموز
        $studentData = [];

        if (isset($validated['first_name'])) $studentData['first_name'] = $validated['first_name'];
        if (isset($validated['last_name'])) $studentData['last_name'] = $validated['last_name'];
        if (isset($validated['national_code'])) $studentData['national_code'] = $validated['national_code'];
        if (isset($validated['class_id'])) $studentData['class_id'] = $validated['class_id'];
        if (isset($validated['birth_date'])) $studentData['birth_date'] = $validated['birth_date'];

        // مدیریت آپلود آواتار دانش‌آموز
        if ($request->hasFile('student_avatar')) {
            // حذف آواتار قبلی
            if ($student->avatar && Storage::disk('public')->exists($student->avatar)) {
                Storage::disk('public')->delete($student->avatar);
            }
            $studentData['avatar'] = $request->file('student_avatar')->store('students/avatars', 'public');
        }

        // بروزرسانی دانش‌آموز
        $student->update($studentData);

        // 2. بروزرسانی اطلاعات والد (اگر وجود داشته باشد)
        if ($student->parent) {
            $parentData = [];

            if (isset($validated['parent_first_name'])) $parentData['first_name'] = $validated['parent_first_name'];
            if (isset($validated['parent_last_name'])) $parentData['last_name'] = $validated['parent_last_name'];
            if (isset($validated['parent_mobile'])) $parentData['mobile'] = $validated['parent_mobile'];
            if (isset($validated['parent_password']) && !empty($validated['parent_password'])) {
                $parentData['password'] = Hash::make($validated['parent_password']);
            }

            // مدیریت آپلود آواتار والد
            if ($request->hasFile('parent_avatar')) {
                if ($student->parent->avatar && Storage::disk('public')->exists($student->parent->avatar)) {
                    Storage::disk('public')->delete($student->parent->avatar);
                }
                $parentData['avatar'] = $request->file('parent_avatar')->store('users/avatars', 'public');
            }

            // بروزرسانی والد
            $student->parent->update($parentData);
        }

        // بارگذاری مجدد روابط
        $student->load(['class.grade', 'parent']);

        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی اطلاعات دانش‌آموز",
            "اطلاعات دانش‌آموز {$student->first_name} {$student->last_name} و والد مربوطه بروزرسانی شد",
            "notification_student",
            [
                'student_id' => $student->id,
                'parent_id' => $student->parent_id,
                'maker' => $maker->full_name,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'دانش‌آموز و والد با موفقیت بروزرسانی شدند',
            'data' => [
                'student' => $student,
                'parent' => $student->parent
            ]
        ], 200);
    }
    /**
     * حذف دانش‌آموز
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $student = Student::find($id);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'دانش‌آموز مورد نظر یافت نشد'
            ], 404);
        }
        $studentName = $student->first_name . ' ' . $student->last_name;
        // حذف آواتار اگر وجود داشته باشد
        if ($student->avatar && Storage::disk('public')->exists($student->avatar)) {
            Storage::disk('public')->delete($student->avatar);
        }
        $student->delete();
        // ثبت نوتیفیکیشن برای حذف
        $maker = $request->user();
        $notifications->create(
            "حذف دانش‌آموز",
            "دانش‌آموز {$studentName} از سیستم حذف شد",
            "notification_student",
            [
                'student' => $student->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'دانش‌آموز با موفقیت حذف شد'
        ], 200);
    }

    /**
     * دریافت دانش‌آموزان یک کلاس خاص
     */
    public function getStudentsByClass($classId)
    {
        $class = Classes::find($classId);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'کلاس مورد نظر یافت نشد'
            ], 404);
        }

        $students = Student::where('class_id', $classId)
            ->with(['class.grade', 'parent'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students,
            'class_name' => $class->name
        ], 200);
    }



    /**
     * جستجوی دانش‌آموز بر اساس کد ملی یا کد دانش‌آموزی
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $searchTerm = $request->q;

        $students = Student::where('national_code', 'LIKE', "%{$searchTerm}%")
            ->orWhere('student_code', 'LIKE', "%{$searchTerm}%")
            ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
            ->orWhereHas('parent', function ($query) use ($searchTerm) {
                $query->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
            })
            ->with(['class.grade', 'parent'])
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students,
            'count' => $students->count()
        ], 200);
    }

    /**
     * تولید کد دانش‌آموزی یکتا
     */
    private function generateStudentCode()
    {
        $year = Carbon::now()->format('y');
        $random = Str::random(6);
        $code = "NK{$year}{$random}";

        // اطمینان از یکتا بودن کد
        while (Student::where('student_code', $code)->exists()) {
            $random = Str::random(6);
            $code = "STU{$year}{$random}";
        }

        return $code;
    }
}
