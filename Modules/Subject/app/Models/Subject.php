<?php

namespace Modules\Subject\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Class\Models\Classes;
use Modules\Class\Models\ClassSubjectTime;
use Modules\Grade\Models\Grade;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskResults;
use Modules\Users\Models\User;

// use Modules\Subject\Database\Factories\SubjectFactory;

class Subject extends Model
{

    use HasFactory, SoftDeletes;

    protected $table = 'subjects';

    protected $fillable = [
        'name',
        'image',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
        // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با زمان‌بندی کلاس‌ها
     * هر درس می‌تواند در چندین کلاس و چندین جلسه تدریس شود
     */
    public function classSubjectTimes()
    {
        return $this->hasMany(ClassSubjectTime::class, 'subject_id');
    }

    /**
     * ارتباط با کلاس‌ها (از طریق class_subject_times)
     * دریافت تمام کلاس‌هایی که این درس در آنها تدریس می‌شود
     */
    public function classes()
    {
        return $this->belongsToMany(
            Classes::class,
            'class_subject_times',
            'subject_id',
            'class_id'
        )->distinct();
    }

    /**
     * ارتباط با معلمان (از طریق class_subject_times)
     * دریافت تمام معلمانی که این درس را تدریس می‌کنند
     */
    public function teachers()
    {
        return $this->belongsToMany(
            User::class,
            'class_subject_times',
            'subject_id',
            'teacher_id'
        )->distinct();
    }

    /**
     * ارتباط با تسک‌ها (از طریق task_evaluation_criteria)
     * دریافت تمام تسک‌هایی که شامل این درس هستند
     * توجه: اگر تسک به یک درس خاص وابسته است
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'subject_id');
    }

    /**
     * ارتباط با نتایج تسک‌ها (از طریق task_results و class_subject_times)
     * دریافت تمام نتایج ثبت شده برای این درس
     */
    public function taskResults()
    {
        return $this->hasManyThrough(
            TaskResults::class,
            ClassSubjectTime::class,
            'subject_id',     // کلید خارجی در class_subject_times
            'task_occurrence_id', // کلید خارجی در task_results (غیرمستقیم)
            'id',             // کلید محلی در subjects
            'id'              // کلید محلی در class_subject_times
        );
    }
    
        // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت URL تصویر درس
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/subjects/' . $this->image);
        }

        // تصویر پیش‌فرض بر اساس نام درس
        $defaultImages = [
            'ریاضی' => 'math.png',
            'علوم' => 'science.png',
            'فارسی' => 'persian.png',
            'قرآن' => 'quran.png',
            'هنر' => 'art.png',
            'ورزش' => 'sport.png',
        ];

        $imageName = $defaultImages[$this->name] ?? 'default-subject.png';
        return asset('images/subjects/' . $imageName);
    }

    /**
     * دریافت تعداد کلاس‌هایی که این درس در آنها تدریس می‌شود
     */
    public function getClassesCountAttribute()
    {
        return $this->classes()->count();
    }

    /**
     * دریافت تعداد معلمانی که این درس را تدریس می‌کنند
     */
    public function getTeachersCountAttribute()
    {
        return $this->teachers()->count();
    }

    /**
     * دریافت برنامه هفتگی این درس در یک کلاس خاص
     */
    public function getScheduleInClass($classId)
    {
        return $this->classSubjectTimes()
            ->where('class_id', $classId)
            ->with(['class', 'teacher'])
            ->orderByRaw("FIELD(day_of_week, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday')")
            ->orderBy('start_time')
            ->get();
    }

    /**
     * دریافت تمام برنامه‌های هفتگی این درس (در تمام کلاس‌ها)
     */
    public function getAllSchedules()
    {
        return $this->classSubjectTimes()
            ->with(['class.grade', 'teacher'])
            ->orderBy('class_id')
            ->orderByRaw("FIELD(day_of_week, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday')")
            ->orderBy('start_time')
            ->get()
            ->groupBy('class_id');
    }

  

    /**
     * بررسی اینکه آیا این درس در پایه خاصی تدریس می‌شود
     */
    public function isTaughtInGrade($gradeId)
    {
        return $this->classes()
            ->whereHas('grade', function ($q) use ($gradeId) {
                $q->where('id', $gradeId);
            })
            ->exists();
    }

    /**
     * دریافت تمام پایه‌هایی که این درس در آنها تدریس می‌شود
     */
    public function getGradesTaughtAttribute()
    {
        return Grade::whereHas('classes', function ($q) {
            $q->whereHas('classSubjectTimes', function ($sub) {
                $sub->where('subject_id', $this->id);
            });
        })->get();
    }
    
        // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ جستجو بر اساس نام درس
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'like', "%{$searchTerm}%");
    }

    /**
     * اسکوپ دروسی که در یک پایه خاص تدریس می‌شوند
     */
    public function scopeTaughtInGrade($query, $gradeId)
    {
        return $query->whereHas('classes', function ($q) use ($gradeId) {
            $q->where('grade_id', $gradeId);
        });
    }

    /**
     * اسکوپ دروسی که توسط یک معلم خاص تدریس می‌شوند
     */
    public function scopeTaughtByTeacher($query, $teacherId)
    {
        return $query->whereHas('teachers', function ($q) use ($teacherId) {
            $q->where('users.id', $teacherId);
        });
    }

    /**
     * اسکوپ مرتب‌سازی بر اساس نام
     */
    public function scopeOrderByName($query, $direction = 'asc')
    {
        return $query->orderBy('name', $direction);
    }

    /**
     * اسکوپ دروسی که در کلاس خاصی تدریس می‌شوند
     */
    public function scopeTaughtInClass($query, $classId)
    {
        return $query->whereHas('classes', function ($q) use ($classId) {
            $q->where('classes.id', $classId);
        });
    }
}
