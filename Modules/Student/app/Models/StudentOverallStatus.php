<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Class\Models\Classes;
use Modules\Grade\Models\Grade;
// use Modules\Student\Database\Factories\StudentOverallStatusFactory;

class StudentOverallStatus extends Model
{
   
        use HasFactory;
    
        protected $table = 'student_overall_statuses';
    
        protected $fillable = [
            'student_id',
            'term',
            'academic_year',
            'total_score_percentage',
            'status_label',
            'category_breakdown',
            'trait_scores',
            'skill_scores',
            'calculated_at',
        ];
    
        protected $casts = [
            'term' => 'integer',
            'academic_year' => 'integer',
            'total_score_percentage' => 'decimal:2',
            'category_breakdown' => 'array',
            'trait_scores' => 'array',
            'skill_scores' => 'array',
            'calculated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    
        // ============ ثابت‌ها (Constants) ============
    
        const TERM_FIRST = 1;
        const TERM_SECOND = 2;
    
        const STATUS_EXCELLENT = 'Excellent';
        const STATUS_GOOD = 'Good';
        const STATUS_AVERAGE = 'Average';
        const STATUS_NEEDS_IMPROVEMENT = 'Needs Improvement';
        const STATUS_POOR = 'Poor';
    
        const STATUS_RANGES = [
            self::STATUS_EXCELLENT => ['min' => 90, 'max' => 100],
            self::STATUS_GOOD => ['min' => 75, 'max' => 89],
            self::STATUS_AVERAGE => ['min' => 60, 'max' => 74],
            self::STATUS_NEEDS_IMPROVEMENT => ['min' => 40, 'max' => 59],
            self::STATUS_POOR => ['min' => 0, 'max' => 39],
        ];
    
        const STATUS_LABELS_PERSIAN = [
            self::STATUS_EXCELLENT => 'عالی',
            self::STATUS_GOOD => 'خوب',
            self::STATUS_AVERAGE => 'متوسط',
            self::STATUS_NEEDS_IMPROVEMENT => 'نیاز به تلاش بیشتر',
            self::STATUS_POOR => 'ضعیف',
        ];
    
        const STATUS_COLORS = [
            self::STATUS_EXCELLENT => '#10B981', // سبز
            self::STATUS_GOOD => '#3B82F6',     // آبی
            self::STATUS_AVERAGE => '#F59E0B',  // نارنجی
            self::STATUS_NEEDS_IMPROVEMENT => '#EF4444', // قرمز
            self::STATUS_POOR => '#6B7280',     // خاکستری
        ];
    
        // ============ ارتباطات (Relationships) ============
    
        /**
         * ارتباط با دانش‌آموز
         * هر رکورد وضعیت متعلق به یک دانش‌آموز است
         */
        public function student()
        {
            return $this->belongsTo(Student::class, 'student_id');
        }
    
        /**
         * ارتباط با کلاس (از طریق دانش‌آموز)
         */
        public function class()
        {
            return $this->hasOneThrough(
                Classes::class,
                Student::class,
                'id',          // کلید خارجی در students
                'id',          // کلید محلی در classes
                'student_id',  // کلید محلی در student_overall_statuses
                'class_id'     // کلید خارجی در students
            );
        }
    
        /**
         * ارتباط با پایه تحصیلی (از طریق دانش‌آموز و کلاس)
         */
        public function grade()
        {
            return $this->hasOneThrough(
                Grade::class,
                Student::class,
                'id',
                'id',
                'student_id',
                'class_id'
            )->via('class');
        }
    
        // ============ متدهای کمکی (Helpers) ============
    
        /**
         * دریافت وضعیت به صورت فارسی
         */
        public function getStatusLabelPersianAttribute()
        {
            return self::STATUS_LABELS_PERSIAN[$this->status_label] ?? $this->status_label;
        }
    
        /**
         * دریافت رنگ وضعیت
         */
        public function getStatusColorAttribute()
        {
            return self::STATUS_COLORS[$this->status_label] ?? '#6B7280';
        }
    
        /**
         * دریافت رتبه عددی وضعیت (برای مرتب‌سازی)
         */
        public function getStatusRankAttribute()
        {
            $ranks = [
                self::STATUS_EXCELLENT => 5,
                self::STATUS_GOOD => 4,
                self::STATUS_AVERAGE => 3,
                self::STATUS_NEEDS_IMPROVEMENT => 2,
                self::STATUS_POOR => 1,
            ];
            
            return $ranks[$this->status_label] ?? 0;
        }
    
        /**
         * دریافت نمره یک دسته خاص (category)
         * دسته‌ها: individual, social, cognitive, behavioral
         */
        public function getCategoryScore($category)
        {
            if (!$this->category_breakdown || !isset($this->category_breakdown[$category])) {
                return 0;
            }
            
            return $this->category_breakdown[$category]['score'] ?? 0;
        }
    
        /**
         * دریافت نمره یک ویژگی خاص
         */
        public function getTraitScore($traitKey)
        {
            if (!$this->trait_scores || !isset($this->trait_scores[$traitKey])) {
                return 0;
            }
            
            return $this->trait_scores[$traitKey]['score'] ?? 0;
        }
    
        /**
         * دریافت نمره یک مهارت خاص
         */
        public function getSkillScore($skillKey)
        {
            if (!$this->skill_scores || !isset($this->skill_scores[$skillKey])) {
                return 0;
            }
            
            return $this->skill_scores[$skillKey]['score'] ?? 0;
        }
    
        /**
         * دریافت نام کامل ترم
         * مثال: "ترم اول ۱۴۰۴"
         */
        public function getTermNameAttribute()
        {
            $termName = $this->term === self::TERM_FIRST ? 'ترم اول' : 'ترم دوم';
            return "{$termName} {$this->academic_year}";
        }
    
        /**
         * محاسبه وضعیت بر اساس درصد
         */
        public static function calculateStatusByPercentage($percentage)
        {
            foreach (self::STATUS_RANGES as $status => $range) {
                if ($percentage >= $range['min'] && $percentage <= $range['max']) {
                    return $status;
                }
            }
            
            return self::STATUS_POOR;
        }
    
        /**
         * دریافت رکورد قبلی (ترم قبل) برای این دانش‌آموز
         */
        public function getPreviousTermStatus()
        {
            $previousTerm = $this->term === self::TERM_FIRST 
                ? self::TERM_SECOND 
                : self::TERM_FIRST;
            
            $previousYear = $this->term === self::TERM_FIRST 
                ? $this->academic_year - 1 
                : $this->academic_year;
            
            return self::where('student_id', $this->student_id)
                ->where('term', $previousTerm)
                ->where('academic_year', $previousYear)
                ->first();
        }
    
        /**
         * محاسبه تغییرات نسبت به ترم قبل
         */
        public function getProgressSinceLastTerm()
        {
            $previous = $this->getPreviousTermStatus();
            
            if (!$previous) {
                return null;
            }
            
            $scoreChange = $this->total_score_percentage - $previous->total_score_percentage;
            $rankChange = $this->status_rank - $previous->status_rank;
            
            return [
                'score_change' => round($scoreChange, 2),
                'rank_change' => $rankChange,
                'improved' => $scoreChange > 0,
                'declined' => $scoreChange < 0,
                'stable' => $scoreChange == 0,
            ];
        }
    
        /**
         * دریافت رتبه دانش‌آموز در کلاس خود
         */
        public function getRankInClass()
        {
            $classStudents = self::where('academic_year', $this->academic_year)
                ->where('term', $this->term)
                ->whereHas('student', function($q) {
                    $q->where('class_id', $this->student->class_id);
                })
                ->orderBy('total_score_percentage', 'desc')
                ->get();
            
            $rank = 1;
            foreach ($classStudents as $index => $status) {
                if ($status->student_id == $this->student_id) {
                    $rank = $index + 1;
                    break;
                }
            }
            
            return [
                'rank' => $rank,
                'total' => $classStudents->count(),
            ];
        }
    
        /**
         * دریافت رتبه دانش‌آموز در پایه خود
         */
        public function getRankInGrade()
        {
            $gradeStudents = self::where('academic_year', $this->academic_year)
                ->where('term', $this->term)
                ->whereHas('student.class', function($q) {
                    $q->where('grade_id', $this->student->class->grade_id);
                })
                ->orderBy('total_score_percentage', 'desc')
                ->get();
            
            $rank = 1;
            foreach ($gradeStudents as $index => $status) {
                if ($status->student_id == $this->student_id) {
                    $rank = $index + 1;
                    break;
                }
            }
            
            return [
                'rank' => $rank,
                'total' => $gradeStudents->count(),
            ];
        }
    
        /**
         * بررسی آیا دانش‌آموز پیشرفت داشته است
         */
        public function getHasImprovedAttribute()
        {
            $progress = $this->getProgressSinceLastTerm();
            return $progress ? $progress['improved'] : false;
        }
    
        // ============ متدهای استاتیک (Static Methods) ============
    
        /**
         * ایجاد یا به‌روزرسانی وضعیت کلی دانش‌آموز
         */
        public static function updateOrCreateForStudent($studentId, $term, $academicYear, $data)
        {
            $percentage = $data['total_score_percentage'] ?? 0;
            $statusLabel = $data['status_label'] ?? self::calculateStatusByPercentage($percentage);
            
            return self::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'term' => $term,
                    'academic_year' => $academicYear,
                ],
                [
                    'total_score_percentage' => $percentage,
                    'status_label' => $statusLabel,
                    'category_breakdown' => $data['category_breakdown'] ?? [],
                    'trait_scores' => $data['trait_scores'] ?? [],
                    'skill_scores' => $data['skill_scores'] ?? [],
                    'calculated_at' => now(),
                ]
            );
        }
    
        // ============ اسکوپ‌ها (Scopes) ============
    
        /**
         * اسکوپ فیلتر بر اساس ترم
         */
        public function scopeForTerm($query, $term)
        {
            return $query->where('term', $term);
        }
    
        /**
         * اسکوپ فیلتر بر اساس سال تحصیلی
         */
        public function scopeForYear($query, $academicYear)
        {
            return $query->where('academic_year', $academicYear);
        }
    
        /**
         * اسکوپ فیلتر بر اساس وضعیت
         */
        public function scopeWithStatus($query, $statusLabel)
        {
            return $query->where('status_label', $statusLabel);
        }
    
        /**
         * اسکوپ فیلتر بر اساس بازه درصدی
         */
        public function scopeScoreBetween($query, $min, $max)
        {
            return $query->whereBetween('total_score_percentage', [$min, $max]);
        }
    
        /**
         * اسکوپ دانش‌آموزانی که پیشرفت داشته‌اند (نسبت به ترم قبل)
         * نیاز به ساب‌کوئری دارد
         */
        public function scopeImproved($query)
        {
            return $query->whereRaw('total_score_percentage > (
                SELECT total_score_percentage 
                FROM student_overall_statuses as prev 
                WHERE prev.student_id = student_overall_statuses.student_id 
                AND prev.term < student_overall_statuses.term
                ORDER BY prev.term DESC 
                LIMIT 1
            )');
        }
    
        /**
         * اسکوپ مرتب‌سازی بر اساس درصد (نزولی)
         */
        public function scopeOrderByScore($query, $direction = 'desc')
        {
            return $query->orderBy('total_score_percentage', $direction);
        }
    
        /**
         * اسکوپ مرتب‌سازی بر اساس رتبه وضعیت
         */
        public function scopeOrderByStatusRank($query, $direction = 'desc')
        {
            return $query->orderByRaw("FIELD(status_label, 'Excellent', 'Good', 'Average', 'Needs Improvement', 'Poor') {$direction}");
        }
    }