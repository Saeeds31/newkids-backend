<?php

namespace Modules\Message\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Message\Models\Message;
use Modules\Task\Models\TaskResults;
use Modules\Student\Models\Student;
use Modules\Users\Models\User;
use Modules\Activity\Services\ActivityLogger;
use Carbon\Carbon;

class ChatController extends Controller
{
    /**
     * دریافت لیست مکالمات برای یک والد
     */
    public function getConversations(Request $request)
    {
        $user = $request->user();
        
        // دریافت همه پیام‌های کاربر
        $messages = Message::where(function($q) use ($user) {
                $q->where('from_user_id', $user->id)
                  ->orWhere('to_user_id', $user->id);
            })
            ->with(['fromUser', 'toUser', 'taskResult.student', 'taskResult.task'])
            ->orderBy('created_at', 'desc')
            ->get();

        // گروه‌بندی بر اساس task_result_id
        $conversations = $messages->groupBy('task_result_id')->map(function($msgs, $taskResultId) use ($user) {
            $lastMessage = $msgs->first();
            $taskResult = $lastMessage->taskResult;
            
            // پیدا کردن طرف مقابل
            $otherUser = $lastMessage->from_user_id == $user->id 
                ? $lastMessage->toUser 
                : $lastMessage->fromUser;

            // تعداد پیام‌های خوانده نشده
            $unreadCount = $msgs->filter(function($msg) use ($user) {
                return $msg->to_user_id == $user->id && !$msg->is_read;
            })->count();

            // دانش‌آموز مربوطه
            $student = $taskResult?->student;
            $task = $taskResult?->task;

            return [
                'task_result_id' => $taskResultId,
                'task_title' => $task?->title ?? 'بدون عنوان',
                'task_color' => $task?->color_code ?? '#6B7280',
                'student' => $student ? [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'avatar' => $student->avatar,
                    'class_name' => $student->class?->full_name ?? 'بدون کلاس',
                ] : null,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->full_name,
                    'avatar' => $otherUser->avatar,
                    'role' => $otherUser->role ?? 'unknown',
                    'role_label' => $this->getRoleLabel($otherUser->role),
                ] : null,
                'last_message' => [
                    'id' => $lastMessage->id,
                    'message' => $lastMessage->message,
                    'from_user_id' => $lastMessage->from_user_id,
                    'is_from_me' => $lastMessage->from_user_id == $user->id,
                    'created_at' => $lastMessage->created_at->toDateTimeString(),
                    'time_ago' => $lastMessage->created_at->diffForHumans(),
                ],
                'unread_count' => $unreadCount,
                'has_unread' => $unreadCount > 0,
                'created_at' => $lastMessage->created_at->toDateTimeString(),
            ];
        })->values();

        // مرتب‌سازی بر اساس تاریخ آخرین پیام و اولویت پیام‌های خوانده نشده
        $sorted = $conversations->sortByDesc(function($conv) {
            return ($conv['has_unread'] ? 1 : 0) . $conv['created_at'];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $sorted
        ]);
    }

    /**
     * دریافت پیام‌های یک مکالمه خاص
     */
    public function getMessages($taskResultId, Request $request)
    {
        $user = $request->user();

        // بررسی دسترسی به این task_result
        $taskResult = TaskResults::with(['student', 'task'])
            ->find($taskResultId);

        if (!$taskResult) {
            return response()->json([
                'success' => false,
                'message' => 'نتیجه تسک مورد نظر یافت نشد'
            ], 404);
        }

        // بررسی دسترسی: کاربر باید والد دانش‌آموز یا معلم ثبت‌کننده باشد
        $student = $taskResult->student;
        $isParent = $student && $student->parent_id == $user->id;
        $isTeacher = $taskResult->recorded_by == $user->id;

        if (!$isParent && !$isTeacher) {
            return response()->json([
                'success' => false,
                'message' => 'شما دسترسی به این مکالمه ندارید'
            ], 403);
        }

        // دریافت پیام‌ها
        $messages = Message::where('task_result_id', $taskResultId)
            ->with(['fromUser', 'toUser'])
            ->orderBy('created_at', 'asc')
            ->get();

        // علامت‌گذاری پیام‌های خوانده نشده به عنوان خوانده شده
        $unreadIds = $messages->filter(function($msg) use ($user) {
            return $msg->to_user_id == $user->id && !$msg->is_read;
        })->pluck('id')->toArray();

        if (!empty($unreadIds)) {
            Message::whereIn('id', $unreadIds)->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        // پیام اول: شرح تسک
        $introMessage = [
            'id' => 'intro',
            'is_intro' => true,
            'message' => "📋 تسک: {$taskResult->task?->title}\n" .
                         "👨‍🎓 دانش‌آموز: {$student?->full_name}\n" .
                         "📅 تاریخ ثبت: {$taskResult->created_at->format('Y/m/d H:i')}\n" .
                         "👤 ثبت‌کننده: {$taskResult->recordedBy?->full_name}",
            'created_at' => $taskResult->created_at->toDateTimeString(),
            'from_user_id' => null,
            'is_from_me' => false,
        ];

        // تبدیل پیام‌ها به فرمت مناسب
        $formattedMessages = $messages->map(function($msg) use ($user) {
            return [
                'id' => $msg->id,
                'is_intro' => false,
                'message' => $msg->message,
                'from_user_id' => $msg->from_user_id,
                'from_user_name' => $msg->fromUser?->full_name ?? 'نامشخص',
                'from_user_role' => $msg->fromUser?->role ?? 'unknown',
                'is_from_me' => $msg->from_user_id == $user->id,
                'is_read' => $msg->is_read,
                'read_at' => $msg->read_at?->toDateTimeString(),
                'created_at' => $msg->created_at->toDateTimeString(),
                'time_ago' => $msg->created_at->diffForHumans(),
            ];
        });

        // ترکیب پیام مقدماتی با پیام‌های واقعی
        $allMessages = collect([$introMessage])->concat($formattedMessages);

        // اطلاعات مکالمه
        $otherUser = null;
        if ($isParent) {
            $otherUser = User::find($taskResult->recorded_by);
        } elseif ($isTeacher) {
            $otherUser = User::find($student?->parent_id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'task_result_id' => $taskResultId,
                'task_title' => $taskResult->task?->title ?? 'بدون عنوان',
                'task_color' => $taskResult->task?->color_code ?? '#6B7280',
                'student' => $student ? [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'avatar' => $student->avatar,
                ] : null,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->full_name,
                    'avatar' => $otherUser->avatar,
                    'role' => $otherUser->role ?? 'unknown',
                    'role_label' => $this->getRoleLabel($otherUser->role),
                ] : null,
                'messages' => $allMessages,
                'unread_count' => count($unreadIds),
            ]
        ]);
    }

    /**
     * ارسال پیام جدید
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'task_result_id' => 'required|exists:task_results,id',
            'message' => 'required|string|max:1000',
            'to_user_id' => 'required|exists:users,id',
        ]);

        $user = $request->user();

        // بررسی دسترسی
        $taskResult = TaskResults::with(['student', 'task'])
            ->find($validated['task_result_id']);

        if (!$taskResult) {
            return response()->json([
                'success' => false,
                'message' => 'نتیجه تسک مورد نظر یافت نشد'
            ], 404);
        }

        $student = $taskResult->student;
        $isParent = $student && $student->parent_id == $user->id;
        $isTeacher = $taskResult->recorded_by == $user->id;

        if (!$isParent && !$isTeacher) {
            return response()->json([
                'success' => false,
                'message' => 'شما دسترسی به این مکالمه ندارید'
            ], 403);
        }

        // بررسی اینکه گیرنده صحیح است
        if ($isParent && $validated['to_user_id'] != $taskResult->recorded_by) {
            return response()->json([
                'success' => false,
                'message' => 'گیرنده نامعتبر است'
            ], 403);
        }

        if ($isTeacher && $validated['to_user_id'] != $student->parent_id) {
            return response()->json([
                'success' => false,
                'message' => 'گیرنده نامعتبر است'
            ], 403);
        }

        DB::beginTransaction();

        try {
            // ایجاد پیام
            $message = Message::create([
                'task_result_id' => $validated['task_result_id'],
                'from_user_id' => $user->id,
                'to_user_id' => $validated['to_user_id'],
                'message' => $validated['message'],
                'is_read' => false,
            ]);

            // بارگذاری روابط
            $message->load(['fromUser', 'toUser']);

            // ========== ثبت لاگ فعالیت ==========
            $userRole = $isParent ? 'والد' : 'معلم';
            $studentName = $student?->full_name ?? 'نامشخص';
            $taskTitle = $taskResult->task?->title ?? 'بدون عنوان';
            
            $description = "ارسال پیام توسط {$userRole} '{$user->full_name}' درباره تسک '{$taskTitle}' برای دانش‌آموز {$studentName}";
            
            ActivityLogger::log($taskResult, 'send_message', $description);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'پیام با موفقیت ارسال شد',
                'data' => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'from_user_id' => $message->from_user_id,
                    'from_user_name' => $message->fromUser?->full_name,
                    'to_user_id' => $message->to_user_id,
                    'to_user_name' => $message->toUser?->full_name,
                    'is_from_me' => true,
                    'created_at' => $message->created_at->toDateTimeString(),
                    'time_ago' => $message->created_at->diffForHumans(),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ارسال پیام: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * دریافت تعداد پیام‌های خوانده نشده
     */
    public function getUnreadCount(Request $request)
    {
        $user = $request->user();

        $count = Message::where('to_user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    /**
     * علامت‌گذاری همه پیام‌های یک مکالمه به عنوان خوانده شده
     */
    public function markConversationAsRead($taskResultId, Request $request)
    {
        $user = $request->user();

        $updated = Message::where('task_result_id', $taskResultId)
            ->where('to_user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'همه پیام‌ها به عنوان خوانده شده علامت خوردند',
            'data' => [
                'marked_count' => $updated
            ]
        ]);
    }

    private function getRoleLabel($role)
    {
        $labels = [
            'manager' => 'مدیر',
            'teacher' => 'معلم',
            'parent' => 'والد',
            'admin' => 'ادمین',
        ];
        return $labels[$role] ?? $role;
    }
}