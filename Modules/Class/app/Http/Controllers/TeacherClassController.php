<?php

namespace Modules\Class\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Class\Models\ClassSubjectTime;
use Modules\Class\Models\Classes;
use Modules\Student\Models\Student;
use Modules\Users\Models\User;
use Carbon\Carbon;

class TeacherClassController extends Controller
{
    /**
     * دریافت لیست کلاس‌های معلم
     */
    public function index(Request $request)
    {
        $teacher = $request->user();

        // دریافت کلاس‌هایی که معلم در آنها تدریس می‌کند
        $classes = Classes::whereHas('classSubjectTimes', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
            ->with(['grade', 'classSubjectTimes' => function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                    ->with('subject');
            }])
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'full_name' => $class->full_name,
                    'grade_name' => $class->grade?->name ?? 'بدون پایه',
                    'academic_year' => $class->academic_year,
                    'image' => $class->image,
                    'students_count' => $class->students()->count(),
                    'subjects' => $class->classSubjectTimes->map(function ($schedule) {
                        return [
                            'id' => $schedule->id,
                            'subject_id' => $schedule->subject_id,
                            'subject_name' => $schedule->subject?->name ?? 'بدون درس',
                            'day_of_week' => $schedule->day_of_week,
                            'day_label' => $this->getDayLabel($schedule->day_of_week),
                            'start_time' => $schedule->start_time,
                            'end_time' => $schedule->end_time,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }

    /**
     * دریافت جزئیات یک کلاس با دانش‌آموزان
     */
    public function show($id, Request $request)
    {
        $teacher = $request->user();

        // بررسی دسترسی معلم به این کلاس
        $hasAccess = ClassSubjectTime::where('class_id', $id)
            ->where('teacher_id', $teacher->id)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'شما دسترسی به این کلاس ندارید'
            ], 403);
        }

        $class = Classes::with(['grade', 'students' => function ($q) {
            $q->orderBy('first_name')->orderBy('last_name');
        }])->find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'کلاس مورد نظر یافت نشد'
            ], 404);
        }

        // دریافت زمان‌بندی کلاس
        $schedules = ClassSubjectTime::with('subject')
            ->where('class_id', $id)
            ->where('teacher_id', $teacher->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // گروه‌بندی بر اساس روز
        $schedulesByDay = [];
        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        foreach ($days as $day) {
            $schedulesByDay[$day] = $schedules->filter(fn($s) => $s->day_of_week === $day)->values();
        }

        // کلاس‌های امروز
        $today = Carbon::now()->format('l');
        $todaySchedules = $schedules->filter(fn($s) => $s->day_of_week === $today)->values();

        return response()->json([
            'success' => true,
            'data' => [
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'full_name' => $class->full_name,
                    'grade_name' => $class->grade?->name ?? 'بدون پایه',
                    'academic_year' => $class->academic_year,
                    'image' => $class->image,
                    'students_count' => $class->students->count(),
                ],
                'students' => $class->students->map(fn($student) => [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'full_name' => $student->full_name,
                    'avatar' => $student->avatar,
                    'national_code' => $student->national_code,
                    'student_code' => $student->student_code,
                    'birth_date' => $student->birth_date,
                    'age' => $student->age,
                    'parent_name' => $student->parent?->full_name ?? 'نامشخص',
                ]),
                'schedules' => $schedules->map(fn($s) => [
                    'id' => $s->id,
                    'subject_name' => $s->subject?->name ?? 'بدون درس',
                    'day_of_week' => $s->day_of_week,
                    'day_label' => $this->getDayLabel($s->day_of_week),
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                ]),
                'schedules_by_day' => collect($schedulesByDay)->map(fn($items) => $items->map(fn($s) => [
                    'id' => $s->id,
                    'subject_name' => $s->subject?->name ?? 'بدون درس',
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                ])),
                'today_schedules' => $todaySchedules->map(fn($s) => [
                    'id' => $s->id,
                    'subject_name' => $s->subject?->name ?? 'بدون درس',
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'is_current' => $this->isCurrentClass($s),
                ]),
            ]
        ]);
    }

    /**
     * دریافت دانش‌آموزان یک کلاس
     */
    public function getStudents($classId, Request $request)
    {
        $teacher = $request->user();

        // بررسی دسترسی
        $hasAccess = ClassSubjectTime::where('class_id', $classId)
            ->where('teacher_id', $teacher->id)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'شما دسترسی به این کلاس ندارید'
            ], 403);
        }

        $students = Student::where('class_id', $classId)
            ->with('parent')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn($student) => [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->full_name,
                'avatar' => $student->avatar,
                'national_code' => $student->national_code,
                'student_code' => $student->student_code,
                'birth_date' => $student->birth_date,
                'age' => $student->age,
                'parent_name' => $student->parent?->full_name ?? 'نامشخص',
            ]);

        return response()->json([
            'success' => true,
            'data' => $students
        ]);
    }

    /**
     * دریافت کلاس‌های امروز معلم
     */
    public function getTodayClasses(Request $request)
    {
        $teacher = $request->user();
        $today = Carbon::now()->format('l');

        $schedules = ClassSubjectTime::with(['class.grade', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $today)
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules->map(fn($s) => [
                'id' => $s->id,
                'class_id' => $s->class_id,
                'class_name' => $s->class?->full_name ?? 'بدون کلاس',
                'subject_name' => $s->subject?->name ?? 'بدون درس',
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'is_current' => $this->isCurrentClass($s),
                'students_count' => $s->class?->students()->count() ?? 0,
            ])
        ]);
    }

    /**
     * دریافت زمان‌بندی هفتگی معلم
     */
    public function getWeeklySchedule(Request $request)
    {
        $teacher = $request->user();
        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        $schedule = [];

        foreach ($days as $day) {
            $schedules = ClassSubjectTime::with(['class.grade', 'subject'])
                ->where('teacher_id', $teacher->id)
                ->where('day_of_week', $day)
                ->orderBy('start_time')
                ->get();

            $schedule[$day] = [
                'day_label' => $this->getDayLabel($day),
                'classes' => $schedules->map(fn($s) => [
                    'id' => $s->id,
                    'class_name' => $s->class?->full_name ?? 'بدون کلاس',
                    'subject_name' => $s->subject?->name ?? 'بدون درس',
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                ]),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    /**
     * بررسی آیا کلاس در حال برگزاری است
     */
    private function isCurrentClass($schedule)
    {
        $now = Carbon::now();
        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);
        return $now->between($start, $end);
    }

    /**
     * دریافت برچسب روز
     */
    private function getDayLabel($day)
    {
        $days = [
            'Saturday' => 'شنبه',
            'Sunday' => 'یکشنبه',
            'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه',
            'Wednesday' => 'چهارشنبه',
            'Thursday' => 'پنجشنبه',
        ];
        return $days[$day] ?? $day;
    }
}
