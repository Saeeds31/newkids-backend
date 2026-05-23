<?php

namespace Modules\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Class\Models\Classes;
use Modules\Student\Models\Student;
use Modules\Users\Models\User;

class TaskAssignment extends Model
{


    use HasFactory;

    protected $table = 'task_assignments';

    protected $fillable = [
        'task_id',
        'class_id',
        'teacher_id',
        'assigned_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
        // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با تسک
     * هر انتساب متعلق به یک تسک است
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * ارتباط با کلاس
     * هر انتساب برای یک کلاس خاص است
     */
    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * ارتباط با معلم
     * هر انتساب برای یک معلم خاص است
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * ارتباط با انتساب‌دهنده (مدیر یا ناظم)
     * شخصی که این تسک را به کلاس و معلم اختصاص داده
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }



    /**
     * ارتباط با دانش‌آموزان (از طریق کلاس)
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Classes::class,
            'id',
            'class_id',
            'class_id',
            'id'
        );
    }
    
        // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت عنوان کامل انتساب
     * مثال: "نقاشی روز مادر - کلاس سوم الف - خانم محمدی"
     */
    public function getFullTitleAttribute()
    {
        return "{$this->task->title} - {$this->class->full_name} - {$this->teacher->first_name} {$this->teacher->last_name}";
    }





    /**
     * دریافت تعداد دانش‌آموزانی که حداقل یک نتیجه ثبت کرده‌اند
     */
    public function getStudentsWithResultsCountAttribute()
    {
        return $this->taskResults()
            ->distinct('student_id')
            ->count('student_id');
    }

    /**
     * دریافت درصد پیشرفت این انتساب (برای تسک‌های یکبار)
     */
    public function getProgressPercentageAttribute()
    {
        $totalStudents = $this->class->students()->count();

        if ($totalStudents == 0) {
            return 0;
        }

        $latestOccurrence = $this->latest_active_occurrence;

        if (!$latestOccurrence) {
            return 0;
        }

        $recordedStudents = $latestOccurrence->taskResults()
            ->distinct('student_id')
            ->count('student_id');

        return round(($recordedStudents / $totalStudents) * 100, 2);
    }

    /**
     * بررسی اینکه آیا معلم می‌تواند برای این انتساب نتیجه ثبت کند
     */
    public function canRecordResults($teacherId)
    {
        // بررسی اینکه معلم با این انتساب مطابقت دارد
        if ($this->teacher_id != $teacherId) {
            return false;
        }

        // بررسی وجود وهله باز
        $openOccurrence = $this->current_open_occurrence;

        if (!$openOccurrence) {
            return false;
        }

        return true;
    }



    
        // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ انتساب‌های یک معلم خاص
     */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * اسکوپ انتساب‌های یک کلاس خاص
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

 



    /**
     * اسکوپ انتساب‌های تسک‌های روتین
     */
    public function scopeRoutine($query)
    {
        return $query->whereHas('task', function ($q) {
            $q->where('type', 'routine');
        });
    }

    /**
     * اسکوپ انتساب‌های تسک‌های یکبار
     */
    public function scopeOnce($query)
    {
        return $query->whereHas('task', function ($q) {
            $q->where('type', 'once');
        });
    }

    /**
     * اسکوپ انتساب‌هایی که توسط یک کاربر خاص ایجاد شده‌اند
     */
    public function scopeAssignedBy($query, $userId)
    {
        return $query->where('assigned_by', $userId);
    }

    /**
     * مرتب‌سازی بر اساس تاریخ ایجاد (جدیدترین)
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
