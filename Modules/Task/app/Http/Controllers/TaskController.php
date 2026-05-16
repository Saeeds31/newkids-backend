<?php

namespace Modules\Task\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Services\NotificationService;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskAssignment;
use Modules\Task\Models\TaskEvaluationCriteria;

class TaskController extends Controller
{
    public function getColorPalette()
    {
        return response()->json([
            'success' => true,
            'data' => Task::getColorPalette()
        ], 200);
    }
    public function index()
    {
        $tasks = Task::with([
            'creator',
            'taskAssignments.class',
            'taskAssignments.teacher',
            'evaluationCriteria',
            'taskOccurrences.taskResults'
        ])->latest()->paginate(20);
        return response()->json([
            'success' => true,
            'data' => $tasks
        ], 200);
    }

    /**
     * ایجاد وظیفه جدید با تمام وابستگی‌ها
     */
    public function store(Request $request, NotificationService $notifications)
    {
        DB::beginTransaction();

        try {
            // اعتبارسنجی
            $validated = $request->validate([
                // فیلدهای Task
                'title' => 'required|string|max:200',
                'labels' => 'nullable|array',
                'color_code' => [
                    'required',
                    'string',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                    function ($attribute, $value, $fail) {
                        if (!Task::isValidColor($value)) {
                            $fail('کد رنگ انتخاب شده معتبر نیست. از رنگ‌های موجود استفاده کنید.');
                        }
                    },
                ],
                'description' => 'nullable|string',
                'type' => 'required|in:routine,once',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',

                // انتسابات (Assignments)
                'assignments' => 'nullable|array',
                'assignments.*.class_id' => 'required_with:assignments|exists:classes,id',
                'assignments.*.teacher_id' => 'required_with:assignments|exists:users,id',

                // معیارهای ارزیابی
                'evaluation_criteria' => 'nullable|array',
                'evaluation_criteria.*.criterion_type' => 'required_with:evaluation_criteria|string|in:trait,skill',
                'evaluation_criteria.*.criterion_id' => 'required_with:evaluation_criteria|integer',
                'evaluation_criteria.*.weight' => 'nullable|numeric|min:0|max:100',
                'evaluation_criteria.*.max_score' => 'required_with:evaluation_criteria|numeric|min:0|max:100',
            ]);

            // 1. ایجاد Task
            $task = Task::create([
                'title' => $validated['title'],
                'labels' => $validated['labels'] ?? null,
                'color_code' => $validated['color_code'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
                'type' => $validated['type'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
            ]);

            // 2. ایجاد انتسابات (TaskAssignments)
            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $assignment) {
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'class_id' => $assignment['class_id'],
                        'teacher_id' => $assignment['teacher_id'],
                        'assigned_by' => $request->user()->id,
                    ]);
                }
            }

            // 3. ایجاد معیارهای ارزیابی
            if (!empty($validated['evaluation_criteria'])) {
                foreach ($validated['evaluation_criteria'] as $criterion) {
                    TaskEvaluationCriteria::create([
                        'task_id' => $task->id,
                        'criterion_type' => $criterion['criterion_type'],
                        'criterion_id' => $criterion['criterion_id'],
                        'weight' => $criterion['weight'] ?? null,
                        'max_score' => $criterion['max_score'] ?? null,
                    ]);
                }
            }

            DB::commit();

            // بارگذاری روابط
            $task->load(['creator', 'assignments', 'evaluationCriteria']);

            // ثبت نوتیفیکیشن
            $notifications->create(
                "ایجاد وظیفه جدید",
                "وظیفه {$task->title} با موفقیت ایجاد شد",
                "notification_task",
                [
                    'task_id' => $task->id,
                    'maker' => $request->user()->full_name
                ]
            );
            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $assignment) {
                    $notifications->create(
                        "ایجاد وظیفه جدید",
                        "یک وظیفه با عنوان {$task->title} برای شما ثبت شد",
                        "notification_user_" . $assignment['teacher_id'],
                        [
                            'task_id' => $task->id,
                            'maker' => $request->user()->full_name
                        ]
                    );
                }
            }
            return response()->json([
                'success' => true,
                'message' => 'وظیفه با موفقیت ایجاد شد',
                'data' => $task
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد وظیفه: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * نمایش یک وظیفه با تمام جزئیات
     */
    public function show($id)
    {
        $task = Task::with([
            'creator',
            'assignments.class',
            'assignments.teacher',
            'assignments.occurrences.results.student',
            'assignments.occurrences.results.evaluations',
            'evaluationCriteria',
            'occurrences.results'
        ])->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'وظیفه مورد نظر یافت نشد'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task
        ], 200);
    }

    /**
     * بروزرسانی وظیفه
     */
    public function update(Request $request, $id, NotificationService $notifications)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'وظیفه مورد نظر یافت نشد'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:200',
                'labels' => 'nullable|array',
                'color_code' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'description' => 'nullable|string',
                'type' => 'sometimes|required|in:homework,exam,project,activity',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $task->update($validated);

            DB::commit();

            $task->load(['creator', 'assignments', 'evaluationCriteria']);

            $notifications->create(
                "بروزرسانی وظیفه",
                "وظیفه {$task->title} بروزرسانی شد",
                "notification_task",
                [
                    'task_id' => $task->id,
                    'maker' => $request->user()->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'وظیفه با موفقیت بروزرسانی شد',
                'data' => $task
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در بروزرسانی: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف وظیفه (به همراه همه وابستگی‌ها)
     */
    public function destroy($id, NotificationService $notifications)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'وظیفه مورد نظر یافت نشد'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $taskTitle = $task->title;

            // حذف cascade انجام میشه (با توجه به foreign key constraints)
            $task->delete();

            DB::commit();

            $notifications->create(
                "حذف وظیفه",
                "وظیفه {$taskTitle} از سیستم حذف شد",
                "notification_task",
                [
                    'deleted_task_id' => $id,
                    'maker' => $request()->user()->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'وظیفه با موفقیت حذف شد'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ثبت نتیجه برای یک دانش‌آموز
     */
    public function storeResult(Request $request, NotificationService $notifications)
    {
        $validated = $request->validate([
            'task_occurrence_id' => 'required|exists:task_occurrences,id',
            'student_id' => 'required|exists:students,id',
            'description' => 'nullable|string',
            'evaluations' => 'required|array',
            'evaluations.*.evaluation_criterion_id' => 'required|exists:task_evaluation_criteria,id',
            'evaluations.*.score' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // ایجاد نتیجه
            $result = TaskResult::create([
                'task_occurrence_id' => $validated['task_occurrence_id'],
                'student_id' => $validated['student_id'],
                'description' => $validated['description'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            // ایجاد ارزیابی‌های نتیجه
            foreach ($validated['evaluations'] as $evaluation) {
                TaskResultEvaluation::create([
                    'task_result_id' => $result->id,
                    'evaluation_criterion_id' => $evaluation['evaluation_criterion_id'],
                    'score' => $evaluation['score'],
                ]);
            }

            DB::commit();

            $result->load(['student', 'evaluations.evaluationCriterion']);

            $notifications->create(
                "ثبت نتیجه وظیفه",
                "نتیجه برای دانش‌آموز {$result->student->full_name} ثبت شد",
                "notification_task_result",
                [
                    'result_id' => $result->id,
                    'maker' => $request->user()->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'نتیجه با موفقیت ثبت شد',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت نتیجه: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * دریافت نتایج یک وظیفه
     */
    public function getTaskResults($taskId)
    {
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'وظیفه مورد نظر یافت نشد'
            ], 404);
        }

        $results = TaskResult::whereHas('occurrence.assignment.task', function ($query) use ($taskId) {
            $query->where('id', $taskId);
        })->with(['student', 'evaluations.evaluationCriterion', 'recorder'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $results,
            'task' => $task
        ], 200);
    }

    /**
     * دریافت آمار یک وظیفه
     */
    public function getTaskStatistics($taskId)
    {
        $task = Task::with(['assignments.class', 'occurrences.results'])->find($taskId);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'وظیفه مورد نظر یافت نشد'
            ], 404);
        }

        $statistics = [
            'total_assignments' => $task->assignments->count(),
            'total_occurrences' => $task->occurrences->count(),
            'total_results' => $task->occurrences->sum(function ($occ) {
                return $occ->results->count();
            }),
            'average_score' => $this->calculateAverageScore($task),
            'submission_rate' => $this->calculateSubmissionRate($task),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'task' => $task
        ], 200);
    }

    private function calculateAverageScore($task)
    {
        $allScores = [];

        foreach ($task->occurrences as $occurrence) {
            foreach ($occurrence->results as $result) {
                $totalScore = $result->evaluations->sum('score');
                $allScores[] = $totalScore;
            }
        }

        return count($allScores) > 0 ? array_sum($allScores) / count($allScores) : 0;
    }

    private function calculateSubmissionRate($task)
    {
        // منطق محاسبه نرخ تحویل
        // بستگی به منطق کسب و کار شما دارد
        return 0;
    }
}
