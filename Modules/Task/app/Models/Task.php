<?php

namespace Modules\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Class\Models\Classes;
use Modules\Skills\Models\Skills;
use Modules\Traits\Models\Traits;
use Modules\Users\Models\User;

class Task extends Model
{

    use HasFactory;

    protected $table = 'tasks';

    protected $fillable = [
        'title',
        'labels',
        'color_code',
        'description',
        'created_by',
        'type',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    public const COLOR_PALETTE = [
        '#FF5733', // قرمز-نارنجی
        '#33FF57', // سبز
        '#3357FF', // آبی
        '#F333FF', // صورتی
        '#FFD733', // زرد
        '#33FFF5', // فیروزه‌ای
        '#FF8333', // نارنجی
        '#8E44AD', // بنفش
        '#E74C3C', // قرمز
        '#2ECC71', // سبز روشن
        '#3498DB', // آبی روشن
        '#F39C12', // نارنجی تیره
        '#1ABC9C', // سبز آبی
        '#9B59B6', // ارغوانی
        '#34495E', // آبی تیره
        '#E67E22', // نارنجی
        '#7F8C8D', // خاکستری
        '#16A085', // سبز دریایی
        '#27AE60', // سبز
        '#2980B9', // آبی
        '#8E44AD', // بنفش
        '#2C3E50', // آبی نفتی
        '#D35400', // نارنجی سوخته
        '#C0392B', // قرمز تیره
    ];

    public static function getColorPalette(): array
    {
        return self::COLOR_PALETTE;
    }
    public static function isValidColor(string $color): bool
    {
        return in_array($color, self::COLOR_PALETTE);
    }
    // ============ ثابت‌ها (Constants) ============

    const TYPE_ROUTINE = 'routine';
    const TYPE_ONCE = 'once';

    const TYPES = [
        self::TYPE_ROUTINE => 'روتین',
        self::TYPE_ONCE => 'یکبار',
    ];

    // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با ایجادکننده تسک (مدیر یا ناظم)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * ارتباط با انتساب‌های تسک
     * هر تسک می‌تواند به چندین کلاس و معلم اختصاص داده شود
     */
    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class, 'task_id');
    }

    /**
     * ارتباط با کلاس‌ها (از طریق task_assignments)
     * دریافت تمام کلاس‌هایی که این تسک به آنها اختصاص داده شده
     */
    public function classes()
    {
        return $this->belongsToMany(
            Classes::class,
            'task_assignments',
            'task_id',
            'class_id'
        )->distinct();
    }

    /**
     * ارتباط با معلمان (از طریق task_assignments)
     * دریافت تمام معلمانی که این تسک به آنها اختصاص داده شده
     */
    public function teachers()
    {
        return $this->belongsToMany(
            User::class,
            'task_assignments',
            'task_id',
            'teacher_id'
        )->distinct();
    }

    /**
     * ارتباط با وهله‌های تسک (از طریق task_assignments)
     */
    public function taskOccurrences()
    {
        return $this->hasManyThrough(
            TaskOccurrences::class,
            TaskAssignment::class,
            'task_id',
            'task_assignment_id',
            'id',
            'id'
        );
    }

    /**
     * ارتباط با نتایج تسک (از طریق task_occurrences)
     */
    public function taskResults()
    {
        return $this->hasManyThrough(
            TaskResults::class,
            TaskOccurrences::class,
            'task_assignment_id',
            'task_occurrence_id',
            'id',
            'id'
        );
    }



    /**
     * ارتباط با معیارهای ارزیابی تسک
     * هر تسک می‌تواند چندین ویژگی و مهارت برای ارزیابی داشته باشد
     */
    public function evaluationCriteria()
    {
        return $this->hasMany(TaskEvaluationCriteria::class, 'task_id');
    }

    /**
     * ارتباط با ویژگی‌ها (از طریق evaluation_criteria)
     */
    public function traits()
    {
        return $this->belongsToMany(
            Traits::class,
            'task_evaluation_criteria',
            'task_id',
            'criterion_id'
        )->where('criterion_type', 'trait');
    }

    /**
     * ارتباط با مهارت‌ها (از طریق evaluation_criteria)
     */
    public function skills()
    {
        return $this->belongsToMany(
            Skills::class,
            'task_evaluation_criteria',
            'task_id',
            'criterion_id'
        )->where('criterion_type', 'skill');
    }

    /**
     * ارتباط با زمان‌بندی روتین (برای تسک‌های روتین)
     */
    public function routineSchedule()
    {
        return $this->hasOne(RoutineSchedules::class, 'task_id');
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نوع تسک به صورت فارسی
     */
    public function getTypePersianAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * دریافت رنگ تسک (برای نمایش در UI)
     */
    public function getColorAttribute()
    {
        return $this->color_code ?? '#6B7280';
    }

    /**
     * دریافت برچسب‌ها به صورت آرایه
     */
    public function getLabelsArrayAttribute()
    {
        if (!$this->labels) {
            return [];
        }

        return explode(',', $this->labels);
    }

    /**
     * دریافت وضعیت فعلی تسک (بر اساس زمان)
     */
    public function getCurrentStatusAttribute()
    {
        $now = now();

        if ($this->type === self::TYPE_ONCE) {
            if (!$this->start_date || !$this->end_date) {
                return 'not_scheduled';
            }

            if ($now < $this->start_date) {
                return 'pending';
            } elseif ($now >= $this->start_date && $now <= $this->end_date) {
                return 'active';
            } else {
                return 'expired';
            }
        }

        // برای تسک روتین
        if ($this->routineSchedule && $this->routineSchedule->routine_expire_at) {
            if ($now > $this->routineSchedule->routine_expire_at) {
                return 'expired';
            }
            return 'active';
        }

        return 'active';
    }

    /**
     * بررسی اینکه آیا تسک در حال حاضر فعال است
     */
    public function getIsActiveAttribute()
    {
        return in_array($this->current_status, ['active', 'pending']);
    }

    /**
     * دریافت تعداد وهله‌های اجرا شده این تسک
     */
    public function getOccurrencesCountAttribute()
    {
        return $this->taskOccurrences()->count();
    }

    /**
     * دریافت تعداد نتایج ثبت شده برای این تسک
     */
    public function getResultsCountAttribute()
    {
        return $this->taskResults()->count();
    }

    /**
     * دریافت میانگین نمرات این تسک برای یک دانش‌آموز خاص
     */
    public function getAverageScoreForStudent($studentId)
    {
        $results = $this->taskResults()
            ->where('student_id', $studentId)
            ->with('status')
            ->get();

        if ($results->isEmpty()) {
            return 0;
        }

        $totalPoints = $results->sum(function ($result) {
            return $result->status ? $result->status->points : 0;
        });

        return round($totalPoints / $results->count(), 2);
    }

    /**
     * دریافت توزیع وضعیت‌های این تسک برای یک کلاس خاص
     */
    public function getStatusDistributionForClass($classId)
    {
        $results = $this->taskResults()
            ->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->with('status')
            ->get();

        $distribution = [];

        foreach ($this->statusDefinitions as $status) {
            $distribution[$status->status_label] = $results->filter(function ($result) use ($status) {
                return $result->status_id === $status->id;
            })->count();
        }

        return $distribution;
    }

    /**
     * دریافت تمام معیارهای ارزیابی این تسک به همراه جزئیات
     */
    public function getFullEvaluationCriteria()
    {
        $criteria = $this->evaluationCriteria()
            ->with(['trait', 'skill'])
            ->get();

        $result = [];

        foreach ($criteria as $criterion) {
            if ($criterion->criterion_type === 'trait' && $criterion->trait) {
                $result['traits'][] = [
                    'id' => $criterion->id,
                    'name' => $criterion->trait->name,
                    'key' => $criterion->trait->key,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                ];
            } elseif ($criterion->criterion_type === 'skill' && $criterion->skill) {
                $result['skills'][] = [
                    'id' => $criterion->id,
                    'name' => $criterion->skill->name,
                    'key' => $criterion->skill->key,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                ];
            }
        }

        return $result;
    }

    /**
     * کپی کردن تنظیمات تسک برای تسک جدید
     */
    public function duplicate($newTitle = null, $newCreatedBy = null)
    {
        $newTask = $this->replicate();
        $newTask->title = $newTitle ?? $this->title . ' (کپی)';
        $newTask->created_by = $newCreatedBy ?? $this->created_by;
        $newTask->save();

        // کپی وضعیت‌ها
        foreach ($this->statusDefinitions as $status) {
            $newStatus = $status->replicate();
            $newStatus->task_id = $newTask->id;
            $newStatus->save();
        }

        // کپی معیارهای ارزیابی
        foreach ($this->evaluationCriteria as $criterion) {
            $newCriterion = $criterion->replicate();
            $newCriterion->task_id = $newTask->id;
            $newCriterion->save();
        }

        // کپی زمان‌بندی روتین
        if ($this->routineSchedule) {
            $newSchedule = $this->routineSchedule->replicate();
            $newSchedule->task_id = $newTask->id;
            $newSchedule->save();
        }

        return $newTask;
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ تسک‌های روتین
     */
    public function scopeRoutine($query)
    {
        return $query->where('type', self::TYPE_ROUTINE);
    }

    /**
     * اسکوپ تسک‌های یکبار
     */
    public function scopeOnce($query)
    {
        return $query->where('type', self::TYPE_ONCE);
    }

    /**
     * اسکوپ تسک‌های فعال (بر اساس زمان)
     */
    public function scopeActive($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            // تسک‌های یکبار در بازه زمانی
            $q->where(function ($sub) use ($now) {
                $sub->where('type', self::TYPE_ONCE)
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
            })->orWhere(function ($sub) {
                // تسک‌های روتین که تاریخ انقضای آن‌ها نرسیده
                $sub->where('type', self::TYPE_ROUTINE)
                    ->whereHas('routineSchedule', function ($schedule) use ($now) {
                        $schedule->where('routine_expire_at', '>', $now);
                    });
            });
        });
    }

    /**
     * اسکوپ جستجو بر اساس عنوان
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('title', 'like', "%{$searchTerm}%")
            ->orWhere('description', 'like', "%{$searchTerm}%");
    }

    /**
     * اسکوپ تسک‌های ایجاد شده توسط یک کاربر خاص
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * اسکوپ تسک‌های یک کلاس خاص
     */
    public function scopeForClass($query, $classId)
    {
        return $query->whereHas('classes', function ($q) use ($classId) {
            $q->where('classes.id', $classId);
        });
    }

    /**
     * اسکوپ تسک‌های یک معلم خاص
     */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->whereHas('teachers', function ($q) use ($teacherId) {
            $q->where('users.id', $teacherId);
        });
    }

    /**
     * مرتب‌سازی بر اساس تاریخ ایجاد (جدیدترین)
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * مرتب‌سازی بر اساس عنوان
     */
    public function scopeOrderByTitle($query, $direction = 'asc')
    {
        return $query->orderBy('title', $direction);
    }
}
