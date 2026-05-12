<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Subject\Models\Subject;
use Modules\Task\Models\TaskAssignment;
use Modules\Users\Models\User;

class ClassSubjectTime extends Model
{

    use HasFactory;

    protected $table = 'class_subject_times';

    protected $fillable = [
        'class_id',
        'teacher_id',
        'subject_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با کلاس
     * هر رکورد متعلق به یک کلاس است
     */
    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * ارتباط با معلم (User)
     * هر رکورد متعلق به یک معلم است
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * ارتباط با درس
     * هر رکورد متعلق به یک درس است
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * ارتباط با تسک‌های اختصاص داده شده
     * از طریق کلاس و معلم می‌توان تسک‌های مربوطه را یافت
     */
    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class, 'class_id', 'class_id')
                    ->where('teacher_id', $this->teacher_id);
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت بازه زمانی به صورت رشته
     * مثال: "شنبه 08:00 - 09:30"
     */
    public function getTimeRangeAttribute()
    {
        $days = [
            'Saturday' => 'شنبه',
            'Sunday' => 'یکشنبه',
            'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه',
            'Wednesday' => 'چهارشنبه',
            'Thursday' => 'پنجشنبه',
        ];

        $dayName = $days[$this->day_of_week] ?? $this->day_of_week;
        
        return $dayName . ' ' . substr($this->start_time, 0, 5) . ' - ' . substr($this->end_time, 0, 5);
    }

    /**
     * بررسی همپوشانی زمانی با یک جلسه دیگر
     * برای اعتبارسنجی تداخل کلاس‌های معلم
     */
    public function overlapsWith($classId, $dayOfWeek, $startTime, $endTime)
    {
        if ($this->day_of_week !== $dayOfWeek) {
            return false;
        }

        // بررسی تداخل زمانی
        $thisStart = strtotime($this->start_time);
        $thisEnd = strtotime($this->end_time);
        $newStart = strtotime($startTime);
        $newEnd = strtotime($endTime);

        return ($newStart < $thisEnd && $newEnd > $thisStart);
    }

    /**
     * دریافت عنوان کامل جلسه
     * مثال: "ریاضی - کلاس سوم الف - شنبه 08:00"
     */
    public function getFullTitleAttribute()
    {
        return $this->subject->name . ' - ' . 
               $this->class->full_name . ' - ' . 
               $this->time_range;
    }

    /**
     * آیا این جلسه در حال حاضر فعال است؟
     * (بر اساس زمان فعلی و روز هفته)
     */
    public function isNowActive()
    {
        $currentDay = now()->format('l'); // Saturday, Sunday, ...
        $currentTime = now()->format('H:i:s');

        if ($currentDay !== $this->day_of_week) {
            return false;
        }

        return ($currentTime >= $this->start_time && $currentTime <= $this->end_time);
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ برای فیلتر بر اساس معلم
     */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * اسکوپ برای فیلتر بر اساس کلاس
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * اسکوپ برای فیلتر بر اساس روز هفته
     */
    public function scopeOnDay($query, $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    /**
     * اسکوپ برای جلسات فعال در حال حاضر
     */
    public function scopeActiveNow($query)
    {
        $currentDay = now()->format('l');
        $currentTime = now()->format('H:i:s');

        return $query->where('day_of_week', $currentDay)
                     ->where('start_time', '<=', $currentTime)
                     ->where('end_time', '>=', $currentTime);
    }
}