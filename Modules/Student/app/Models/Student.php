<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Attribute\Models\Attribute;
use Modules\Class\Models\Classes;
use Modules\Concern\Models\Concern;
use Modules\Grade\Models\Grade;
use Modules\Interest\Models\Interest;
use Modules\Message\Models\Message;
use Modules\Skills\Models\Skills;
use Modules\Task\Models\TaskResultEvaluation;
use Modules\Task\Models\TaskResults;
use Modules\Traits\Models\Traits;
use Modules\Users\Models\User;

// use Modules\Student\Database\Factories\StudentFactory;

class Student extends Model
{

    use HasFactory;

    protected $table = 'students';

    protected $fillable = [
        'first_name',
        'last_name',
        'avatar',
        'national_code',
        'student_code',
        'class_id',
        'parent_id',
        'birth_date',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============ ارتباطات (Relationships) ============

    public function info()
    {
        return $this->hasOne(Info::class);
    }

    public function medicalInformation()
    {
        return $this->hasOne(MedicalInformation::class);
    }

    public function medication()
    {
        return $this->hasMany(Medication::class);
    }
    /**
     * ارتباط با کلاس
     * هر دانش‌آموز در یک کلاس تحصیل می‌کند
     */
    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * ارتباط با پایه تحصیلی (از طریق کلاس)
     */
    public function grade()
    {
        return $this->hasOneThrough(
            Grade::class,
            Classes::class,
            'id',          // کلید خارجی در classes
            'id',          // کلید محلی در grades
            'class_id',    // کلید محلی در students
            'grade_id'     // کلید خارجی در classes
        );
    }

    /**
     * ارتباط با والد (User با role=parent)
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * ارتباط با نتایج تسک‌ها
     * هر دانش‌آموز چندین نتیجه تسک دارد
     */
    public function taskResults()
    {
        return $this->hasMany(TaskResults::class, 'student_id');
    }

    /**
     * ارتباط با ارزیابی‌های جزئی (از طریق task_results)
     */
    public function taskResultEvaluations()
    {
        return $this->hasManyThrough(
            TaskResultEvaluation::class,
            TaskResults::class,
            'student_id',     // کلید خارجی در task_results
            'task_result_id', // کلید خارجی در task_result_evaluations
            'id',             // کلید محلی در students
            'id'              // کلید محلی در task_results
        );
    }

    /**
     * ارتباط با وضعیت‌های کلی دانش‌آموز در ترم‌های مختلف
     */
    public function overallStatuses()
    {
        return $this->hasMany(StudentOverallStatus::class, 'student_id');
    }

    /**
     * ارتباط با پیام‌ها (از طریق task_results)
     */
    public function messages()
    {
        return $this->hasManyThrough(
            Message::class,
            TaskResults::class,
            'student_id',
            'task_result_id',
            'id',
            'id'
        );
    }

    /**
     * ارتباط با مهارت‌ها (از طریق ارزیابی‌ها)
     */
    public function skills()
    {
        return $this->belongsToMany(
            Skills::class,
            'task_result_evaluations',
            'task_result_id',
            'evaluation_criterion_id'
        )->via('taskResults');
    }

    /**
     * ارتباط با ویژگی‌ها (از طریق ارزیابی‌ها)
     */
    public function traits()
    {
        return $this->belongsToMany(
            Traits::class,
            'task_result_evaluations',
            'task_result_id',
            'evaluation_criterion_id'
        )->via('taskResults');
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نام کامل دانش‌آموز
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * دریافت نام کامل به همراه کلاس
     * مثال: "سارا احمدی - سوم الف"
     */
    public function getFullNameWithClassAttribute()
    {
        return "{$this->full_name} - {$this->class->full_name}";
    }


    /**
     * دریافت سن دانش‌آموز
     */
    public function getAgeAttribute()
    {
        if (!$this->birth_date) {
            return null;
        }

        return $this->birth_date->age;
    }

    /**
     * دریافت وضعیت کلی دانش‌آموز در یک ترم مشخص
     */
    public function getOverallStatus($term, $academicYear)
    {
        return $this->overallStatuses()
            ->where('term', $term)
            ->where('academic_year', $academicYear)
            ->first();
    }
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'attribute_students');
    }
    public function concerns()
    {
        return $this->belongsToMany(Concern::class, 'concern_students');
    }
    public function interests()
    {
        return $this->belongsToMany(Interest::class, 'interest_students');
    }
    /**
     * دریافت درصد نهایی دانش‌آموز در یک ترم
     */
    public function getOverallScore($term, $academicYear)
    {
        $status = $this->getOverallStatus($term, $academicYear);

        return $status ? $status->total_score_percentage : 0;
    }

    /**
     * دریافت نمره یک مهارت خاص برای دانش‌آموز
     */
    public function getSkillScore($skillKey, $term = null, $academicYear = null)
    {
        $skill = Skills::where('key', $skillKey)->first();

        if (!$skill) {
            return 0;
        }

        $query = $this->taskResultEvaluations()
            ->whereHas('evaluationCriterion', function ($q) use ($skill) {
                $q->where('criterion_type', 'skill')
                    ->where('criterion_id', $skill->id);
            });

        if ($term && $academicYear) {
            $query->whereHas('taskResult.taskOccurrence', function ($q) use ($term, $academicYear) {
                $q->whereHas('taskAssignment.task', function ($task) use ($term, $academicYear) {
                    // اگر تسک‌ها فیلد term و academic_year دارند
                    // $task->where('term', $term)->where('academic_year', $academicYear);
                });
            });
        }

        $scores = $query->pluck('score')->toArray();

        if (empty($scores)) {
            return 0;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * دریافت نمره یک ویژگی خاص برای دانش‌آموز
     */
    public function getTraitScore($traitKey, $term = null, $academicYear = null)
    {
        $trait = Traits::where('key', $traitKey)->first();

        if (!$trait) {
            return 0;
        }

        $query = $this->taskResultEvaluations()
            ->whereHas('evaluationCriterion', function ($q) use ($trait) {
                $q->where('criterion_type', 'trait')
                    ->where('criterion_id', $trait->id);
            });

        if ($term && $academicYear) {
            // فیلتر بر اساس ترم و سال تحصیلی
        }

        $scores = $query->pluck('score')->toArray();

        if (empty($scores)) {
            return 0;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * دریافت تمام نمرات مهارت‌ها به صورت آرایه برای نمودار راداری
     */
    public function getAllSkillsScores($term = null, $academicYear = null)
    {
        $skills = Skills::all();
        $scores = [];

        foreach ($skills as $skill) {
            $scores[$skill->key] = [
                'name' => $skill->name,
                'score' => $this->getSkillScore($skill->key, $term, $academicYear),
                'color' => $skill->color_code,
            ];
        }

        return $scores;
    }

    /**
     * دریافت تمام نمرات ویژگی‌ها به صورت آرایه
     */
    public function getAllTraitsScores($term = null, $academicYear = null)
    {
        $traits = Traits::all();
        $scores = [];

        foreach ($traits as $trait) {
            $scores[$trait->key] = [
                'name' => $trait->name,
                'score' => $this->getTraitScore($trait->key, $term, $academicYear),
                'color' => $trait->color_code,
            ];
        }

        return $scores;
    }

    /**
     * دریافت تاریخچه نتایج یک تسک خاص برای دانش‌آموز
     */
    public function getTaskHistory($taskId)
    {
        return $this->taskResults()
            ->whereHas('taskOccurrence.taskAssignment', function ($q) use ($taskId) {
                $q->where('task_id', $taskId);
            })
            ->with(['taskOccurrence', 'status', 'evaluations.evaluationCriterion'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * دریافت تعداد کل پیام‌های خوانده نشده مربوط به این دانش‌آموز (برای والد)
     */
    public function getUnreadMessagesCountForParent($parentId)
    {
        return Message::where('to_user_id', $parentId)
            ->whereHas('taskResult', function ($q) {
                $q->where('student_id', $this->id);
            })
            ->where('is_read', false)
            ->count();
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ جستجو بر اساس نام و نام خانوادگی
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('first_name', 'like', "%{$searchTerm}%")
                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                ->orWhere('national_code', 'like', "%{$searchTerm}%")
                ->orWhere('student_code', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * اسکوپ دانش‌آموزان یک کلاس خاص
     */
    public function scopeInClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * اسکوپ دانش‌آموزان یک پایه خاص (از طریق کلاس)
     */
    public function scopeInGrade($query, $gradeId)
    {
        return $query->whereHas('class', function ($q) use ($gradeId) {
            $q->where('grade_id', $gradeId);
        });
    }

    /**
     * اسکوپ دانش‌آموزانی که والد خاصی دارند
     */
    public function scopeWithParent($query, $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * اسکوپ مرتب‌سازی بر اساس نام
     */
    public function scopeOrderByName($query, $direction = 'asc')
    {
        return $query->orderBy('first_name', $direction)
            ->orderBy('last_name', $direction);
    }

    /**
     * اسکوپ دانش‌آموزانی که حداقل یک نتیجه تسک دارند
     */
    public function scopeWithAnyTaskResult($query)
    {
        return $query->whereHas('taskResults');
    }

    /**
     * اسکوپ دانش‌آموزانی که در یک بازه سنی مشخص هستند
     */
    public function scopeBetweenAges($query, $minAge, $maxAge)
    {
        $minDate = now()->subYears($maxAge)->startOfYear();
        $maxDate = now()->subYears($minAge)->endOfYear();

        return $query->whereBetween('birth_date', [$minDate, $maxDate]);
    }
}
