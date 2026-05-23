<?php

namespace Modules\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Class\Models\Classes;
use Modules\Message\Models\Message;
use Modules\Student\Models\Student;
use Modules\Student\Models\StudentOverallStatus;
use Modules\Users\Models\User;

// use Modules\Task\Database\Factories\TaskResultsFactory;

class TaskResults extends Model
{

    use HasFactory;

    protected $table = 'task_results';

    protected $fillable = [
        'task_id',
        'student_id',
        'description',
        'recorded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ============ ارتباطات (Relationships) ============


    /**
     * ارتباط با دانش‌آموز
     * هر نتیجه متعلق به یک دانش‌آموز است
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * ارتباط با ثبت‌کننده نتیجه (معلم)
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }




    /**
     * ارتباط با ارزیابی‌های جزئی (ویژگی‌ها و مهارت‌ها)
     * هر نتیجه می‌تواند چندین ارزیابی جزئی داشته باشد
     */
    public function evaluations()
    {
        return $this->hasMany(TaskResultEvaluation::class, 'task_result_id');
    }

    /**
     * ارتباط با پیام‌ها
     * هر نتیجه می‌تواند چندین پیام بین والد و معلم داشته باشد
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'task_result_id');
    }

    /**
     * ارتباط با کلاس (از طریق دانش‌آموز)
     */
    public function class()
    {
        return $this->hasOneThrough(
            Classes::class,
            Student::class,
            'id',
            'id',
            'student_id',
            'class_id'
        );
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت عنوان کامل نتیجه
     * مثال: "نقاشی روز مادر - سارا احمدی"
     */
    public function getFullTitleAttribute()
    {
        $taskTitle = $this->taskOccurrence->taskAssignment->task->title ?? 'بدون عنوان';
        $studentName = $this->student->full_name ?? 'نامشخص';

        return "{$taskTitle} - {$studentName}";
    }

    /**
     * دریافت وضعیت کلی این نتیجه (اگر وضعیت تعریف شده باشد)
     */
    public function getStatusLabelAttribute()
    {
        return $this->status ? $this->status->status_label : 'تعریف نشده';
    }

    /**
     * دریافت امتیاز این نتیجه (از وضعیت کلی)
     */
    public function getPointsAttribute()
    {
        return $this->status ? $this->status->points : 0;
    }

    /**
     * دریافت مجموع نمرات وزنی ارزیابی‌های جزئی
     */
    public function getTotalWeightedScoreAttribute()
    {
        $total = 0;

        foreach ($this->evaluations as $evaluation) {
            $total += $evaluation->weighted_score;
        }

        return $total;
    }

    /**
     * دریافت مجموع حداکثر نمرات وزنی ارزیابی‌های جزئی
     */
    public function getTotalMaxWeightedScoreAttribute()
    {
        $total = 0;

        foreach ($this->evaluations as $evaluation) {
            $total += $evaluation->max_weighted_score;
        }

        return $total;
    }

    /**
     * دریافت درصد نهایی از ارزیابی‌های جزئی
     */
    public function getPercentageFromEvaluationsAttribute()
    {
        $max = $this->total_max_weighted_score;

        if ($max == 0) {
            return 0;
        }

        return round(($this->total_weighted_score / $max) * 100, 2);
    }

    /**
     * دریافت وضعیت کلی بر اساس ارزیابی‌های جزئی
     */
    public function getDerivedStatusLabelAttribute()
    {
        $percentage = $this->percentage_from_evaluations;

        return StudentOverallStatus::calculateStatusByPercentage($percentage);
    }

    /**
     * دریافت تمام نمرات ویژگی‌ها به صورت آرایه
     */
    public function getTraitScoresAttribute()
    {
        $scores = [];

        foreach ($this->evaluations as $evaluation) {
            if ($evaluation->evaluationCriterion->criterion_type === 'trait') {
                $scores[] = [
                    'trait_name' => $evaluation->evaluationCriterion->criterion_name,
                    'score' => $evaluation->score,
                    'max_score' => $evaluation->evaluationCriterion->max_score,
                    'weight' => $evaluation->evaluationCriterion->weight,
                ];
            }
        }

        return $scores;
    }

    /**
     * دریافت تمام نمرات مهارت‌ها به صورت آرایه
     */
    public function getSkillScoresAttribute()
    {
        $scores = [];

        foreach ($this->evaluations as $evaluation) {
            if ($evaluation->evaluationCriterion->criterion_type === 'skill') {
                $scores[] = [
                    'skill_name' => $evaluation->evaluationCriterion->criterion_name,
                    'score' => $evaluation->score,
                    'max_score' => $evaluation->evaluationCriterion->max_score,
                    'weight' => $evaluation->evaluationCriterion->weight,
                ];
            }
        }

        return $scores;
    }

    /**
     * دریافت تعداد پیام‌های خوانده نشده برای یک کاربر خاص
     */
    public function getUnreadMessagesCountForUser($userId)
    {
        return $this->messages()
            ->where('to_user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * دریافت آخرین پیام این نتیجه
     */
    public function getLastMessageAttribute()
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * بررسی اینکه آیا نتیجه کامل است (همه معیارها ثبت شده)
     */
    public function getIsCompleteAttribute()
    {
        $expectedCriteriaCount = $this->taskOccurrence
            ->taskAssignment
            ->task
            ->evaluationCriteria()
            ->count();

        if ($expectedCriteriaCount == 0) {
            return true; // اگر معیاری تعریف نشده، کامل محسوب می‌شود
        }

        $recordedCriteriaCount = $this->evaluations()->count();

        return $recordedCriteriaCount >= $expectedCriteriaCount;
    }

    /**
     * دریافت معیارهای ثبت نشده
     */
    public function getMissingCriteriaAttribute()
    {
        $expectedCriteriaIds = $this->taskOccurrence
            ->taskAssignment
            ->task
            ->evaluationCriteria()
            ->pluck('id')
            ->toArray();

        $recordedCriteriaIds = $this->evaluations()
            ->pluck('evaluation_criterion_id')
            ->toArray();

        $missingIds = array_diff($expectedCriteriaIds, $recordedCriteriaIds);

        return TaskEvaluationCriteria::whereIn('id', $missingIds)->get();
    }

    /**
     * کپی نتیجه برای دانش‌آموز دیگر (برای تسک‌های گروهی)
     */
    public function duplicateForStudent($newStudentId, $newRecordedBy = null)
    {
        $newResult = $this->replicate();
        $newResult->student_id = $newStudentId;
        $newResult->recorded_by = $newRecordedBy ?? $this->recorded_by;
        $newResult->save();

        // کپی ارزیابی‌های جزئی
        foreach ($this->evaluations as $evaluation) {
            $newEvaluation = $evaluation->replicate();
            $newEvaluation->task_result_id = $newResult->id;
            $newEvaluation->save();
        }

        return $newResult;
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ نتایج یک معلم خاص
     */
    public function scopeRecordedByTeacher($query, $teacherId)
    {
        return $query->where('recorded_by', $teacherId);
    }

    /**
     * اسکوپ نتایج یک کلاس خاص
     */
    public function scopeInClass($query, $classId)
    {
        return $query->whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });
    }

    /**
     * اسکوپ نتایج یک تسک خاص
     */
    public function scopeForTask($query, $taskId)
    {
        return $query->whereHas('taskOccurrence.taskAssignment', function ($q) use ($taskId) {
            $q->where('task_id', $taskId);
        });
    }

    /**
     * اسکوپ نتایج یک وهله خاص
     */
    public function scopeForOccurrence($query, $occurrenceId)
    {
        return $query->where('task_occurrence_id', $occurrenceId);
    }

    /**
     * اسکوپ نتایج یک دانش‌آموز خاص
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * اسکوپ نتایج کامل (همه معیارها ثبت شده)
     */
    public function scopeComplete($query)
    {
        // این اسکوپ پیچیده است و نیاز به subquery دارد
        return $query->whereHas('evaluations');
    }

    /**
     * اسکوپ نتایج با وضعیت کلی مشخص (اگر از status_id استفاده می‌کنید)
     */
    public function scopeWithStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * مرتب‌سازی بر اساس تاریخ ثبت (جدیدترین)
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * مرتب‌سازی بر اساس دانش‌آموز
     */
    public function scopeOrderByStudent($query, $direction = 'asc')
    {
        return $query->join('students', 'task_results.student_id', '=', 'students.id')
            ->orderBy('students.first_name', $direction)
            ->orderBy('students.last_name', $direction)
            ->select('task_results.*');
    }
}
