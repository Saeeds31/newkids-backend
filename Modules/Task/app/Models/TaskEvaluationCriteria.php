<?php

namespace Modules\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Skills\Models\Skills;
use Modules\Traits\Models\Traits;

// use Modules\Task\Database\Factories\TaskEvaluationCriteriaFactory;

class TaskEvaluationCriteria extends Model
{

    use HasFactory;

    protected $table = 'task_evaluation_criteria';

    protected $fillable = [
        'task_id',
        'criterion_type',
        'criterion_id',
        'weight',
        'max_score',
    ];

    protected $casts = [
        'weight' => 'integer',
        'max_score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============ ثابت‌ها (Constants) ============

    const TYPE_TRAIT = 'trait';
    const TYPE_SKILL = 'skill';

    const TYPES = [
        self::TYPE_TRAIT => 'ویژگی شخصیتی',
        self::TYPE_SKILL => 'مهارت',
    ];
    
        // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با تسک
     * هر معیار ارزیابی متعلق به یک تسک است
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * ارتباط پلی‌مورفیک با ویژگی یا مهارت
     * (این روش استاندارد لاراول برای چندریختی است)
     */
    public function criterion()
    {
        if ($this->criterion_type === self::TYPE_TRAIT) {
            return $this->belongsTo(Traits::class, 'criterion_id');
        }

        return $this->belongsTo(Skills::class, 'criterion_id');
    }

    /**
     * ارتباط مستقیم با ویژگی (برای راحتی)
     */
    public function trait()
    {
        return $this->belongsTo(Traits::class, 'criterion_id')
            ->where('criterion_type', self::TYPE_TRAIT);
    }

    /**
     * ارتباط مستقیم با مهارت (برای راحتی)
     */
    public function skill()
    {
        return $this->belongsTo(Skills::class, 'criterion_id')
            ->where('criterion_type', self::TYPE_SKILL);
    }

    /**
     * ارتباط با نتایج ارزیابی
     * هر معیار می‌تواند چندین نمره برای دانش‌آموزان مختلف داشته باشد
     */
    public function resultEvaluations()
    {
        return $this->hasMany(TaskResultEvaluation::class, 'evaluation_criterion_id');
    }
    
        // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نوع معیار به صورت فارسی
     */
    public function getTypePersianAttribute()
    {
        return self::TYPES[$this->criterion_type] ?? $this->criterion_type;
    }

    /**
     * دریافت نام معیار (از ویژگی یا مهارت مربوطه)
     */
    public function getCriterionNameAttribute()
    {
        if ($this->criterion_type === self::TYPE_TRAIT && $this->trait) {
            return $this->trait->name;
        }

        if ($this->criterion_type === self::TYPE_SKILL && $this->skill) {
            return $this->skill->name;
        }

        return 'نامشخص';
    }

    /**
     * دریافت کلید معیار (برای ارجاع در کد)
     */
    public function getCriterionKeyAttribute()
    {
        if ($this->criterion_type === self::TYPE_TRAIT && $this->trait) {
            return $this->trait->key;
        }

        if ($this->criterion_type === self::TYPE_SKILL && $this->skill) {
            return $this->skill->key;
        }

        return null;
    }

    /**
     * دریافت رنگ معیار
     */
    public function getColorAttribute()
    {
        if ($this->criterion_type === self::TYPE_TRAIT && $this->trait) {
            return $this->trait->color;
        }

        if ($this->criterion_type === self::TYPE_SKILL && $this->skill) {
            return $this->skill->color;
        }

        return '#6B7280';
    }

    /**
     * دریافت آیکون معیار
     */
    public function getIconAttribute()
    {
        if ($this->criterion_type === self::TYPE_TRAIT && $this->trait) {
            return $this->trait->icon_emoji;
        }

        if ($this->criterion_type === self::TYPE_SKILL && $this->skill) {
            return $this->skill->icon_emoji;
        }

        return '📌';
    }

    /**
     * دریافت میانگین نمره این معیار برای یک کلاس خاص
     */
    public function getAverageScoreForClass($classId, $occurrenceId = null)
    {
        $query = $this->resultEvaluations()
            ->whereHas('taskResult', function ($q) use ($classId, $occurrenceId) {
                $q->whereHas('student', function ($sub) use ($classId) {
                    $sub->where('class_id', $classId);
                });

                if ($occurrenceId) {
                    $q->where('task_occurrence_id', $occurrenceId);
                }
            });

        $scores = $query->pluck('score')->toArray();

        if (empty($scores)) {
            return 0;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * دریافت میانگین نمره این معیار برای یک دانش‌آموز خاص
     */
    public function getAverageScoreForStudent($studentId, $occurrenceId = null)
    {
        $query = $this->resultEvaluations()
            ->whereHas('taskResult', function ($q) use ($studentId, $occurrenceId) {
                $q->where('student_id', $studentId);

                if ($occurrenceId) {
                    $q->where('task_occurrence_id', $occurrenceId);
                }
            });

        $scores = $query->pluck('score')->toArray();

        if (empty($scores)) {
            return 0;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * دریافت توزیع نمرات این معیار در یک کلاس
     */
    public function getScoreDistributionInClass($classId, $occurrenceId = null)
    {
        $query = $this->resultEvaluations()
            ->whereHas('taskResult', function ($q) use ($classId, $occurrenceId) {
                $q->whereHas('student', function ($sub) use ($classId) {
                    $sub->where('class_id', $classId);
                });

                if ($occurrenceId) {
                    $q->where('task_occurrence_id', $occurrenceId);
                }
            });

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

        $distribution = [];
        for ($i = 1; $i <= $this->max_score; $i++) {
            $distribution[$i] = 0;
        }

        foreach ($scores as $score) {
            if (isset($distribution[$score])) {
                $distribution[$score]++;
            }
        }

        return [
            'average' => round(array_sum($scores) / count($scores), 2),
            'min' => min($scores),
            'max' => max($scores),
            'count' => count($scores),
            'distribution' => $distribution,
        ];
    }

    /**
     * دریافت نمره وزنی این معیار (وزن * max_score)
     */
    public function getWeightedMaxScoreAttribute()
    {
        return $this->weight * $this->max_score;
    }

    /**
     * محاسبه نمره وزنی کسب شده بر اساس نمره داده شده
     */
    public function calculateWeightedScore($score)
    {
        return $score * $this->weight;
    }

    /**
     * محاسبه درصد کسب شده بر اساس نمره داده شده
     */
    public function calculatePercentage($score)
    {
        if ($this->max_score == 0) {
            return 0;
        }

        return ($score / $this->max_score) * 100;
    }
    
        // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ معیارهای نوع ویژگی
     */
    public function scopeTraits($query)
    {
        return $query->where('criterion_type', self::TYPE_TRAIT);
    }

    /**
     * اسکوپ معیارهای نوع مهارت
     */
    public function scopeSkills($query)
    {
        return $query->where('criterion_type', self::TYPE_SKILL);
    }

    /**
     * اسکوپ بر اساس وزن (بالاترین وزن)
     */
    public function scopeHighestWeight($query, $limit = 10)
    {
        return $query->orderBy('weight', 'desc')->limit($limit);
    }

    /**
     * اسکوپ بر اساس max_score
     */
    public function scopeWithMaxScore($query, $minScore, $maxScore = null)
    {
        if ($maxScore) {
            return $query->whereBetween('max_score', [$minScore, $maxScore]);
        }

        return $query->where('max_score', $minScore);
    }

    /**
     * اسکوپ معیارهایی که حداقل یک نتیجه ارزیابی دارند
     */
    public function scopeWithResults($query)
    {
        return $query->whereHas('resultEvaluations');
    }
}
