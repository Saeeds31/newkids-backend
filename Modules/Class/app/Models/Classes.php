<?php
namespace Modules\Class\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Grade\Models\Grade;
use Modules\Student\Models\Student;
use Modules\Subject\Models\Subject;
use Modules\Task\Models\TaskAssignment;
use Modules\Task\Models\TaskOccurrences;
use Modules\Users\Models\User;

class Classes extends Model
{

    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'image',
        'grade_id',
        'academic_year',
    ];

    protected $casts = [
        'academic_year' => 'integer',
    ];

    // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با پایه تحصیلی
     * هر کلاس متعلق به یک پایه است
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    /**
     * ارتباط با دانش‌آموزان
     * هر کلاس چندین دانش‌آموز دارد
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * ارتباط با زمان‌بندی درس‌ها
     * هر کلاس چندین جلسه درس در هفته دارد
     */
    public function classSubjectTimes()
    {
        return $this->hasMany(ClassSubjectTime::class, 'class_id');
    }

    /**
     * ارتباط با تسک‌های اختصاص داده شده
     * هر کلاس چندین تسک دریافت می‌کند
     */
    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class, 'class_id');
    }

    /**
     * ارتباط با معلمان از طریق زمان‌بندی درس‌ها
     * دریافت لیست معلمانی که در این کلاس تدریس می‌کنند
     */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'class_subject_times', 'class_id', 'teacher_id')
                    ->distinct();
    }

    /**
     * ارتباط با درس‌ها از طریق زمان‌بندی
     * دریافت لیست درس‌هایی که در این کلاس تدریس می‌شوند
     */
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'class_subject_times', 'class_id', 'subject_id')
                    ->distinct();
    }

    /**
     * دریافت تمام وهله‌های تسک مربوط به این کلاس
     * (از طریق task_assignments -> task_occurrences)
     */
    public function taskOccurrences()
    {
        return $this->hasManyThrough(
            TaskOccurrences::class,
            TaskAssignment::class,
            'class_id',        // کلید خارجی در task_assignments
            'task_assignment_id', // کلید خارجی در task_occurrences
            'id',              // کلید محلی در classes
            'id'               // کلید محلی در task_assignments
        );
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نام کامل کلاس (پایه + نام کلاس)
     * مثال: "سوم - الف"
     */
    public function getFullNameAttribute()
    {
        return $this->grade->name . ' - ' . $this->name;
    }

    /**
     * تعداد دانش‌آموزان فعال کلاس
     */
    public function getStudentsCountAttribute()
    {
        return $this->students()->count();
    }

    /**
     * بررسی اینکه آیا معلم خاصی در این کلاس تدریس می‌کند
     */
    public function hasTeacher($teacherId)
    {
        return $this->classSubjectTimes()
            ->where('teacher_id', $teacherId)
            ->exists();
    }

    /**
     * دریافت لیست تسک‌های فعال (باز) برای این کلاس
     */
    public function getActiveTasks()
    {
        return $this->taskOccurrences()
            ->where('status', 'open')
            ->with('taskAssignment.task')
            ->get();
    }
}