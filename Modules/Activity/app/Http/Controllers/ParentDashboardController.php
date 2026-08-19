<?php

namespace Modules\Activity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Student\Models\Student;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskResults;
use Modules\Class\Models\ClassSubjectTime;
use Modules\Activity\Services\ActivityLogger;
use Carbon\Carbon;

class ParentDashboardController extends Controller
{
    /**
     * دریافت اطلاعات داشبورد والدین
     */
    public function index(Request $request)
    {
        $parent = $request->user();
        
        // دریافت فرزندان این والد
        $children = Student::with(['class.grade', 'class.classSubjectTimes.subject'])
            ->where('parent_id', $parent->id)
            ->get();

        if ($children->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'هیچ فرزندی برای این والد ثبت نشده است',
                    'children' => [],
                    'statistics' => $this->getEmptyStatistics()
                ]
            ]);
        }

        $childData = [];
        $allStatistics = [];

        foreach ($children as $child) {
            // اطلاعات پایه فرزند
            $childInfo = [
                'id' => $child->id,
                'full_name' => $child->full_name,
                'avatar' => $child->avatar,
                'age' => $child->age,
                'class_name' => $child->class?->full_name ?? 'بدون کلاس',
                'grade_name' => $child->class?->grade?->name ?? 'بدون پایه',
                'student_code' => $child->student_code,
                'birth_date' => $child->birth_date?->toDateString(),
            ];

            // کلاس‌های امروز فرزند
            $todaySchedules = $this->getTodaySchedules($child);
            
            // آمار وظایف فرزند
            $taskStatistics = $this->getChildTaskStatistics($child);
            
            // آخرین نتایج فرزند
            $recentResults = $this->getRecentResults($child);
            
            // پیشرفت کلی
            $overallProgress = $this->getOverallProgress($child);

            $childData[] = [
                'info' => $childInfo,
                'today_schedules' => $todaySchedules,
                'task_statistics' => $taskStatistics,
                'recent_results' => $recentResults,
                'overall_progress' => $overallProgress,
            ];

            // جمع‌آوری آمار کلی برای همه فرزندان
            $allStatistics[] = $taskStatistics;
        }

        // محاسبه آمار کلی
        $totalTasks = array_sum(array_column($allStatistics, 'total_tasks'));
        $completedTasks = array_sum(array_column($allStatistics, 'completed_tasks'));
        $totalStudents = $children->count();

        return response()->json([
            'success' => true,
            'data' => [
                'children' => $childData,
                'summary' => [
                    'total_children' => $totalStudents,
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'completion_percentage' => $totalTasks > 0 
                        ? round(($completedTasks / $totalTasks) * 100, 2) 
                        : 0,
                ]
            ]
        ]);
    }

    /**
     * دریافت اطلاعات یک فرزند خاص
     */
    public function getChildDetail($childId, Request $request)
    {
        $parent = $request->user();
        
        // بررسی اینکه این فرزند متعلق به این والد است
        $child = Student::with(['class.grade', 'class.classSubjectTimes.subject'])
            ->where('id', $childId)
            ->where('parent_id', $parent->id)
            ->first();

        if (!$child) {
            return response()->json([
                'success' => false,
                'message' => 'فرزند مورد نظر یافت نشد'
            ], 404);
        }

        // اطلاعات فرزند
        $childInfo = [
            'id' => $child->id,
            'full_name' => $child->full_name,
            'avatar' => $child->avatar,
            'age' => $child->age,
            'class_name' => $child->class?->full_name ?? 'بدون کلاس',
            'grade_name' => $child->class?->grade?->name ?? 'بدون پایه',
            'student_code' => $child->student_code,
            'national_code' => $child->national_code,
            'birth_date' => $child->birth_date?->toDateString(),
            'parent_name' => $child->parent?->full_name ?? 'نامشخص',
        ];

        // کلاس‌های امروز
        $todaySchedules = $this->getTodaySchedules($child);
        
        // برنامه هفتگی
        $weeklySchedule = $this->getWeeklySchedule($child);
        
        // آمار وظایف
        $taskStatistics = $this->getChildTaskStatistics($child);
        
        // آخرین نتایج (با جزئیات بیشتر)
        $recentResults = $this->getRecentResults($child, 10);
        
        // پیشرفت کلی
        $overallProgress = $this->getOverallProgress($child);
        
        // توزیع نمرات
        $scoreDistribution = $this->getScoreDistribution($child);

        return response()->json([
            'success' => true,
            'data' => [
                'info' => $childInfo,
                'today_schedules' => $todaySchedules,
                'weekly_schedule' => $weeklySchedule,
                'task_statistics' => $taskStatistics,
                'recent_results' => $recentResults,
                'overall_progress' => $overallProgress,
                'score_distribution' => $scoreDistribution,
            ]
        ]);
    }

    /**
     * دریافت کلاس‌های امروز فرزند
     */
    private function getTodaySchedules($child)
    {
        $today = Carbon::now()->format('l');
        $now = Carbon::now();

        if (!$child->class_id) {
            return [
                'current' => [],
                'upcoming' => [],
                'past' => [],
                'total' => 0
            ];
        }

        $schedules = ClassSubjectTime::with(['subject', 'teacher'])
            ->where('class_id', $child->class_id)
            ->where('day_of_week', $today)
            ->orderBy('start_time')
            ->get();

        $current = [];
        $upcoming = [];
        $past = [];

        foreach ($schedules as $schedule) {
            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);

            $item = [
                'id' => $schedule->id,
                'subject_name' => $schedule->subject?->name ?? 'بدون درس',
                'teacher_name' => $schedule->teacher?->full_name ?? 'بدون معلم',
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'day_label' => $this->getDayLabel($schedule->day_of_week),
            ];

            if ($now->between($start, $end)) {
                $item['status'] = 'current';
                $current[] = $item;
            } elseif ($now->lt($start)) {
                $item['status'] = 'upcoming';
                $upcoming[] = $item;
            } else {
                $item['status'] = 'past';
                $past[] = $item;
            }
        }

        return [
            'current' => $current,
            'upcoming' => $upcoming,
            'past' => $past,
            'total' => $schedules->count()
        ];
    }

    /**
     * دریافت برنامه هفتگی فرزند
     */
    private function getWeeklySchedule($child)
    {
        if (!$child->class_id) {
            return [];
        }

        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        $schedule = [];

        foreach ($days as $day) {
            $schedules = ClassSubjectTime::with(['subject', 'teacher'])
                ->where('class_id', $child->class_id)
                ->where('day_of_week', $day)
                ->orderBy('start_time')
                ->get();

            $schedule[$day] = [
                'day_label' => $this->getDayLabel($day),
                'classes' => $schedules->map(function($s) {
                    return [
                        'id' => $s->id,
                        'subject_name' => $s->subject?->name ?? 'بدون درس',
                        'teacher_name' => $s->teacher?->full_name ?? 'بدون معلم',
                        'start_time' => $s->start_time,
                        'end_time' => $s->end_time,
                    ];
                })
            ];
        }

        return $schedule;
    }

    /**
     * دریافت آمار وظایف فرزند
     */
    private function getChildTaskStatistics($child)
    {
        // کل وظایف مرتبط با این دانش‌آموز
        $totalTasks = TaskResults::where('student_id', $child->id)
            ->distinct('task_id')
            ->count('task_id');

        // وظایف انجام شده (با وضعیت done یا closed)
        $completedTasks = TaskResults::where('student_id', $child->id)
            ->whereHas('task', function($q) {
                $q->whereIn('status', ['done', 'closed']);
            })
            ->distinct('task_id')
            ->count('task_id');

        // وظایف در حال انجام
        $doingTasks = TaskResults::where('student_id', $child->id)
            ->whereHas('task', function($q) {
                $q->where('status', 'doing');
            })
            ->distinct('task_id')
            ->count('task_id');

        // وظایف انجام نشده
        $todoTasks = TaskResults::where('student_id', $child->id)
            ->whereHas('task', function($q) {
                $q->where('status', 'todo');
            })
            ->distinct('task_id')
            ->count('task_id');

        // میانگین نمرات
        $averageScore = TaskResults::where('student_id', $child->id)
            ->whereHas('evaluations')
            ->with('evaluations')
            ->get()
            ->map(function($result) {
                return $result->evaluations->avg('score') ?? 0;
            })
            ->avg() ?? 0;

        // تعداد کل نتایج ثبت شده
        $totalResults = TaskResults::where('student_id', $child->id)->count();

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'doing_tasks' => $doingTasks,
            'todo_tasks' => $todoTasks,
            'completion_percentage' => $totalTasks > 0 
                ? round(($completedTasks / $totalTasks) * 100, 2) 
                : 0,
            'average_score' => round($averageScore, 2),
            'total_results' => $totalResults,
        ];
    }

    /**
     * دریافت آخرین نتایج فرزند
     */
    private function getRecentResults($child, $limit = 5)
    {
        return TaskResults::where('student_id', $child->id)
            ->with(['task', 'evaluations.evaluationCriterion'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($result) {
                $totalScore = $result->evaluations->sum('score') ?? 0;
                $maxScore = $result->evaluations->sum(function($e) {
                    return $e->evaluationCriterion->max_score ?? 0;
                }) ?: 1;
                
                return [
                    'id' => $result->id,
                    'task_title' => $result->task?->title ?? 'بدون عنوان',
                    'task_status' => $result->task?->status ?? 'unknown',
                    'status_label' => $this->getStatusLabel($result->task?->status),
                    'total_score' => $totalScore,
                    'max_score' => $maxScore,
                    'percentage' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0,
                    'evaluations_count' => $result->evaluations->count(),
                    'created_at' => $result->created_at->toDateTimeString(),
                    'time_ago' => $result->created_at->diffForHumans(),
                    'color_code' => $result->task?->color_code ?? '#6B7280',
                ];
            });
    }

    /**
     * دریافت پیشرفت کلی فرزند
     */
    private function getOverallProgress($child)
    {
        $totalTasks = TaskResults::where('student_id', $child->id)
            ->distinct('task_id')
            ->count('task_id');

        $completedTasks = TaskResults::where('student_id', $child->id)
            ->whereHas('task', function($q) {
                $q->whereIn('status', ['done', 'closed']);
            })
            ->distinct('task_id')
            ->count('task_id');

        // نمرات بر اساس ماه
        $monthlyScores = TaskResults::where('student_id', $child->id)
            ->with('evaluations')
            ->get()
            ->groupBy(function($result) {
                return $result->created_at->format('Y-m');
            })
            ->map(function($results) {
                $avg = $results->map(function($r) {
                    return $r->evaluations->avg('score') ?? 0;
                })->avg();
                return round($avg, 2);
            });

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'completion_percentage' => $totalTasks > 0 
                ? round(($completedTasks / $totalTasks) * 100, 2) 
                : 0,
            'monthly_scores' => $monthlyScores,
            'trend' => $this->calculateTrend($monthlyScores),
        ];
    }

    /**
     * دریافت توزیع نمرات
     */
    private function getScoreDistribution($child)
    {
        $results = TaskResults::where('student_id', $child->id)
            ->with('evaluations')
            ->get();

        $distribution = [
            'excellent' => 0, // 90-100
            'good' => 0,      // 75-89
            'average' => 0,   // 60-74
            'below_average' => 0, // 40-59
            'poor' => 0,      // 0-39
        ];

        foreach ($results as $result) {
            $avgScore = $result->evaluations->avg('score') ?? 0;
            $maxScore = $result->evaluations->sum(function($e) {
                return $e->evaluationCriterion->max_score ?? 0;
            }) ?: 1;
            
            $percentage = $maxScore > 0 ? ($avgScore / $maxScore) * 100 : 0;
            
            if ($percentage >= 90) $distribution['excellent']++;
            elseif ($percentage >= 75) $distribution['good']++;
            elseif ($percentage >= 60) $distribution['average']++;
            elseif ($percentage >= 40) $distribution['below_average']++;
            else $distribution['poor']++;
        }

        return $distribution;
    }

    /**
     * محاسبه روند تغییرات
     */
    private function calculateTrend($monthlyScores)
    {
        if ($monthlyScores->count() < 2) return 'stable';
        
        $values = $monthlyScores->values()->toArray();
        $last = end($values);
        $first = reset($values);
        
        if ($last > $first) return 'improving';
        if ($last < $first) return 'declining';
        return 'stable';
    }

    /**
     * دریافت آمار خالی (برای زمانی که فرزندی وجود ندارد)
     */
    private function getEmptyStatistics()
    {
        return [
            'total_children' => 0,
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'completion_percentage' => 0,
        ];
    }

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