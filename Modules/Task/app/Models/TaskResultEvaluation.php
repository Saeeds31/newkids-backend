<?php

namespace Modules\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Student\Models\Student;

// use Modules\Task\Database\Factories\TaskResultEvaluationFactory;

class TaskResultEvaluation extends Model
{


    use HasFactory;

    protected $table = 'task_result_evaluations';

    protected $fillable = [
        'task_result_id',
        'evaluation_criterion_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با نتیجه تسک
     * هر ارزیابی متعلق به یک نتیجه تسک است
     */
    public function taskResult()
    {
        return $this->belongsTo(TaskResults::class, 'task_result_id');
    }

    /**
     * ارتباط با معیار ارزیابی
     * هر ارزیابی بر اساس یک معیار خاص (ویژگی یا مهارت) است
     */
    public function evaluationCriterion()
    {
        return $this->belongsTo(TaskEvaluationCriteria::class, 'evaluation_criterion_id');
    }

    /**
     * ارتباط با تسک (از طریق evaluationCriterion)
     */
    public function task()
    {
        return $this->hasOneThrough(
            Task::class,
            TaskEvaluationCriteria::class,
            'id',
            'id',
            'evaluation_criterion_id',
            'task_id'
        );
    }

    /**
     * ارتباط با دانش‌آموز (از طریق taskResult)
     */
    public function student()
    {
        return $this->hasOneThrough(
            Student::class,
            TaskResults::class,
            'id',
            'id',
            'task_result_id',
            'student_id'
        );
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نوع معیار (ویژگی یا مهارت)
     */
    public function getCriterionTypeAttribute()
    {
        return $this->evaluationCriterion->criterion_type;
    }

    /**
     * دریافت نام معیار
     */
    public function getCriterionNameAttribute()
    {
        return $this->evaluationCriterion->criterion_name;
    }

    /**
     * دریافت کلید معیار
     */
    public function getCriterionKeyAttribute()
    {
        return $this->evaluationCriterion->criterion_key;
    }

    /**
     * دریافت حداکثر نمره مجاز برای این معیار
     */
    public function getMaxScoreAttribute()
    {
        return $this->evaluationCriterion->max_score;
    }

    /**
     * دریافت وزن این معیار
     */
    public function getWeightAttribute()
    {
        return $this->evaluationCriterion->weight;
    }

    /**
     * دریافت نمره وزنی کسب شده
     */
    public function getWeightedScoreAttribute()
    {
        return $this->score * $this->weight;
    }

    /**
     * دریافت حداکثر نمره وزنی قابل کسب
     */
    public function getMaxWeightedScoreAttribute()
    {
        return $this->max_score * $this->weight;
    }

    /**
     * دریافت درصد کسب شده برای این معیار
     */
    public function getPercentageAttribute()
    {
        if ($this->max_score == 0) {
            return 0;
        }

        return round(($this->score / $this->max_score) * 100, 2);
    }

    /**
     * دریافت وضعیت کیفی بر اساس درصد
     */
    public function getQualitativeStatusAttribute()
    {
        $percentage = $this->percentage;

        if ($percentage >= 90) return 'عالی';
        if ($percentage >= 75) return 'خوب';
        if ($percentage >= 60) return 'متوسط';
        if ($percentage >= 40) return 'نیاز به تلاش';
        return 'ضعیف';
    }

    /**
     * دریافت رنگ وضعیت کیفی
     */
    public function getQualitativeColorAttribute()
    {
        $status = $this->qualitative_status;

        $colors = [
            'عالی' => '#10B981',
            'خوب' => '#3B82F6',
            'متوسط' => '#F59E0B',
            'نیاز به تلاش' => '#EF4444',
            'ضعیف' => '#6B7280',
        ];

        return $colors[$status] ?? '#6B7280';
    }

    /**
     * دریافت آیکون معیار
     */
    public function getIconAttribute()
    {
        return $this->evaluationCriterion->icon;
    }

    /**
     * دریافت رنگ معیار
     */
    public function getColorAttribute()
    {
        return $this->evaluationCriterion->color;
    }

    /**
     * بررسی اینکه آیا نمره ثبت شده است
     */
    public function getHasScoreAttribute()
    {
        return !is_null($this->score);
    }

    /**
     * دریافت توضیحات تکمیلی از任务结果 (اگر نیاز باشد)
     */
    public function getTaskResultDescriptionAttribute()
    {
        return $this->taskResult->description;
    }

    /**
     * دریافت نام کامل دانش‌آموز
     */
    public function getStudentNameAttribute()
    {
        return $this->student->full_name ?? 'نامشخص';
    }

    /**
     * دریافت عنوان کامل تسک
     */
    public function getTaskTitleAttribute()
    {
        return $this->taskResult->taskOccurrence->taskAssignment->task->title ?? 'بدون عنوان';
    }

    // ============ متدهای استاتیک (Static Methods) ============

    /**
     * ثبت یا به‌روزرسانی ارزیابی برای یک نتیجه
     */
    public static function updateOrCreateForResult($taskResultId, $criterionId, $score)
    {
        return self::updateOrCreate(
            [
                'task_result_id' => $taskResultId,
                'evaluation_criterion_id' => $criterionId,
            ],
            [
                'score' => $score,
            ]
        );
    }

    /**
     * ثبت批量 ارزیابی برای یک نتیجه
     */
    public static function bulkCreateForResult($taskResultId, array $scores)
    {
        $created = [];

        foreach ($scores as $criterionId => $score) {
            $evaluation = self::updateOrCreateForResult($taskResultId, $criterionId, $score);
            $created[] = $evaluation;
        }

        return $created;
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ ارزیابی‌های یک دانش‌آموز خاص
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->whereHas('taskResult', function ($q) use ($studentId) {
            $q->where('student_id', $studentId);
        });
    }

    /**
     * اسکوپ ارزیابی‌های یک تسک خاص
     */
    public function scopeForTask($query, $taskId)
    {
        return $query->whereHas('evaluationCriterion', function ($q) use ($taskId) {
            $q->where('task_id', $taskId);
        });
    }

    /**
     * اسکوپ ارزیابی‌های یک کلاس خاص
     */
    public function scopeForClass($query, $classId)
    {
        return $query->whereHas('taskResult.student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });
    }

    /**
     * اسکوپ ارزیابی‌های نوع ویژگی
     */
    public function scopeTraits($query)
    {
        return $query->whereHas('evaluationCriterion', function ($q) {
            $q->where('criterion_type', 'trait');
        });
    }

    /**
     * اسکوپ ارزیابی‌های نوع مهارت
     */
    public function scopeSkills($query)
    {
        return $query->whereHas('evaluationCriterion', function ($q) {
            $q->where('criterion_type', 'skill');
        });
    }

    /**
     * اسکوپ ارزیابی‌های با نمره بالا
     */
    public function scopeHighScore($query, $threshold = 8)
    {
        return $query->where('score', '>=', $threshold);
    }

    /**
     * اسکوپ ارزیابی‌های با نمره پایین
     */
    public function scopeLowScore($query, $threshold = 4)
    {
        return $query->where('score', '<=', $threshold);
    }

    /**
     * اسکوپ ارزیابی‌هایی که نمره دارند (نال نباشند)
     */
    public function scopeWithScore($query)
    {
        return $query->whereNotNull('score');
    }

    /**
     * اسکوپ ارزیابی‌هایی که نمره ندارند
     */
    public function scopeWithoutScore($query)
    {
        return $query->whereNull('score');
    }

    /**
     * مرتب‌سازی بر اساس نمره (نزولی)
     */
    public function scopeOrderByScore($query, $direction = 'desc')
    {
        return $query->orderBy('score', $direction);
    }

    /**
     * مرتب‌سازی بر اساس وزن معیار
     */
    public function scopeOrderByWeight($query, $direction = 'desc')
    {
        return $query->join('task_evaluation_criteria', 'task_result_evaluations.evaluation_criterion_id', '=', 'task_evaluation_criteria.id')
            ->orderBy('task_evaluation_criteria.weight', $direction)
            ->select('task_result_evaluations.*');
    }
}
