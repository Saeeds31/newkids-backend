<?php

namespace Modules\Task\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Student\Models\Student;
use Modules\Task\Models\TaskResults;
use Modules\Task\Models\Message;
use Modules\Users\Models\User;
use Carbon\Carbon;

class ParentTaskReportController extends Controller
{
    /**
     * دریافت لیست گزارش‌های ثبت شده برای فرزندان والد
     */
    public function index(Request $request)
    {
        $parent = $request->user();
        $childId = $request->get('child_id');
        $taskId = $request->get('task_id');
        $search = $request->get('search');

        // دریافت فرزندان این والد
        $children = Student::where('parent_id', $parent->id)->pluck('id')->toArray();

        if (empty($children)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'هیچ فرزندی برای این والد ثبت نشده است'
            ]);
        }

        // فیلتر بر اساس فرزند خاص
        if ($childId) {
            if (!in_array($childId, $children)) {
                return response()->json([
                    'success' => false,
                    'message' => 'دسترسی به این فرزند ندارید'
                ], 403);
            }
            $children = [$childId];
        }

        // دریافت نتایج
        $query = TaskResults::with([
            'task',
            'student',
            'recordedBy',
            'evaluations.evaluationCriterion'
        ])
        ->whereIn('student_id', $children)
        ->orderBy('created_at', 'desc');

        // فیلتر بر اساس تسک
        if ($taskId) {
            $query->where('task_id', $taskId);
        }

        // جستجو
        if ($search) {
            $query->whereHas('task', function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })->orWhereHas('student', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $results = $query->paginate($request->get('per_page', 20));

        // افزودن اطلاعات تکمیلی
        $results->getCollection()->transform(function($result) use ($parent) {
            // محاسبه مجموع نمرات
            $totalScore = $result->evaluations->sum('score') ?? 0;
            $maxScore = $result->evaluations->sum(function($e) {
                return $e->evaluationCriterion->max_score ?? 0;
            }) ?: 1;

            // بررسی وجود پیام‌های خوانده نشده برای این نتیجه
            $unreadMessagesCount = Message::where('task_result_id', $result->id)
                ->where('to_user_id', $parent->id)
                ->where('is_read', false)
                ->count();

            // آخرین پیام
            $lastMessage = Message::where('task_result_id', $result->id)
                ->with(['fromUser', 'toUser'])
                ->orderBy('created_at', 'desc')
                ->first();

            // نام معلم
            $teacherName = $result->recordedBy?->full_name ?? 'نامشخص';

            return [
                'id' => $result->id,
                'task_id' => $result->task_id,
                'task_title' => $result->task?->title ?? 'بدون عنوان',
                'task_status' => $result->task?->status ?? 'unknown',
                'task_status_label' => $this->getStatusLabel($result->task?->status),
                'task_color' => $result->task?->color_code ?? '#6B7280',
                'student_id' => $result->student_id,
                'student_name' => $result->student?->full_name ?? 'نامشخص',
                'student_avatar' => $result->student?->avatar,
                'description' => $result->description,
                'recorded_by' => $teacherName,
                'recorded_by_id' => $result->recorded_by,
                'created_at' => $result->created_at->toDateTimeString(),
                'time_ago' => $result->created_at->diffForHumans(),
                'evaluations' => $result->evaluations->map(function($e) {
                    return [
                        'id' => $e->id,
                        'criterion_name' => $e->evaluationCriterion?->criterion_name ?? 'بدون نام',
                        'criterion_type' => $e->evaluationCriterion?->criterion_type ?? 'unknown',
                        'criterion_type_label' => $e->evaluationCriterion?->criterion_type === 'trait' ? 'ویژگی' : 'مهارت',
                        'score' => $e->score,
                        'max_score' => $e->evaluationCriterion?->max_score ?? 1,
                        'percentage' => $e->evaluationCriterion?->max_score > 0 
                            ? round(($e->score / $e->evaluationCriterion->max_score) * 100, 2) 
                            : 0,
                        'color' => $e->evaluationCriterion?->color ?? '#6B7280',
                        'icon' => $e->evaluationCriterion?->icon ?? '📌',
                    ];
                }),
                'statistics' => [
                    'total_score' => $totalScore,
                    'max_score' => $maxScore,
                    'percentage' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0,
                    'evaluations_count' => $result->evaluations->count(),
                ],
                'messages' => [
                    'unread_count' => $unreadMessagesCount,
                    'last_message' => $lastMessage ? [
                        'id' => $lastMessage->id,
                        'message' => $lastMessage->message,
                        'from_user' => $lastMessage->fromUser?->full_name ?? 'نامشخص',
                        'from_user_id' => $lastMessage->from_user_id,
                        'created_at' => $lastMessage->created_at->toDateTimeString(),
                        'time_ago' => $lastMessage->created_at->diffForHumans(),
                        'is_read' => $lastMessage->is_read,
                    ] : null,
                    'has_messages' => $lastMessage ? true : false,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * دریافت جزئیات یک گزارش خاص
     */
    public function show($id, Request $request)
    {
        $parent = $request->user();

        $result = TaskResults::with([
            'task',
            'student',
            'recordedBy',
            'evaluations.evaluationCriterion',
            'messages.fromUser',
            'messages.toUser'
        ])->find($id);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'گزارش مورد نظر یافت نشد'
            ], 404);
        }

        // بررسی دسترسی والد به این نتیجه
        $student = $result->student;
        if (!$student || $student->parent_id != $parent->id) {
            return response()->json([
                'success' => false,
                'message' => 'شما دسترسی به این گزارش ندارید'
            ], 403);
        }

        // محاسبه مجموع نمرات
        $totalScore = $result->evaluations->sum('score') ?? 0;
        $maxScore = $result->evaluations->sum(function($e) {
            return $e->evaluationCriterion->max_score ?? 0;
        }) ?: 1;

        // پیام‌های خوانده نشده
        $unreadMessagesCount = Message::where('task_result_id', $result->id)
            ->where('to_user_id', $parent->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $result->id,
                'task_id' => $result->task_id,
                'task_title' => $result->task?->title ?? 'بدون عنوان',
                'task_description' => $result->task?->description,
                'task_status' => $result->task?->status ?? 'unknown',
                'task_status_label' => $this->getStatusLabel($result->task?->status),
                'task_color' => $result->task?->color_code ?? '#6B7280',
                'task_type' => $result->task?->type ?? 'once',
                'task_type_label' => $result->task?->type === 'routine' ? 'روتین' : 'یکبار',
                'student' => [
                    'id' => $result->student_id,
                    'name' => $result->student?->full_name ?? 'نامشخص',
                    'avatar' => $result->student?->avatar,
                    'class_name' => $result->student?->class?->full_name ?? 'بدون کلاس',
                ],
                'description' => $result->description,
                'recorded_by' => $result->recordedBy?->full_name ?? 'نامشخص',
                'recorded_by_id' => $result->recorded_by,
                'created_at' => $result->created_at->toDateTimeString(),
                'updated_at' => $result->updated_at->toDateTimeString(),
                'time_ago' => $result->created_at->diffForHumans(),
                'evaluations' => $result->evaluations->map(function($e) {
                    return [
                        'id' => $e->id,
                        'criterion_name' => $e->evaluationCriterion?->criterion_name ?? 'بدون نام',
                        'criterion_type' => $e->evaluationCriterion?->criterion_type ?? 'unknown',
                        'criterion_type_label' => $e->evaluationCriterion?->criterion_type === 'trait' ? 'ویژگی' : 'مهارت',
                        'score' => $e->score,
                        'max_score' => $e->evaluationCriterion?->max_score ?? 1,
                        'percentage' => $e->evaluationCriterion?->max_score > 0 
                            ? round(($e->score / $e->evaluationCriterion->max_score) * 100, 2) 
                            : 0,
                        'color' => $e->evaluationCriterion?->color ?? '#6B7280',
                        'icon' => $e->evaluationCriterion?->icon ?? '📌',
                        'qualitative_status' => $e->qualitative_status ?? 'نامشخص',
                        'qualitative_color' => $e->qualitative_color ?? '#6B7280',
                    ];
                }),
                'statistics' => [
                    'total_score' => $totalScore,
                    'max_score' => $maxScore,
                    'percentage' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0,
                    'evaluations_count' => $result->evaluations->count(),
                ],
                'messages' => $result->messages->map(function($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'from_user' => $message->fromUser?->full_name ?? 'نامشخص',
                        'from_user_id' => $message->from_user_id,
                        'from_user_role' => $message->fromUser?->role ?? 'unknown',
                        'to_user' => $message->toUser?->full_name ?? 'نامشخص',
                        'to_user_id' => $message->to_user_id,
                        'created_at' => $message->created_at->toDateTimeString(),
                        'time_ago' => $message->created_at->diffForHumans(),
                        'is_read' => $message->is_read,
                    ];
                }),
                'unread_messages_count' => $unreadMessagesCount,
            ]
        ]);
    }

    /**
     * دریافت لیست فرزندان والد برای فیلتر
     */
    public function getChildren(Request $request)
    {
        $parent = $request->user();

        $children = Student::where('parent_id', $parent->id)
            ->with(['class'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'class_name' => $student->class?->full_name ?? 'بدون کلاس',
                    'avatar' => $student->avatar,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $children
        ]);
    }

    /**
     * دریافت لیست تسک‌های ثبت شده برای یک فرزند
     */
    public function getTasksForChild($childId, Request $request)
    {
        $parent = $request->user();

        // بررسی دسترسی
        $student = Student::where('id', $childId)
            ->where('parent_id', $parent->id)
            ->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی به این فرزند ندارید'
            ], 403);
        }

        $tasks = TaskResults::where('student_id', $childId)
            ->with('task')
            ->distinct('task_id')
            ->get()
            ->map(function($result) {
                return [
                    'id' => $result->task_id,
                    'title' => $result->task?->title ?? 'بدون عنوان',
                    'color' => $result->task?->color_code ?? '#6B7280',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }

    private function getStatusLabel($status)
    {
        $labels = [
            'todo' => 'انجام نشده',
            'doing' => 'در حال انجام',
            'done' => 'انجام شده',
            'closed' => 'بسته شده',
        ];
        return $labels[$status] ?? $status;
    }
}