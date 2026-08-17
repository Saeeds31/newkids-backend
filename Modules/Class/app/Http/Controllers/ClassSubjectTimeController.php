<?php

namespace Modules\Class\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Class\Http\Requests\ClassSubjectTimeStoreRequest;
use Modules\Class\Http\Requests\ClassSubjectTimeUpdateRequest;
use Modules\Class\Models\Classes;
use Modules\Class\Models\ClassSubjectTime;
use Modules\Notifications\Services\NotificationService;
use Modules\Subject\Models\Subject;
use Modules\Users\Models\User;

class ClassSubjectTimeController extends Controller
{

    /**
     * نمایش لیست تمام زمان‌بندی‌ها
     */
    public function index()
    {
        $schedules = ClassSubjectTime::with(['class.grade', 'teacher', 'subject'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }


    /**
     * ثبت زمان‌بندی جدید
     */
    public function store(Request $request, NotificationService $notifications)
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'day_of_week' => 'required|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        // بررسی تداخل زمانی برای معلم
        $overlap = ClassSubjectTime::where('teacher_id', $validated['teacher_id'])
            ->where('day_of_week', $validated['day_of_week'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhere(function ($sub) use ($validated) {
                        $sub->where('start_time', '<=', $validated['start_time'])
                            ->where('end_time', '>=', $validated['end_time']);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'این معلم در این زمان کلاس دیگری دارد'
            ], 422);
        }

        $schedule = ClassSubjectTime::create($validated);
        $schedule->load(['class.grade', 'teacher', 'subject']);

        $maker = $request->user();
        $notifications->create(
            "ثبت زمان‌بندی کلاس",
            "زمان‌بندی جدید برای کلاس {$schedule->class->name} ثبت شد",
            "notification_schedule",
            [
                'schedule_id' => $schedule->id,
                'maker' => $maker->full_name,
                'class' => $schedule->class->name,
                'subject' => $schedule->subject->name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'زمان‌بندی با موفقیت ثبت شد',
            'data' => $schedule
        ], 201);
    }

    public function show($id)
    {
        $schedule = ClassSubjectTime::with(['class.grade', 'teacher', 'subject'])
            ->find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'زمان‌بندی مورد نظر یافت نشد'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    /**
     * بروزرسانی زمان‌بندی
     */
    public function update(Request $request, $id, NotificationService $notifications)
    {
        $schedule = ClassSubjectTime::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'زمان‌بندی مورد نظر یافت نشد'
            ], 404);
        }

        $validated = $request->validate([
            'class_id' => 'sometimes|exists:classes,id',
            'teacher_id' => 'sometimes|exists:users,id',
            'subject_id' => 'sometimes|exists:subjects,id',
            'day_of_week' => 'sometimes|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
        ]);

        // بررسی تداخل (به جز خودش)
        if (
            isset($validated['teacher_id']) && isset($validated['day_of_week']) &&
            isset($validated['start_time']) && isset($validated['end_time'])
        ) {
            $overlap = ClassSubjectTime::where('teacher_id', $validated['teacher_id'])
                ->where('day_of_week', $validated['day_of_week'])
                ->where('id', '!=', $id)
                ->where(function ($q) use ($validated) {
                    $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                        ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                        ->orWhere(function ($sub) use ($validated) {
                            $sub->where('start_time', '<=', $validated['start_time'])
                                ->where('end_time', '>=', $validated['end_time']);
                        });
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'این معلم در این زمان کلاس دیگری دارد'
                ], 422);
            }
        }

        $schedule->update($validated);
        $schedule->load(['class.grade', 'teacher', 'subject']);

        $maker = $request->user();
        $notifications->create(
            "بروزرسانی زمان‌بندی",
            "زمان‌بندی کلاس {$schedule->class->name} بروزرسانی شد",
            "notification_schedule",
            [
                'schedule_id' => $schedule->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'زمان‌بندی با موفقیت بروزرسانی شد',
            'data' => $schedule
        ]);
    }

    /**
     * حذف زمان‌بندی
     */
    public function destroy($id, NotificationService $notifications)
    {
        $schedule = ClassSubjectTime::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'زمان‌بندی مورد نظر یافت نشد'
            ], 404);
        }

        $scheduleData = [
            'class' => $schedule->class->name,
            'subject' => $schedule->subject->name,
            'day' => $schedule->day_of_week,
            'time' => $schedule->start_time . ' - ' . $schedule->end_time
        ];

        $schedule->delete();

        $maker = request()->user();
        $notifications->create(
            "حذف زمان‌بندی",
            "زمان‌بندی کلاس {$scheduleData['class']} حذف شد",
            "notification_schedule",
            [
                'maker' => $maker->full_name,
                'deleted_schedule' => $scheduleData
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'زمان‌بندی با موفقیت حذف شد'
        ]);
    }

    /**
     * دریافت برنامه هفتگی یک کلاس
     */
    public function getClassSchedule($classId)
    {
        $class = Classes::find($classId);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'کلاس مورد نظر یافت نشد'
            ], 404);
        }

        $schedules = ClassSubjectTime::with(['teacher', 'subject'])
            ->where('class_id', $classId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'class' => $class->load('grade'),
                'schedules' => $schedules
            ]
        ]);
    }
    /**
     * دریافت برنامه هفتگی یک معلم
     */
    public function getTeacherSchedule($teacherId)
    {
        $teacher = User::find($teacherId);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'معلم مورد نظر یافت نشد'
            ], 404);
        }

        $schedule = ClassSubjectTime::with(['class', 'subject'])
            ->where('teacher_id', $teacherId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => $teacher->only(['id', 'first_name', 'last_name', 'full_name']),
                'schedule' => $schedule,
                'weekly_schedule' => $this->formatWeeklySchedule($schedule)
            ]
        ], 200);
    }

    /**
     * دریافت برنامه هفتگی یک درس
     */
    public function getSubjectSchedule($subjectId)
    {
        $subject = Subject::find($subjectId);

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'درس مورد نظر یافت نشد'
            ], 404);
        }

        $schedules = ClassSubjectTime::with(['class.grade', 'teacher'])
            ->where('subject_id', $subjectId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'subject' => $subject,
                'schedules' => $schedules
            ]
        ]);
    }
    /**
     * بررسی تداخل زمانی برای کلاس
     */
    private function checkClassTimeConflict($classId, $dayOfWeek, $startTime, $endTime, $excludeId = null)
    {
        $query = ClassSubjectTime::where('class_id', $classId)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q2) use ($startTime, $endTime) {
                        $q2->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * بررسی تداخل زمانی برای معلم
     */
    private function checkTeacherTimeConflict($teacherId, $dayOfWeek, $startTime, $endTime, $excludeId = null)
    {
        $query = ClassSubjectTime::where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q2) use ($startTime, $endTime) {
                        $q2->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * فرمت کردن برنامه هفتگی برای نمایش
     */
    private function formatWeeklySchedule($schedule)
    {
        $days = [
            1 => 'شنبه',
            2 => 'یکشنبه',
            3 => 'دوشنبه',
            4 => 'سه‌شنبه',
            5 => 'چهارشنبه',
            6 => 'پنجشنبه',
            7 => 'جمعه',
        ];

        $formatted = [];

        foreach ($days as $key => $dayName) {
            $formatted[$dayName] = $schedule->get($key, collect())->map(function ($item) {
                return [
                    'start_time' => $item->start_time,
                    'end_time' => $item->end_time,
                    'time_range' => $item->time_range,
                    'subject' => $item->subject->name ?? null,
                    'teacher' => $item->teacher->full_name ?? null,
                    'class' => $item->class->name ?? null,
                ];
            });
        }

        return $formatted;
    }

    /**
     * دریافت زمان‌های آزاد یک کلاس در یک روز خاص
     */
    public function getFreeTimes($classId, $dayOfWeek)
    {
        $class = Classes::find($classId);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'کلاس مورد نظر یافت نشد'
            ], 404);
        }

        $schedules = ClassSubjectTime::where('class_id', $classId)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $freeTimes = [];
        $start = '08:00'; // شروع روز
        $end = '14:00';   // پایان روز

        if ($schedules->isEmpty()) {
            $freeTimes[] = [
                'start' => $start,
                'end' => $end
            ];
        } else {
            $lastEnd = $start;

            foreach ($schedules as $schedule) {
                if ($schedule->start_time > $lastEnd) {
                    $freeTimes[] = [
                        'start' => $lastEnd,
                        'end' => $schedule->start_time
                    ];
                }
                if ($schedule->end_time > $lastEnd) {
                    $lastEnd = $schedule->end_time;
                }
            }

            if ($lastEnd < $end) {
                $freeTimes[] = [
                    'start' => $lastEnd,
                    'end' => $end
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'class_id' => $classId,
                'class_name' => $class->name,
                'day' => $dayOfWeek,
                'free_times' => $freeTimes
            ]
        ]);
    }

    public function getFormData()
    {
        $teachers = User::whereHas('roles', function ($q) {
            $q->where('slug', 'teacher');
        })->get(['id', 'first_name', 'last_name']);

        $subjects = Subject::all(['id', 'name']);

        $classes = Classes::with('grade')->get(['id', 'name', 'grade_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $teachers->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->full_name
                ]),
                'subjects' => $subjects,
                'classes' => $classes->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->full_name
                ])
            ]
        ]);
    }
    /**
     * محاسبه زمان‌های آزاد
     */
    private function calculateFreeTimes($busyTimes, $workStart, $workEnd)
    {
        $freeTimes = [];
        $lastEnd = $workStart;

        foreach ($busyTimes as $busy) {
            if ($lastEnd < $busy->start_time) {
                $freeTimes[] = [
                    'start' => $lastEnd,
                    'end' => $busy->start_time
                ];
            }
            $lastEnd = max($lastEnd, $busy->end_time);
        }

        if ($lastEnd < $workEnd) {
            $freeTimes[] = [
                'start' => $lastEnd,
                'end' => $workEnd
            ];
        }

        return $freeTimes;
    }

    /**
     * دریافت نام روز به فارسی
     */
    private function getDayName($dayOfWeek)
    {
        $days = [
            1 => 'شنبه',
            2 => 'یکشنبه',
            3 => 'دوشنبه',
            4 => 'سه‌شنبه',
            5 => 'چهارشنبه',
            6 => 'پنجشنبه',
            7 => 'جمعه',
        ];

        return $days[$dayOfWeek] ?? 'نامشخص';
    }
}
