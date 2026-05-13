<?php

namespace Modules\Traits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskEvaluationCriteria;
use Modules\Task\Models\TaskResultEvaluation;

// use Modules\Traits\Database\Factories\TraitsFactory;

class Traits extends Model
{

    use HasFactory;

    protected $table = 'traits';

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

    /**
     * دریافت لیست رنگ‌های ثابت
     */
    public static function getColorPalette(): array
    {
        return self::COLOR_PALETTE;
    }
    public static function isValidColor(string $color): bool
    {
        return in_array($color, self::COLOR_PALETTE);
    }
    
        // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با معیارهای ارزیابی تسک
     * هر ویژگی می‌تواند در چندین تسک به عنوان معیار ارزیابی استفاده شود
     */
    public function taskEvaluationCriteria()
    {
        return $this->hasMany(TaskEvaluationCriteria::class, 'criterion_id', 'id')
            ->where('criterion_type', 'trait');
    }

    /**
     * ارتباط مستقیم با تسک‌ها (از طریق task_evaluation_criteria)
     * دریافت تمام تسک‌هایی که این ویژگی در آنها ارزیابی می‌شود
     */
    public function tasks()
    {
        return $this->belongsToMany(
            Task::class,
            'task_evaluation_criteria',
            'criterion_id',
            'task_id'
        )->where('criterion_type', 'trait');
    }

    /**
     * ارتباط با نتایج ارزیابی (از طریق task_evaluation_criteria و task_result_evaluations)
     * دریافت تمام نمرات داده شده برای این ویژگی
     */
    public function resultEvaluations()
    {
        return $this->hasManyThrough(
            TaskResultEvaluation::class,
            TaskEvaluationCriteria::class,
            'criterion_id',     // کلید خارجی در task_evaluation_criteria
            'evaluation_criterion_id', // کلید خارجی در task_result_evaluations
            'id',               // کلید محلی در traits
            'id'                // کلید محلی در task_evaluation_criteria
        )->where('task_evaluation_criteria.criterion_type', 'trait');
    }
    
        // ============ متدهای کمکی (Helpers) ============





    /**
     * دریافت میانگین نمره این ویژگی برای یک دانش‌آموز خاص
     */
    public function getAverageScoreForStudent($studentId, $term = null, $academicYear = null)
    {
        $query = $this->resultEvaluations()
            ->whereHas('taskResult', function ($q) use ($studentId, $term, $academicYear) {
                $q->where('student_id', $studentId);

                if ($term && $academicYear) {
                    $q->whereHas('taskOccurrence', function ($sub) use ($term, $academicYear) {
                        $sub->whereHas('taskAssignment.task', function ($task) use ($term, $academicYear) {
                            // اگر تسک‌ها فیلد term و academic_year دارند
                            // $task->where('term', $term)->where('academic_year', $academicYear);
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
     * دریافت میانگین نمره این ویژگی برای یک کلاس خاص
     */
    public function getAverageScoreForClass($classId, $term = null, $academicYear = null)
    {
        $query = $this->resultEvaluations()
            ->whereHas('taskResult.student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });

        if ($term && $academicYear) {
            $query->whereHas('taskResult.taskOccurrence', function ($q) use ($term, $academicYear) {
                $q->whereHas('taskAssignment.task', function ($task) use ($term, $academicYear) {
                    // $task->where('term', $term)->where('academic_year', $academicYear);
                });
            });
        }

        $totalScore = $query->sum('score');
        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        return round($totalScore / $count, 2);
    }

    /**
     * دریافت توزیع نمرات این ویژگی در یک کلاس خاص
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
     * بررسی اینکه آیا این ویژگی در یک تسک خاص استفاده شده است
     */
    public function isUsedInTask($taskId)
    {
        return $this->taskEvaluationCriteria()
            ->where('task_id', $taskId)
            ->exists();
    }

    /**
     * دریافت لیست دانش‌آموزانی که بالاترین نمره را در این ویژگی دارند
     */
    public function getTopStudents($classId = null, $limit = 10)
    {
        $query = $this->resultEvaluations()
            ->select('student_id')
            ->selectRaw('AVG(score) as avg_score')
            ->groupBy('student_id')
            ->orderBy('avg_score', 'desc');

        if ($classId) {
            $query->whereHas('taskResult.student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        $results = $query->limit($limit)->get();

        $students = [];
        foreach ($results as $result) {
            $student = Student::find($result->student_id);
            if ($student) {
                $students[] = [
                    'student' => $student,
                    'average_score' => round($result->avg_score, 2),
                ];
            }
        }

        return $students;
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
     * اسکوپ ویژگی‌هایی که در تسک خاصی استفاده شده‌اند
     */
    public function scopeUsedInTask($query, $taskId)
    {
        return $query->whereHas('taskEvaluationCriteria', function ($q) use ($taskId) {
            $q->where('task_id', $taskId);
        });
    }

    /**
     * اسکوپ ویژگی‌هایی که در تسک خاصی استفاده نشده‌اند
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

    /**
     * اسکوپ ویژگی‌های یک دسته خاص (اگر دسته‌بندی داشته باشی)
     */
    public function scopeInCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
