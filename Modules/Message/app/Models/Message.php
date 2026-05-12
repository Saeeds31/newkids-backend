<?php

namespace Modules\Message\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Student\Models\Student;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskResults;
use Modules\Users\Models\User;

// use Modules\Message\Database\Factories\MessageFactory;

class Message extends Model
{

    use HasFactory;

    protected $table = 'messages';

    protected $fillable = [
        'task_result_id',
        'from_user_id',
        'to_user_id',
        'message',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // ============ ارتباطات (Relationships) ============

    /**
     * ارتباط با نتیجه تسک
     * هر پیام مربوط به یک نتیجه تسک خاص است
     */
    public function taskResult()
    {
        return $this->belongsTo(TaskResults::class, 'task_result_id');
    }

    /**
     * ارتباط با فرستنده پیام
     */
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * ارتباط با گیرنده پیام
     */
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * ارتباط با دانش‌آموز (از طریق task_result)
     * دریافت دانش‌آموزی که پیام درباره اوست
     */
    public function student()
    {
        return $this->hasOneThrough(
            Student::class,
            TaskResults::class,
            'id',          // کلید خارجی در task_results
            'id',          // کلید محلی در students
            'task_result_id', // کلید محلی در messages
            'student_id'   // کلید خارجی در task_results
        );
    }

    /**
     * ارتباط با تسک (از طریق task_result)
     */
    public function task()
    {
        return $this->hasOneThrough(
            Task::class,
            TaskResults::class,
            'id',
            'id',
            'task_result_id',
            'task_occurrence_id'
        )->via('taskResult');
    }

    // ============ متدهای کمکی (Helpers) ============

    /**
     * مشخص کردن اینکه آیا پیام متعلق به کاربر خاصی است
     */
    public function belongsToUser($userId)
    {
        return ($this->from_user_id === $userId || $this->to_user_id === $userId);
    }

    /**
     * دریافت طرف مقابل در مکالمه
     */
    public function getOtherUser($currentUserId)
    {
        if ($this->from_user_id === $currentUserId) {
            return $this->toUser;
        }

        if ($this->to_user_id === $currentUserId) {
            return $this->fromUser;
        }

        return null;
    }

    /**
     * علامت‌گذاری پیام به عنوان خوانده شده
     */
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * بررسی آیا پیام خوانده شده است
     */
    public function getIsReadAttribute($value)
    {
        return (bool) $value;
    }

    /**
     * دریافت وضعیت خوانده شده به صورت فارسی
     */
    public function getReadStatusPersianAttribute()
    {
        return $this->is_read ? 'خوانده شده' : 'خوانده نشده';
    }

    /**
     * آیا این پیام توسط والد فرستاده شده است؟
     */
    public function isFromParent()
    {
        return $this->fromUser && $this->fromUser->role === 'parent';
    }

    /**
     * آیا این پیام توسط معلم فرستاده شده است؟
     */
    public function isFromTeacher()
    {
        return $this->fromUser && $this->fromUser->role === 'teacher';
    }

    /**
     * دریافت عنوان مکالمه (برای نمایش در لیست پیام‌ها)
     */
    public function getConversationTitleAttribute()
    {
        $student = $this->student;
        $task = $this->task;

        if ($student && $task) {
            return "{$student->first_name} {$student->last_name} - {$task->title}";
        }

        return 'پیام بدون عنوان';
    }

    /**
     * دریافت لینک مشاهده نتیجه مرتبط
     */
    public function getResultLinkAttribute()
    {
        return route('task-results.show', $this->task_result_id);
    }

    // ============ اسکوپ‌ها (Scopes) ============

    /**
     * اسکوپ پیام‌های خوانده نشده برای یک کاربر
     */
    public function scopeUnreadForUser($query, $userId)
    {
        return $query->where('to_user_id', $userId)
            ->where('is_read', false);
    }

    /**
     * اسکوپ پیام‌های یک مکالمه خاص (بین دو کاربر درباره یک task_result)
     */
    public function scopeConversation($query, $userId1, $userId2, $taskResultId)
    {
        return $query->where('task_result_id', $taskResultId)
            ->where(function ($q) use ($userId1, $userId2) {
                $q->where(function ($sub) use ($userId1, $userId2) {
                    $sub->where('from_user_id', $userId1)
                        ->where('to_user_id', $userId2);
                })->orWhere(function ($sub) use ($userId1, $userId2) {
                    $sub->where('from_user_id', $userId2)
                        ->where('to_user_id', $userId1);
                });
            });
    }

    /**
     * اسکوپ دریافت آخرین پیام هر مکالمه (برای نمایش در لیست)
     */
    public function scopeLatestPerConversation($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('from_user_id', $userId)
                ->orWhere('to_user_id', $userId);
        })
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique(function ($message) use ($userId) {
                // ساخت کلید یکتا برای هر مکالمه
                $otherId = $message->getOtherUser($userId)->id ?? null;
                return $message->task_result_id . '-' . min($userId, $otherId) . '-' . max($userId, $otherId);
            });
    }

    /**
     * اسکوپ پیام‌های ارسال شده به یک معلم خاص
     */
    public function scopeToTeacher($query, $teacherId)
    {
        return $query->where('to_user_id', $teacherId)
            ->whereHas('toUser', function ($q) {
                $q->where('role', 'teacher');
            });
    }

    /**
     * اسکوپ پیام‌های ارسال شده به یک والد خاص
     */
    public function scopeToParent($query, $parentId)
    {
        return $query->where('to_user_id', $parentId)
            ->whereHas('toUser', function ($q) {
                $q->where('role', 'parent');
            });
    }

    /**
     * اسکوپ پیام‌های مربوط به یک دانش‌آموز خاص
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->whereHas('taskResult', function ($q) use ($studentId) {
            $q->where('student_id', $studentId);
        });
    }
}
