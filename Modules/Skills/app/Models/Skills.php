<?php

namespace Modules\Skills\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskEvaluationCriteria;
use Modules\Task\Models\TaskResultEvaluation;

// use Modules\Skills\Database\Factories\SkillsFactory;

class Skills extends Model
{


    use HasFactory;

    protected $table = 'skills';

    protected $fillable = [
        'name',
        'icon',
        'key',
        'description',
        'color_code',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با معیارهای ارزیابی تسک
     * هر مهارت می‌تواند در چندین تسک به عنوان معیار ارزیابی استفاده شود
     */
    public function taskEvaluationCriteria()
    {
        return $this->hasMany(TaskEvaluationCriteria::class, 'criterion_id', 'id')
            ->where('criterion_type', 'skill');
    }

    /**
     * ارتباط مستقیم با تسک‌ها (از طریق task_evaluation_criteria)
     * دریافت تمام تسک‌هایی که این مهارت در آنها ارزیابی می‌شود
     */
    public function tasks()
    {
        return $this->belongsToMany(
            Task::class,
            'task_evaluation_criteria',
            'criterion_id',
            'task_id'
        )->where('criterion_type', 'skill');
    }

    /**
     * ارتباط با نتایج ارزیابی (از طریق task_evaluation_criteria و task_result_evaluations)
     * دریافت تمام نمرات داده شده برای این مهارت
     */
    public function resultEvaluations()
    {
        return $this->hasManyThrough(
            TaskResultEvaluation::class,
            TaskEvaluationCriteria::class,
            'criterion_id',     // کلید خارجی در task_evaluation_criteria
            'evaluation_criterion_id', // کلید خارجی در task_result_evaluations
            'id',               // کلید محلی در skills
            'id'                // کلید محلی در task_evaluation_criteria
        )->where('task_evaluation_criteria.criterion_type', 'skill');
    }

    // ============ متدهای کمکی (Helpers) ============



    /**
     * دریافت رنگ به صورت متن (برای نمایش در UI)
     */
    public function getColorAttribute()
    {
        if ($this->color_code) {
            return $this->color_code;
        }

        // رنگ‌های پیش‌فرض بر اساس کلید مهارت
        $defaultColors = [
            'mindfulness' => '#10B981', // سبز
            'self_regulation' => '#3B82F6', // آبی
            'creativity' => '#8B5CF6', // بنفش
            'persistence' => '#EF4444', // قرمز
            'problem_solving' => '#F59E0B', // نارنجی
            'communication' => '#06B6D4', // فیروزه‌ای
            'teamwork' => '#EC4899', // صورتی
        ];

        return $defaultColors[$this->key] ?? '#6B7280'; // خاکستری
    }

    /**
     * دریافت میانگین نمره این مهارت برای یک دانش‌آموز خاص
     */
    public function getAverageScoreForStudent($studentId, $term = null, $academicYear = null)
    {
        $query = $this->resultEvaluations()
            ->whereHas('taskResult', function ($q) use ($studentId, $term, $academicYear) {
                $q->where('student_id', $studentId);

                if ($term && $academicYear) {
                    $q->whereHas('taskOccurrence', function ($sub) use ($term, $academicYear) {
                        $sub->whereHas('taskAssignment.task', function ($task) use ($term, $academicYear) {
                            // فرض می‌کنیم تسک‌ها فیلد term و academic_year دارند
                            // اگر نداری، این بخش را حذف کن
                        });
                    });
                }
            });

        $totalScore = $query->sum('score');
        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        return round($totalScore / $count, 2);
    }

    /**
     * دریافت توزیع نمرات این مهارت در یک کلاس خاص
     */
    public function getScoreDistributionInClass($classId, $taskId = null)
    {
        $query = $this->resultEvaluations()
            ->whereHas('taskResult.student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });

        if ($taskId) {
            $query->whereHas('taskEvaluationCriterion', function ($q) use ($taskId) {
                $q->where('task_id', $taskId);
            });
        }

        $scores = $query->pluck('score')->toArray();

        if (empty($scores)) {
            return [
                'average' => 0,
                'min' => 0,
                'max' => 0,
                'count' => 0,
                'distribution' => [],
            ];
        }

        return [
            'average' => round(array_sum($scores) / count($scores), 2),
            'min' => min($scores),
            'max' => max($scores),
            'count' => count($scores),
            'distribution' => array_count_values($scores),
        ];
    }

    /**
     * بررسی اینکه آیا این مهارت در یک تسک خاص استفاده شده است
     */
    public function isUsedInTask($taskId)
    {
        return $this->taskEvaluationCriteria()
            ->where('task_id', $taskId)
            ->exists();
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ جستجو بر اساس کلید (key)
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    /**
     * اسکوپ جستجو بر اساس نام
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'like', "%{$searchTerm}%")
            ->orWhere('description', 'like', "%{$searchTerm}%");
    }

    /**
     * اسکوپ مهارت‌هایی که در تسک خاصی استفاده شده‌اند
     */
    public function scopeUsedInTask($query, $taskId)
    {
        return $query->whereHas('taskEvaluationCriteria', function ($q) use ($taskId) {
            $q->where('task_id', $taskId);
        });
    }

    /**
     * اسکوپ مهارت‌هایی که در تسک خاصی استفاده نشده‌اند
     */
    public function scopeNotUsedInTask($query, $taskId)
    {
        return $query->whereDoesntHave('taskEvaluationCriteria', function ($q) use ($taskId) {
            $q->where('task_id', $taskId);
        });
    }

    /**
     * مرتب‌سازی بر اساس نام
     */
    public function scopeOrderByName($query, $direction = 'asc')
    {
        return $query->orderBy('name', $direction);
    }
}
