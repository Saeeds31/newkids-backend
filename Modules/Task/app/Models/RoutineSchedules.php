<?php

namespace Modules\Task\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Task\Database\Factories\RoutineSchedulesFactory;

class RoutineSchedules extends Model
{

    use HasFactory;

    protected $table = 'routine_schedules';

    protected $fillable = [
        'task_id',
        'day_of_week',
        'start_time',
        'end_time',
        'duration_days',
        'routine_expire_at',
    ];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
        'duration_days' => 'integer',
        'routine_expire_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============ ثابت‌ها (Constants) ============

    const DAYS_OF_WEEK = [
        'Saturday' => 'شنبه',
        'Sunday' => 'یکشنبه',
        'Monday' => 'دوشنبه',
        'Tuesday' => 'سه‌شنبه',
        'Wednesday' => 'چهارشنبه',
        'Thursday' => 'پنجشنبه',
    ];

    const DAYS_ORDER = [
        'Saturday' => 1,
        'Sunday' => 2,
        'Monday' => 3,
        'Tuesday' => 4,
        'Wednesday' => 5,
        'Thursday' => 6,
    ];
    
        // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با تسک
     * هر زمان‌بندی روتین متعلق به یک تسک است
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * ارتباط با انتساب‌های تسک (از طریق task)
     */
    public function taskAssignments()
    {
        return $this->hasManyThrough(
            TaskAssignment::class,
            Task::class,
            'id',
            'task_id',
            'task_id',
            'id'
        );
    }
    
        // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نام روز به فارسی
     */
    public function getDayNamePersianAttribute()
    {
        return self::DAYS_OF_WEEK[$this->day_of_week] ?? $this->day_of_week;
    }

    /**
     * دریافت بازه زمانی به صورت رشته
     * مثال: "شنبه 09:00 - 10:00"
     */
    public function getTimeRangeAttribute()
    {
        $start = substr($this->start_time, 0, 5);
        $end = substr($this->end_time, 0, 5);

        return "{$this->day_name_persian} {$start} - {$end}";
    }

    /**
     * دریافت تاریخ انقضا به صورت شمسی
     */
    public function getRoutineExpireAtJalaliAttribute()
    {
        return $this->routine_expire_at ? verta($this->routine_expire_at)->format('Y/m/d') : null;
    }

    /**
     * بررسی اینکه آیا تسک روتین هنوز فعال است (منقضی نشده)
     */
    public function getIsValidAttribute()
    {
        return now()->lessThanOrEqualTo($this->routine_expire_at);
    }

    /**
     * دریافت روزهای باقی مانده تا انقضا
     */
    public function getDaysRemainingAttribute()
    {
        if (!$this->is_valid) {
            return 0;
        }

        return now()->diffInDays($this->routine_expire_at, false);
    }

    /**
     * دریافت تاریخ شروع اولین وهله از این تسک روتین
     */
    public function getFirstOccurrenceStartDate()
    {
        $now = now();
        $targetDay = $this->day_of_week;
        $currentDay = $now->format('l');

        $startDateTime = Carbon::parse($this->start_time);

        if ($currentDay === $targetDay) {
            if ($now->format('H:i:s') <= $this->start_time) {
                return $now->setTime($startDateTime->hour, $startDateTime->minute);
            } else {
                return $now->addWeek()->setTime($startDateTime->hour, $startDateTime->minute);
            }
        }

        $daysToAdd = $this->getDaysUntilNextOccurrence();

        return $now->addDays($daysToAdd)->setTime($startDateTime->hour, $startDateTime->minute);
    }

    /**
     * دریافت تعداد روز تا وهله بعدی
     */
    public function getDaysUntilNextOccurrence()
    {
        $daysOfWeek = array_keys(self::DAYS_OF_WEEK);
        $currentDayIndex = now()->dayOfWeek; // 0=Sunday, 1=Monday, ...

        // تبدیل به سیستم شنبه اول هفته
        $adjustedCurrentIndex = ($currentDayIndex + 1) % 7;

        $targetIndex = array_search($this->day_of_week, $daysOfWeek);

        if ($targetIndex === false) {
            return 7;
        }

        if ($targetIndex > $adjustedCurrentIndex) {
            return $targetIndex - $adjustedCurrentIndex;
        }

        return (7 - $adjustedCurrentIndex) + $targetIndex;
    }

    /**
     * محاسبه تاریخ پایان برای یک وهله
     */
    public function calculateEndDate($startDate)
    {
        $endDate = Carbon::parse($startDate);

        if ($this->duration_days > 0) {
            $endDate->addDays($this->duration_days);
        }

        // تنظیم زمان پایان
        $endTime = Carbon::parse($this->end_time);
        $endDate->setTime($endTime->hour, $endTime->minute, $endTime->second);

        return $endDate;
    }

    /**
     * دریافت لیست تاریخ‌های وهله‌های آینده تا تاریخ انقضا
     */
    public function getUpcomingOccurrences($limit = 10)
    {
        $occurrences = [];
        $currentStart = $this->getFirstOccurrenceStartDate();

        for ($i = 0; $i < $limit; $i++) {
            if ($currentStart->greaterThan($this->routine_expire_at)) {
                break;
            }

            $endDate = $this->calculateEndDate($currentStart);

            $occurrences[] = [
                'start_at' => $currentStart->copy(),
                'end_at' => $endDate,
                'week_number' => $i + 1,
            ];

            // حرکت به هفته بعد
            $currentStart->addWeek();
        }

        return $occurrences;
    }

    /**
     * ایجاد خودکار وهله‌های تسک برای یک انتساب خاص
     */
    public function createOccurrencesForAssignment($taskAssignmentId, $limit = 52)
    {
        $occurrences = $this->getUpcomingOccurrences($limit);
        $created = [];

        foreach ($occurrences as $occurrence) {
            $existing = TaskOccurrences::where('task_assignment_id', $taskAssignmentId)
                ->where('start_at', $occurrence['start_at'])
                ->exists();

            if (!$existing) {
                $taskOccurrence = TaskOccurrences::create([
                    'task_assignment_id' => $taskAssignmentId,
                    'start_at' => $occurrence['start_at'],
                    'end_at' => $occurrence['end_at'],
                    'status' => 'pending',
                ]);

                $created[] = $taskOccurrence;
            }
        }

        return $created;
    }

    /**
     * دریافت تعداد کل وهله‌های قابل تولید تا تاریخ انقضا
     */
    public function getTotalPossibleOccurrencesCount()
    {
        $firstStart = $this->getFirstOccurrenceStartDate();

        if ($firstStart->greaterThan($this->routine_expire_at)) {
            return 0;
        }

        $weeks = $firstStart->diffInWeeks($this->routine_expire_at);

        return $weeks + 1;
    }

    /**
     * دریافت الگوی زمانی به صورت خوانا
     * مثال: "هر هفته شنبه ساعت 09:00"
     */
    public function getSchedulePatternAttribute()
    {
        $time = substr($this->start_time, 0, 5);
        $duration = $this->duration_days == 1 ? 'یک روزه' : "{$this->duration_days} روزه";

        return "هر هفته {$this->day_name_persian} ساعت {$time} ({$duration})";
    }

    /**
     * بررسی اینکه آیا زمان شروع با پایان فاصله منطقی دارد
     */
    public function getIsValidTimeRangeAttribute()
    {
        $start = strtotime($this->start_time);
        $end = strtotime($this->end_time);

        if ($this->duration_days == 1) {
            // اگر duration_days = 1، end_time باید روز بعد باشد
            return true;
        }

        // اگر در همان روز است، end باید بعد از start باشد
        return $end > $start;
    }
    
        // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ زمان‌بندی‌هایی که هنوز منقضی نشده‌اند
     */
    public function scopeValid($query)
    {
        return $query->where('routine_expire_at', '>', now());
    }

    /**
     * اسکوپ زمان‌بندی‌های منقضی شده
     */
    public function scopeExpired($query)
    {
        return $query->where('routine_expire_at', '<=', now());
    }

    /**
     * اسکوپ بر اساس روز هفته
     */
    public function scopeOnDay($query, $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    /**
     * اسکوپ زمان‌بندی‌هایی که در یک بازه زمانی خاص اجرا می‌شوند
     */
    public function scopeBetweenTimes($query, $startTime, $endTime)
    {
        return $query->where('start_time', '>=', $startTime)
            ->where('start_time', '<=', $endTime);
    }

    /**
     * اسکوپ مرتب‌سازی بر اساس روز هفته (از شنبه شروع شود)
     */
    public function scopeOrderByDay($query, $direction = 'asc')
    {
        $order = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw("FIELD(day_of_week, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday') {$order}");
    }

    /**
     * اسکوپ مرتب‌سازی بر اساس زمان شروع
     */
    public function scopeOrderByStartTime($query, $direction = 'asc')
    {
        return $query->orderBy('start_time', $direction);
    }
}
