<?php

namespace Modules\Activity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Activity\Models\Activity;
use Modules\Class\Models\ClassSubjectTime;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskAssignment;
use Modules\Task\Models\TaskResults;
use Modules\Users\Models\User;
use Carbon\Carbon;

class TeacherDashboardController extends Controller
{
    /**
     * دریافت داده‌های داشبورد معلم
     */
    public function index(Request $request)
    {
        $teacher = $request->user();
        $today = Carbon::today();
        $now = Carbon::now();

        // ========== 1. وظایف امروز معلم ==========
        $todayTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
            ->where(function ($q) use ($today) {
                // تسک‌های یکباره که امروز هستند
                $q->where('type', 'once')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->orWhere(function ($sub) use ($today) {
                        // تسک‌های روتین که امروز فعال هستند
                        $sub->where('type', 'routine')
                            ->whereHas('routineSchedule', function ($schedule) use ($today) {
                                $schedule->whereDate('routine_expire_at', '>=', $today);
                            });
                    });
            })
            ->with(['creator', 'taskAssignments.class', 'routineSchedule'])
            ->orderBy('created_at', 'desc')
            ->get();

        // ========== 2. آمار وظایف ==========
        // تعداد کل وظایف اختصاص داده شده
        $totalTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->count();

        // تعداد وظایف انجام شده (وضعیت done یا closed)
        $completedTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereIn('status', ['done', 'closed'])->count();

        // درصد وظایف انجام شده
        $completionPercentage = $totalTasks > 0
            ? round(($completedTasks / $totalTasks) * 100, 2)
            : 0;

        // تعداد وظایف در حال انجام
        $doingTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('status', 'doing')->count();

        // تعداد وظایف انجام نشده
        $todoTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('status', 'todo')->count();

        // ========== 3. کلاس‌ها و درس‌های امروز ==========
        $todaySchedules = ClassSubjectTime::with(['class.grade', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $now->format('l'))
            ->orderBy('start_time')
            ->get();

        // کلاس‌های در حال برگزاری
        $currentClasses = $todaySchedules->filter(function ($schedule) use ($now) {
            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);
            return $now->between($start, $end);
        });

        // کلاس‌های آینده
        $upcomingClasses = $todaySchedules->filter(function ($schedule) use ($now) {
            $start = Carbon::parse($schedule->start_time);
            return $now->lt($start);
        });

        // کلاس‌های گذشته
        $pastClasses = $todaySchedules->filter(function ($schedule) use ($now) {
            $end = Carbon::parse($schedule->end_time);
            return $now->gt($end);
        });

        // ========== 4. آخرین فعالیت‌ها ==========
        $recentActivities = Activity::with('user')
            ->where('user_id', $teacher->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // ========== 5. آمار هفتگی وظایف (برای نمودار) ==========
        $weeklyStats = $this->getWeeklyTaskStats($teacher->id);

        // ========== 6. نتایج ثبت شده امروز ==========
        $todayResults = TaskResults::where('recorded_by', $teacher->id)
            ->whereDate('created_at', $today)
            ->count();

        // ========== 7. آخرین نتایج ثبت شده ==========
        $recentResults = TaskResults::with(['student', 'task'])
            ->where('recorded_by', $teacher->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                // وظایف امروز
                'today_tasks' => $todayTasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'type' => $task->type,
                        'type_label' => $task->type === 'routine' ? 'روتین' : 'یکبار',
                        'status' => $task->status,
                        'status_label' => $this->getStatusLabel($task->status),
                        'color_code' => $task->color_code,
                        'class_name' => $task->taskAssignments->first()?->class?->full_name ?? 'بدون کلاس',
                        'start_date' => $task->start_date?->toDateTimeString(),
                        'end_date' => $task->end_date?->toDateTimeString(),
                        'routine_time' => $task->routineSchedule ?
                            $task->routineSchedule->day_of_week . ' ' .
                            substr($task->routineSchedule->start_time, 0, 5) . '-' .
                            substr($task->routineSchedule->end_time, 0, 5) : null,
                    ];
                }),

                // آمار وظایف
                'task_stats' => [
                    'total' => $totalTasks,
                    'completed' => $completedTasks,
                    'completion_percentage' => $completionPercentage,
                    'doing' => $doingTasks,
                    'todo' => $todoTasks,
                ],

                // کلاس‌های امروز
                'today_schedules' => [
                    'current' => $currentClasses->map(fn($s) => $this->formatSchedule($s)),
                    'upcoming' => $upcomingClasses->map(fn($s) => $this->formatSchedule($s)),
                    'past' => $pastClasses->map(fn($s) => $this->formatSchedule($s)),
                    'total' => $todaySchedules->count(),
                ],

                // آخرین فعالیت‌ها
                'recent_activities' => $recentActivities->map(fn($a) => [
                    'id' => $a->id,
                    'user_name' => $a->user?->full_name,
                    'action' => $a->action,
                    'model' => $a->model,
                    'description' => $a->description,
                    'created_at' => $a->created_at->toDateTimeString(),
                    'time_ago' => $a->created_at->diffForHumans(),
                ]),

                // آمار هفتگی
                'weekly_stats' => $weeklyStats,

                // نتایج امروز
                'today_results_count' => $todayResults,
                'recent_results' => $recentResults->map(fn($r) => [
                    'id' => $r->id,
                    'student_name' => $r->student?->full_name ?? 'نامشخص',
                    'task_title' => $r->task?->title ?? 'بدون عنوان',
                    'created_at' => $r->created_at->toDateTimeString(),
                ]),
            ]
        ]);
    }

    /**
     * دریافت آمار هفتگی وظایف (اصلاح شده)
     */
  /**
 * دریافت آمار هفتگی وظایف (نسخه جایگزین با whereRaw)
 */
private function getWeeklyTaskStats($teacherId)
{
    $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    $stats = [];

    $dayLabels = [
        'Saturday' => 'شنبه',
        'Sunday' => 'یکشنبه',
        'Monday' => 'دوشنبه',
        'Tuesday' => 'سه‌شنبه',
        'Wednesday' => 'چهارشنبه',
        'Thursday' => 'پنجشنبه',
    ];

    foreach ($days as $day) {
        // استفاده از whereRaw برای فیلتر بر اساس روز هفته
        $dayNumber = $this->getDayOfWeekNumber($day);
        
        $onceCount = Task::whereHas('taskAssignments', function($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })
        ->where('type', 'once')
        ->whereRaw("DAYOFWEEK(start_date) = ?", [$dayNumber + 1]) // MySQL: 1=Sunday
        ->count();

        $routineCount = Task::whereHas('taskAssignments', function($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })
        ->where('type', 'routine')
        ->whereHas('routineSchedule', function($schedule) use ($day) {
            $schedule->where('day_of_week', $day);
        })
        ->count();

        $stats[] = [
            'day' => $day,
            'day_label' => $dayLabels[$day] ?? $day,
            'count' => $onceCount + $routineCount,
            'once_count' => $onceCount,
            'routine_count' => $routineCount,
        ];
    }

    return $stats;
}

    /**
     * تبدیل نام روز به عدد (0=Sunday, 1=Monday, ...)
     */
    private function getDayOfWeekNumber($day)
    {
        $days = [
            'Sunday' => 0,
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
        ];
        return $days[$day] ?? 0;
    }

    /**
     * فرمت‌بندی زمان‌بندی
     */
    private function formatSchedule($schedule)
    {
        return [
            'id' => $schedule->id,
            'class_name' => $schedule->class?->full_name ?? 'بدون کلاس',
            'subject_name' => $schedule->subject?->name ?? 'بدون درس',
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'day_of_week' => $schedule->day_of_week,
            'day_label' => $this->getDayLabel($schedule->day_of_week),
            'teacher_name' => $schedule->teacher?->full_name ?? 'بدون معلم',
        ];
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

    /**
     * دریافت برچسب وضعیت
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'todo' => 'انجام نشده',
            'doing' => 'در حال انجام',
            'done' => 'انجام شده',
            'closed' => 'بسته شده',
        ];
        return $labels[$status] ?? $status;
    }
}
