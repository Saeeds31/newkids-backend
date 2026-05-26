<?php

namespace Modules\Users\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Class\Models\Classes;
use Modules\Class\Models\ClassSubjectTime;
use Modules\Message\Models\Message;
use Modules\Student\Models\Student;
use Modules\Subject\Models\Subject;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskAssignment;
use Modules\Task\Models\TaskAssignment as ModelsTaskAssignment;
use Modules\Task\Models\TaskResults;
use Modules\Wallet\Models\Wallet;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'avatar',
        'first_name',
        'last_name',
        'mobile',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    public function getPermissionsAttribute()
    {
        return $this->roles
            ->map->permissions
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();
    }
    public function wallet(){
        return $this->hasOne(Wallet::class);

    }
    public function hasPermission($permission)
    {
        return $this->permissions()->contains('name', $permission);
    }

    /**
     * ارتباط با نقش ایجاد شده
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }


    // متد کمکی برای بررسی نقش
    public function hasRole($roleName): bool
    {
        return $this->roles()->where('slug', $roleName)->exists();
    }

    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * ارتباط با انتساب‌های تسک (برای معلم)
     * تسک‌هایی که به این معلم اختصاص داده شده
     */
    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class, 'teacher_id');
    }

   
    public function classSubjectTimes()
    {
        return $this->hasMany(ClassSubjectTime::class, 'teacher_id');
    }

    /**
     * ارتباط با کلاس‌هایی که معلم در آنها تدریس می‌کند
     */
    public function teachingClasses()
    {
        return $this->belongsToMany(
            Classes::class,
            'class_subject_times',
            'teacher_id',
            'class_id'
        )->distinct();
    }

    /**
     * ارتباط با درس‌هایی که معلم تدریس می‌کند
     */
    public function teachingSubjects()
    {
        return $this->belongsToMany(
            Subject::class,
            'class_subject_times',
            'teacher_id',
            'subject_id'
        )->distinct();
    }

    /**
     * ارتباط با دانش‌آموزان (برای والد)
     */
    public function children()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    /**
     * ارتباط با نتایج ثبت شده (برای معلم)
     */
    public function recordedTaskResults()
    {
        return $this->hasMany(TaskResults::class, 'recorded_by');
    }

    /**
     * ارتباط با پیام‌های ارسال شده
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'from_user_id');
    }

    /**
     * ارتباط با پیام‌های دریافت شده
     */
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'to_user_id');
    }

    /**
     * ارتباط با انتساب‌های انجام شده (برای مدیر/ناظم)
     */
    public function assignedTaskAssignments()
    {
        return $this->hasMany(ModelsTaskAssignment::class, 'assigned_by');
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * دریافت نام کامل کاربر
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * دریافت نقش کاربر (از جدول roles یا فیلد role)
     * توجه: اگر از جدول roles جداگانه استفاده می‌کنید، این متد را اصلاح کنید
     */
    public function getRoleAttribute()
    {
        // این یک متد ساده است. اگر سیستم نقش‌های پیچیده‌تری دارید، 
        // از spatie/laravel-permission استفاده کنید
        return $this->role ?? self::ROLE_TEACHER;
    }

    /**
     * دریافت نقش به صورت فارسی
     */
    public function getRolePersianAttribute()
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /**
     * دریافت رنگ نقش
     */
    public function getRoleColorAttribute()
    {
        return self::ROLE_COLORS[$this->role] ?? '#6B7280';
    }

    /**
     * بررسی آیا کاربر مدیر است
     */
    public function getIsAdminAttribute()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * بررسی آیا کاربر ناظم است
     */
    public function getIsSupervisorAttribute()
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    /**
     * بررسی آیا کاربر معلم است
     */
    public function getIsTeacherAttribute()
    {
        return $this->role === self::ROLE_TEACHER;
    }

    /**
     * بررسی آیا کاربر والد است
     */
    public function getIsParentAttribute()
    {
        return $this->role === self::ROLE_PARENT;
    }

    // دانش‌آموزانی که این معلم تدریس می‌کند (از طریق کلاس‌ها)
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Classes::class,
            'teacher_id', // foreign key on classes table
            'class_id',   // foreign key on students table
            'id',         // local key on users table
            'id'          // local key on classes table
        );
    }

    // کلاس‌هایی که این معلم تدریس می‌کند
    public function classes()
    {
        return $this->hasMany(Classes::class, 'teacher_id');
    }

    // وظایف محول شده به این معلم

    /**
     * بررسی آیا کاربر فعال است
     */

    public function getIsActiveUserAttribute()
    {
        return $this->is_active && is_null($this->deleted_at);
    }

    /**
     * دریافت تعداد دانش‌آموزانی که معلم برای آنها نتیجه ثبت کرده
     */
    public function getRecordedStudentsCountAttribute()
    {
        return $this->recordedTaskResults()
            ->distinct('student_id')
            ->count('student_id');
    }

    /**
     * دریافت تعداد کلاس‌هایی که معلم در آنها تدریس می‌کند
     */
    public function getTeachingClassesCountAttribute()
    {
        return $this->teachingClasses()->count();
    }

    /**
     * دریافت تعداد فرزندان (برای والد)
     */
    public function getChildrenCountAttribute()
    {
        return $this->children()->count();
    }

    /**
     * دریافت تعداد پیام‌های خوانده نشده
     */
    public function getUnreadMessagesCountAttribute()
    {
        return $this->receivedMessages()
            ->where('is_read', false)
            ->count();
    }

    /**
     * دریافت لیست کلاس‌هایی که معلم در آنها تدریس می‌کند (به همراه جزئیات)
     */
    public function getTeachingClassesWithDetails()
    {
        return $this->classSubjectTimes()
            ->with(['class.grade', 'subject'])
            ->orderByRaw("FIELD(day_of_week, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday')")
            ->orderBy('start_time')
            ->get()
            ->groupBy('class_id');
    }



    /**
     * دریافت فرزندان یک والد به همراه نتایج اخیر
     */
    public function getChildrenWithRecentResults($limit = 5)
    {
        return $this->children()
            ->with(['class', 'taskResults' => function ($q) use ($limit) {
                $q->with(['taskOccurrence.taskAssignment.task', 'status'])
                    ->latest()
                    ->limit($limit);
            }])
            ->get();
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ کاربران بر اساس نقش
     */
    public function scopeWithRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * اسکوپ مدیران
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    /**
     * اسکوپ ناظمان
     */
    public function scopeSupervisors($query)
    {
        return $query->where('role', self::ROLE_SUPERVISOR);
    }

    /**
     * اسکوپ معلمان
     */
    public function scopeTeachers($query)
    {
        return $query->where('role', self::ROLE_TEACHER);
    }

    /**
     * اسکوپ والدین
     */
    public function scopeParents($query)
    {
        return $query->where('role', self::ROLE_PARENT);
    }

    /**
     * اسکوپ کاربران فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * اسکوپ کاربران غیرفعال
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * اسکوپ جستجو بر اساس نام یا موبایل
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('first_name', 'like', "%{$searchTerm}%")
                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                ->orWhere('mobile', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * اسکوپ معلمانی که در یک کلاس خاص تدریس می‌کنند
     */
    public function scopeTeachersInClass($query, $classId)
    {
        return $query->teachers()
            ->whereHas('classSubjectTimes', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
    }

    /**
     * اسکوپ والدین یک دانش‌آموز خاص
     */
    public function scopeParentsOfStudent($query, $studentId)
    {
        return $query->parents()
            ->whereHas('children', function ($q) use ($studentId) {
                $q->where('id', $studentId);
            });
    }

    /**
     * مرتب‌سازی بر اساس نام
     */
    public function scopeOrderByName($query, $direction = 'asc')
    {
        return $query->orderBy('first_name', $direction)
            ->orderBy('last_name', $direction);
    }

    /**
     * مرتب‌سازی بر اساس تاریخ ایجاد (جدیدترین)
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
