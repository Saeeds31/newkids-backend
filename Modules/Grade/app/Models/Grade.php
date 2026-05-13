<?php

namespace Modules\Grade\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Class\Models\Classes;
use Modules\Student\Models\Student;
use Modules\Users\Models\User;

// use Modules\Grade\Database\Factories\GradeFactory;

class Grade extends Model
{

    use HasFactory;

    protected $table = 'grades';

    protected $fillable = [
        'name',
        'level',
    ];

    protected $casts = [
        'level' => 'integer',
    ];
    
        // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با کلاس‌ها
     * هر پایه چندین کلاس دارد (اول - الف، اول - ب، ...)
     */
    public function classes()
    {
        return $this->hasMany(Classes::class, 'grade_id');
    }

    /**
     * ارتباط با دانش‌آموزان (از طریق کلاس‌ها)
     * دریافت تمام دانش‌آموزان این پایه
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Classes::class,
            'grade_id',    // کلید خارجی در classes
            'class_id',    // کلید خارجی در students
            'id',          // کلید محلی در grades
            'id'           // کلید محلی در classes
        );
    }

    /**
     * ارتباط با معلمانی که در این پایه تدریس می‌کنند (از طریق کلاس‌ها و time slots)
     */
    public function teachers()
    {
        return $this->hasManyThrough(
            User::class,
            Classes::class,
            'grade_id',
            'id',
            'id',
            'id'
        )->whereHas('classSubjectTimes');
    }
    
        // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نام کامل پایه
     * مثال: "پایه سوم ابتدایی"
     */
    public function getFullNameAttribute()
    {
        $gradeNames = [
            1 => 'اول',
            2 => 'دوم',
            3 => 'سوم',
            4 => 'چهارم',
            5 => 'پنجم',
            6 => 'ششم',
        ];

        $name = $gradeNames[$this->level] ?? $this->level;

        return "پایه {$name} ابتدایی";
    }

    /**
     * دریافت تعداد کل دانش‌آموزان این پایه
     */
    public function getTotalStudentsCountAttribute()
    {
        return $this->students()->count();
    }

    /**
     * دریافت تعداد کل کلاس‌های این پایه
     */
    public function getClassesCountAttribute()
    {
        return $this->classes()->count();
    }

    /**
     * دریافت میانگین نمرات دانش‌آموزان این پایه (برای یک ترم مشخص)
     */
    public function getAverageScore($term, $academicYear)
    {
        $students = $this->students()
            ->whereHas('overallStatuses', function ($q) use ($term, $academicYear) {
                $q->where('term', $term)
                    ->where('academic_year', $academicYear);
            })
            ->with('overallStatuses')
            ->get();

        if ($students->isEmpty()) {
            return 0;
        }

        $average = $students->avg(function ($student) use ($term, $academicYear) {
            $status = $student->overallStatuses
                ->where('term', $term)
                ->where('academic_year', $academicYear)
                ->first();

            return $status ? $status->total_score_percentage : 0;
        });

        return round($average, 2);
    }

    /**
     * توزیع وضعیت دانش‌آموزان (Excellent, Good, ...)
     */
    public function getStatusDistribution($term, $academicYear)
    {
        $students = $this->students()
            ->whereHas('overallStatuses', function ($q) use ($term, $academicYear) {
                $q->where('term', $term)->where('academic_year', $academicYear);
            })
            ->with('overallStatuses')
            ->get();

        $distribution = [
            'Excellent' => 0,
            'Good' => 0,
            'Average' => 0,
            'Needs Improvement' => 0,
            'Poor' => 0,
        ];

        foreach ($students as $student) {
            $status = $student->overallStatuses
                ->where('term', $term)
                ->where('academic_year', $academicYear)
                ->first();

            if ($status && isset($distribution[$status->status_label])) {
                $distribution[$status->status_label]++;
            }
        }

        return $distribution;
    }
    
        // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ برای مرتب‌سازی بر اساس سطح پایه (از اول تا ششم)
     */
    public function scopeOrderByLevel($query, $direction = 'asc')
    {
        return $query->orderBy('level', $direction);
    }

    /**
     * اسکوپ برای گرفتن پایه‌های بالاتر از سطح مشخص
     */
    public function scopeAboveLevel($query, $level)
    {
        return $query->where('level', '>', $level);
    }

    /**
     * اسکوپ برای گرفتن پایه‌های پایین‌تر از سطح مشخص
     */
    public function scopeBelowLevel($query, $level)
    {
        return $query->where('level', '<', $level);
    }
}
