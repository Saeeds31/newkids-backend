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

    // ============ ثابت‌های نقش‌ها (Roles) ============
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_SUPERVISOR = 'supervisor';
    const ROLE_TEACHER = 'teacher';
    const ROLE_PARENT = 'parent';

    const ROLES = [
        self::ROLE_ADMIN => 'ادمین',
        self::ROLE_MANAGER => 'مدیر',
        self::ROLE_SUPERVISOR => 'ناظم',
        self::ROLE_TEACHER => 'معلم',
        self::ROLE_PARENT => 'والد',
    ];

    const ROLE_COLORS = [
        self::ROLE_ADMIN => '#EF4444',
        self::ROLE_MANAGER => '#F59E0B',
        self::ROLE_SUPERVISOR => '#8B5CF6',
        self::ROLE_TEACHER => '#3B82F6',
        self::ROLE_PARENT => '#10B981',
    ];

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

    // ============ روابط (Relationships) ============

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

    public function toggleActive()
    {
        $this->is_active = !$this->is_active;
        return $this->save();
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function hasPermission($permission)
    {
        return $this->permissions()->contains('name', $permission);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole($roleName): bool
    {
        return $this->roles()->where('slug', $roleName)->exists();
    }

    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class, 'teacher_id');
    }

    public function classSubjectTimes()
    {
        return $this->hasMany(ClassSubjectTime::class, 'teacher_id');
    }

    public function teachingClasses()
    {
        return $this->belongsToMany(
            Classes::class,
            'class_subject_times',
            'teacher_id',
            'class_id'
        )->distinct();
    }

    public function teachingSubjects()
    {
        return $this->belongsToMany(
            Subject::class,
            'class_subject_times',
            'teacher_id',
            'subject_id'
        )->distinct();
    }

    public function children()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    public function recordedTaskResults()
    {
        return $this->hasMany(TaskResults::class, 'recorded_by');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'from_user_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'to_user_id');
    }

    public function assignedTaskAssignments()
    {
        return $this->hasMany(ModelsTaskAssignment::class, 'assigned_by');
    }

    // ============ متدهای کمکی (Helpers) ============

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * دریافت نقش کاربر (از جدول roles یا فیلد role)
     * اگر از سیستم نقش‌های جداگانه استفاده می‌کنید، این متد را اصلاح کنید
     */
    public function getRoleAttribute()
    {
        // اگر فیلد role در جدول users وجود دارد
        if (isset($this->attributes['role'])) {
            return $this->attributes['role'];
        }

        // اگر از جدول roles استفاده می‌کنید
        $role = $this->roles()->first();
        return $role ? $role->slug : self::ROLE_TEACHER;
    }

    public function getRolePersianAttribute()
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function getRoleColorAttribute()
    {
        return self::ROLE_COLORS[$this->role] ?? '#6B7280';
    }

    public function getIsAdminAttribute()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getIsManagerAttribute()
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function getIsSupervisorAttribute()
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function getIsTeacherAttribute()
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function getIsParentAttribute()
    {
        return $this->role === self::ROLE_PARENT;
    }

    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Classes::class,
            'teacher_id',
            'class_id',
            'id',
            'id'
        );
    }

    public function classes()
    {
        return $this->hasMany(Classes::class, 'teacher_id');
    }

    public function getIsActiveUserAttribute()
    {
        return $this->is_active && is_null($this->deleted_at);
    }

    public function getRecordedStudentsCountAttribute()
    {
        return $this->recordedTaskResults()
            ->distinct('student_id')
            ->count('student_id');
    }

    public function getTeachingClassesCountAttribute()
    {
        return $this->teachingClasses()->count();
    }

    public function getChildrenCountAttribute()
    {
        return $this->children()->count();
    }

    public function getUnreadMessagesCountAttribute()
    {
        return $this->receivedMessages()
            ->where('is_read', false)
            ->count();
    }

    public function getTeachingClassesWithDetails()
    {
        return $this->classSubjectTimes()
            ->with(['class.grade', 'subject'])
            ->orderByRaw("FIELD(day_of_week, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday')")
            ->orderBy('start_time')
            ->get()
            ->groupBy('class_id');
    }

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
     * توجه: اگر فیلد role در جدول users ندارید، این متد را اصلاح کنید
     */
    public function scopeWithRole($query, $role)
    {
        return $query->whereHas('roles', function ($q) use ($role) {
            $q->where('slug', $role);
        });
    }
    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeManagers($query)
    {
        return $query->where('role', self::ROLE_MANAGER);
    }

    public function scopeSupervisors($query)
    {
        return $query->where('role', self::ROLE_SUPERVISOR);
    }

    public function scopeTeachers($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('slug', self::ROLE_TEACHER);
        });
    }
   public function scopeParents($query)
{
    return $query->whereHas('roles', function($q) {
        $q->where('slug', self::ROLE_PARENT);
    });
}

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('first_name', 'like', "%{$searchTerm}%")
                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                ->orWhere('mobile', 'like', "%{$searchTerm}%");
        });
    }

    public function scopeTeachersInClass($query, $classId)
    {
        return $query->teachers()
            ->whereHas('classSubjectTimes', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
    }

    public function scopeParentsOfStudent($query, $studentId)
    {
        return $query->parents()
            ->whereHas('children', function ($q) use ($studentId) {
                $q->where('id', $studentId);
            });
    }

    public function scopeOrderByName($query, $direction = 'asc')
    {
        return $query->orderBy('first_name', $direction)
            ->orderBy('last_name', $direction);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
