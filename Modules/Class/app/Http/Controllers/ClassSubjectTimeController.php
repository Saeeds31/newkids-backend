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
    public function index(Request $request)
    {
        $schedules = ClassSubjectTime::with(['class', 'teacher', 'subject'])
            ->when($request->get('class_id'), function ($query, $classId) {
                return $query->where('class_id', $classId);
            })
            ->when($request->get('teacher_id'), function ($query, $teacherId) {
                return $query->where('teacher_id', $teacherId);
            })
            ->when($request->get('day_of_week'), function ($query, $day) {
                return $query->where('day_of_week', $day);
            })
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // گروه‌بندی بر اساس روزهای هفته
        $groupedByDay = $schedules->groupBy('day_of_week');

        return response()->json([
            'success' => true,
            'data' => $schedules,
            'grouped_by_day' => $groupedByDay,
            'total' => $schedules->count()
        ], 200);
    }

    /**
     * ثبت زمان‌بندی جدید
     */
    public function store(ClassSubjectTimeStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // بررسی تداخل زمانی برای کلاس
            $classConflict = $this->checkClassTimeConflict(
                $validated['class_id'],
                $validated['day_of_week'],
                $validated['start_time'],
                $validated['end_time']
            );

            if ($classConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'این کلاس در این روز و ساعت قبلاً زمان‌بندی شده است',
                    'conflict' => $classConflict
                ], 409);
            }

            // بررسی تداخل زمانی برای معلم
            $teacherConflict = $this->checkTeacherTimeConflict(
                $validated['teacher_id'],
                $validated['day_of_week'],
                $validated['start_time'],
                $validated['end_time']
            );

            if ($teacherConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'این معلم در این روز و ساعت قبلاً زمان‌بندی شده است',
                    'conflict' => $teacherConflict
                ], 409);
            }

            // ایجاد زمان‌بندی
            $schedule = ClassSubjectTime::create($validated);
            $schedule->load(['class', 'teacher', 'subject']);

            DB::commit();

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "ثبت زمان‌بندی جدید",
                "زمان‌بندی برای کلاس {$schedule->class->name} در روز {$schedule->day_name} برای شما ثبت شد",
                "role_teacher_" . $validated['teacher_id'],
                [
                    'schedule_id' => $schedule->id,
                    'maker' => $maker->full_name,
                    'class_name' => $schedule->class->name
                ]
            );
            $notifications->create(
                "ثبت زمان‌بندی جدید",
                "زمان‌بندی برای کلاس {$schedule->class->name} در روز {$schedule->day_name} ثبت شد",
                "notification_class",
                [
                    'schedule_id' => $schedule->id,
                    'maker' => $maker->full_name,
                    'class_name' => $schedule->class->name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'زمان‌بندی با موفقیت ایجاد شد',
                'data' => $schedule
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد زمان‌بندی: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * نمایش یک زمان‌بندی
     */
    public function show($id)
    {
        $schedule = ClassSubjectTime::with(['class', 'teacher', 'subject'])->find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'زمان‌بندی مورد نظر یافت نشد'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $schedule
        ], 200);
    }

    /**
     * بروزرسانی زمان‌بندی
     */
    public function update(ClassSubjectTimeUpdateRequest $request, $id, NotificationService $notifications)
    {
        $schedule = ClassSubjectTime::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'زمان‌بندی مورد نظر یافت نشد'
            ], 404);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // استفاده از مقادیر جدید یا قدیمی برای بررسی تداخل
            $classId = $validated['class_id'] ?? $schedule->class_id;
            $teacherId = $validated['teacher_id'] ?? $schedule->teacher_id;
            $dayOfWeek = $validated['day_of_week'] ?? $schedule->day_of_week;
            $startTime = $validated['start_time'] ?? $schedule->start_time;
            $endTime = $validated['end_time'] ?? $schedule->end_time;

            // بررسی تداخل زمانی برای کلاس (به جز خود این رکورد)
            $classConflict = $this->checkClassTimeConflict(
                $classId,
                $dayOfWeek,
                $startTime,
                $endTime,
                $id
            );

            if ($classConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'این کلاس در این روز و ساعت قبلاً زمان‌بندی شده است',
                    'conflict' => $classConflict
                ], 409);
            }

            // بررسی تداخل زمانی برای معلم (به جز خود این رکورد)
            $teacherConflict = $this->checkTeacherTimeConflict(
                $teacherId,
                $dayOfWeek,
                $startTime,
                $endTime,
                $id
            );

            if ($teacherConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'این معلم در این روز و ساعت قبلاً زمان‌بندی شده است',
                    'conflict' => $teacherConflict
                ], 409);
            }

            // بروزرسانی زمان‌بندی
            $schedule->update($validated);
            $schedule->load(['class', 'teacher', 'subject']);

            DB::commit();

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "بروزرسانی زمان‌بندی جدید",
                "زمان‌بندی برای کلاس {$schedule->class->name} در روز {$schedule->day_name} برای شما بروزرسانی شد",
                "role_teacher_" . $validated['teacher_id'],
                [
                    'schedule_id' => $schedule->id,
                    'maker' => $maker->full_name,
                    'class_name' => $schedule->class->name
                ]
            );
            $notifications->create(
                "بروزرسانی زمان‌بندی",
                "زمان‌بندی کلاس {$schedule->class->name} بروزرسانی شد",
                "notification_class",
                [
                    'schedule_id' => $schedule->id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'زمان‌بندی با موفقیت بروزرسانی شد',
                'data' => $schedule
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

        DB::beginTransaction();

        try {
            $scheduleInfo = [
                'class_name' => $schedule->class->name ?? 'نامشخص',
                'day_name' => $schedule->day_name,
                'time_range' => $schedule->time_range
            ];

            $schedule->delete();

            DB::commit();

            // ثبت نوتیفیکیشن
            $maker = request()->user();
            $notifications->create(
                "بروزرسانی زمان‌بندی جدید",
                "زمان‌بندی برای کلاس {$schedule->class->name} در روز {$schedule->day_name} برای شما بروزرسانی شد",
                "role_teacher_" . $schedule->teacher_id,
                [
                    'schedule_id' => $schedule->id,
                    'maker' => $maker->full_name,
                    'class_name' => $schedule->class->name
                ]
            );
            $notifications->create(
                "حذف زمان‌بندی",
                "زمان‌بندی کلاس {$scheduleInfo['class_name']} در روز {$scheduleInfo['day_name']} حذف شد",
                "notification_class",
                [
                    'deleted_schedule_id' => $id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'زمان‌بندی با موفقیت حذف شد',
                'deleted_info' => $scheduleInfo
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

        $schedule = ClassSubjectTime::with(['teacher', 'subject'])
            ->where('class_id', $classId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return response()->json([
            'success' => true,
            'data' => [
                'class' => $class,
                'schedule' => $schedule,
                'weekly_schedule' => $this->formatWeeklySchedule($schedule)
            ]
        ], 200);
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

        $schedule = ClassSubjectTime::with(['class', 'teacher'])
            ->where('subject_id', $subjectId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return response()->json([
            'success' => true,
            'data' => [
                'subject' => $subject,
                'schedule' => $schedule,
                'weekly_schedule' => $this->formatWeeklySchedule($schedule)
            ]
        ], 200);
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

        $busyTimes = ClassSubjectTime::where('class_id', $classId)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        $workStart = '07:00';
        $workEnd = '20:00';
        $freeTimes = $this->calculateFreeTimes($busyTimes, $workStart, $workEnd);
        return response()->json([
            'success' => true,
            'data' => [
                'class' => $class->name,
                'day' => $this->getDayName($dayOfWeek),
                'busy_times' => $busyTimes,
                'free_times' => $freeTimes
            ]
        ], 200);
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
