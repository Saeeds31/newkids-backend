<?php

namespace Modules\Activity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Activity\Models\Activity;
use Modules\Class\Models\Classes;
use Modules\Student\Models\Student;
use Modules\Task\Models\Task;
use Modules\Users\Models\User;

class DashboardController extends Controller
{
    public function managerDashboard($filters = [])
    {
        // دریافت تاریخ فیلتر (اختیاری)
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        // ============ 1. آمار تسک‌ها ============

        // تعداد کل تسک‌ها
        $totalTasks = Task::count();

        // تعداد تسک‌های یکباره
        $totalOnceTasks = Task::where('type', Task::TYPE_ONCE)->count();

        // تعداد تسک‌های یکباره انجام شده (status = done یا closed)
        $completedOnceTasks = Task::where('type', Task::TYPE_ONCE)
            ->whereIn('status', ['done', 'closed'])
            ->count();

        // درصد تسک‌های یکباره انجام شده
        $onceTasksCompletionPercentage = $totalOnceTasks > 0
            ? round(($completedOnceTasks / $totalOnceTasks) * 100, 2)
            : 0;

        // ============ 2. تعداد مربیان فعال ============

        $activeTeachersCount = User::whereHas('teacher')
            ->where('is_active', true)
            ->count();

        // ============ 3. آمار دانش‌آموزان ============

        // تعداد کل دانش‌آموزان (بر اساس کد ملی یکتا)
        $totalStudents = Student::whereNotNull('national_code')
            ->distinct('national_code')
            ->count('national_code');

        // تعداد ثبت‌نامی‌های سال جدید (بر اساس تاریخ فیلتر)
        $newStudentsQuery = Student::whereNotNull('national_code')
            ->distinct('national_code');

        if ($startDate && $endDate) {
            $newStudentsQuery->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $newStudentsQuery->where('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $newStudentsQuery->where('created_at', '<=', $endDate);
        }

        $newStudentsCount = $newStudentsQuery->count('national_code');

        // ============ 4. آخرین فعالیت‌های سیستم (۱۰ مورد آخر) ============

        $recentActivities = Activity::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'user_name' => $activity->user ? $activity->user->full_name : 'سیستم',
                    'action' => $activity->action,
                    'model' => $activity->model,
                    'description' => $activity->description,
                    'created_at' => $activity->created_at->toDateTimeString(),
                    'jalali_date' => $this->convertToJalali($activity->created_at), // اگر نیاز به تاریخ شمسی دارید
                ];
            });

        // ============ 5. آمار ثبت‌نام ماهانه دانش‌آموزان (برای نمودار area) ============

        $monthlyRegistrations = $this->getMonthlyRegistrationStats($filters);

        // ============ 6. توزیع دانش‌آموزان در کلاس‌ها (برای نمودار donut) ============

        $classDistribution = $this->getClassDistributionStats();

        // ============ 7. آمار وضعیت تسک‌ها ============

        $taskStatusStats = [
            'todo' => Task::where('status', 'todo')->count(),
            'doing' => Task::where('status', 'doing')->count(),
            'done' => Task::where('status', 'done')->count(),
            'closed' => Task::where('status', 'closed')->count(),
        ];

        // ============ 8. تسک‌های در حال انجام (اختیاری) ============

        $activeTasks = Task::whereIn('status', ['todo', 'doing'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'status', 'created_at']);

        // ============ پاسخ نهایی ============

        return response()->json([
            'success' => true,
            'data' => [
                // آمار تسک‌ها
                'tasks' => [
                    'total' => $totalTasks,
                    'once' => [
                        'total' => $totalOnceTasks,
                        'completed' => $completedOnceTasks,
                        'completion_percentage' => $onceTasksCompletionPercentage,
                    ],
                    'status_distribution' => $taskStatusStats,
                    'active_tasks' => $activeTasks,
                ],

                // آمار مربیان
                'teachers' => [
                    'active_count' => $activeTeachersCount,
                ],

                // آمار دانش‌آموزان
                'students' => [
                    'total' => $totalStudents,
                    'new_registrations' => $newStudentsCount,
                    'monthly_registrations' => $monthlyRegistrations, // برای نمودار area
                    'class_distribution' => $classDistribution, // برای نمودار donut
                ],

                // آخرین فعالیت‌ها
                'recent_activities' => $recentActivities,

                // اطلاعات فیلتر
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * دریافت آمار ثبت‌نام ماهانه دانش‌آموزان
     */
    private function getMonthlyRegistrationStats($filters = [])
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $query = Student::whereNotNull('national_code')
            ->distinct('national_code');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // دریافت آمار ماهانه (برای ۱۲ ماه گذشته)
        $months = [];
        $data = [];

        // فرض کنید سال تحصیلی از مهر شروع می‌شود
        $currentYear = now()->year;
        $startMonth = 9; // مهر (September)

        // اگر تاریخ فیلتر داریم، از آن استفاده می‌کنیم
        if ($startDate) {
            $start = Carbon::parse($startDate);
        } else {
            $start = Carbon::create($currentYear, $startMonth, 1);
        }

        if ($endDate) {
            $end = Carbon::parse($endDate);
        } else {
            $end = now();
        }

        // ایجاد آرایه ۱۲ ماهه
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            if ($month->gt($end)) {
                break;
            }

            $monthName = $this->getJalaliMonthName($month->month);
            $months[] = $monthName;

            $count = Student::whereNotNull('national_code')
                ->distinct('national_code')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $data[] = $count;
        }

        return [
            'categories' => $months,
            'series' => [
                [
                    'name' => 'ثبت‌نام',
                    'data' => $data,
                ]
            ],
        ];
    }

    /**
     * دریافت توزیع دانش‌آموزان در کلاس‌ها
     */
    private function getClassDistributionStats()
    {
        $classes = Classes::withCount('students')->get();

        $labels = [];
        $data = [];

        foreach ($classes as $class) {
            $labels[] = $class->full_name ?? $class->name;
            $data[] = $class->students_count;
        }

        // اگر کلاسی وجود نداشت، داده‌های پیش‌فرض
        if (empty($data)) {
            return [
                'labels' => ['بدون کلاس'],
                'series' => [0],
            ];
        }

        return [
            'labels' => $labels,
            'series' => $data,
        ];
    }

    /**
     * تبدیل تاریخ به شمسی (در صورت نیاز)
     */
    private function convertToJalali($date)
    {
        // می‌توانید از کتابخانه‌های تبدیل تاریخ مانند verta یا jdatetime استفاده کنید
        // مثلاً با verta:
        // return \Verta::instance($date)->format('Y/m/d H:i');
        return $date->toDateTimeString();
    }

    /**
     * دریافت نام ماه شمسی
     */
    private function getJalaliMonthName($month)
    {
        $months = [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ];

        return $months[$month] ?? '';
    }
}
