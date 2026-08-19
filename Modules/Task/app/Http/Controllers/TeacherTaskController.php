<?php

namespace Modules\Task\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskAssignment;
use Modules\Task\Models\TaskResults;
use Modules\Task\Models\TaskEvaluationCriteria;
use Modules\Task\Models\TaskResultEvaluation;
use Modules\Student\Models\Student;
use Modules\Activity\Services\ActivityLogger;
use Modules\Users\Models\User;
use Carbon\Carbon;

class TeacherTaskController extends Controller
{
    /**
     * دریافت لیست وظایف معلم با فیلتر
     */
    public function index(Request $request)
    {
        $teacher = $request->user();
        $status = $request->get('status', 'all');
        $type = $request->get('type', 'all');
        $classId = $request->get('class_id');
        $search = $request->get('search');

        $query = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
            ->with([
                'creator',
                'taskAssignments.class',
                'taskAssignments.teacher',
                'evaluationCriteria',
                'routineSchedule'
            ]);

        // فیلتر وضعیت
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // فیلتر نوع
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        // فیلتر کلاس
        if ($classId) {
            $query->whereHas('taskAssignments', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        // جستجو
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tasks = $query->orderBy('created_at', 'desc')->get();

        $now = Carbon::now();

        $tasks = $tasks->map(function ($task) use ($teacher, $now) {
            $assignment = $task->taskAssignments->first();
            $classId = $assignment?->class_id;

            $totalStudents = $classId ? Student::where('class_id', $classId)->count() : 0;

            $resultsCount = TaskResults::where('task_id', $task->id)
                ->whereHas('student', function ($q) use ($classId) {
                    if ($classId) {
                        $q->where('class_id', $classId);
                    }
                })
                ->count();

            $progress = $totalStudents > 0 ? round(($resultsCount / $totalStudents) * 100, 2) : 0;

            // ========== بررسی قابلیت ثبت نتیجه ==========
            $canRecord = true;
            $cannotRecordReason = null;

            // 1. بررسی وضعیت تسک
            if ($task->status === 'closed' || $task->status === 'done') {
                $canRecord = false;
                $cannotRecordReason = 'این وظیفه بسته شده است';
            }

            // 2. بررسی تاریخ شروع برای تسک‌های یکباره
            if ($task->type === 'once' && $task->start_date) {
                $startDate = Carbon::parse($task->start_date);
                if ($now->lt($startDate)) {
                    $canRecord = false;
                    $cannotRecordReason = 'زمان شروع این وظیفه ' . $startDate->format('Y/m/d H:i') . ' است';
                }
            }

            // 3. بررسی تاریخ انقضا برای تسک‌های روتین
            if ($task->type === 'routine' && $task->routineSchedule) {
                $expireAt = Carbon::parse($task->routineSchedule->routine_expire_at);
                if ($now->gt($expireAt)) {
                    $canRecord = false;
                    $cannotRecordReason = 'این وظیفه روتین منقضی شده است';
                }
            }

            return [
                'id' => $task->id,
                'title' => $task->title,
                'type' => $task->type,
                'type_label' => $task->type === 'routine' ? 'روتین' : 'یکبار',
                'status' => $task->status,
                'status_label' => $this->getStatusLabel($task->status),
                'color_code' => $task->color_code,
                'description' => $task->description,
                'created_at' => $task->created_at->toDateTimeString(),
                'start_date' => $task->start_date?->toDateTimeString(),
                'end_date' => $task->end_date?->toDateTimeString(),
                'class' => $assignment?->class ? [
                    'id' => $assignment->class->id,
                    'name' => $assignment->class->full_name,
                ] : null,
                'teacher' => $assignment?->teacher ? [
                    'id' => $assignment->teacher->id,
                    'name' => $assignment->teacher->full_name,
                ] : null,
                'routine_schedule' => $task->routineSchedule ? [
                    'day_of_week' => $task->routineSchedule->day_of_week,
                    'day_label' => $this->getDayLabel($task->routineSchedule->day_of_week),
                    'start_time' => $task->routineSchedule->start_time,
                    'end_time' => $task->routineSchedule->end_time,
                    'duration_days' => $task->routineSchedule->duration_days,
                    'routine_expire_at' => $task->routineSchedule->routine_expire_at?->toDateTimeString(),
                ] : null,
                'evaluation_criteria' => $task->evaluationCriteria->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'criterion_type' => $c->criterion_type,
                        'criterion_type_label' => $c->criterion_type === 'trait' ? 'ویژگی' : 'مهارت',
                        'criterion_name' => $c->criterion_name,
                        'max_score' => $c->max_score,
                        'weight' => $c->weight,
                    ];
                }),
                'statistics' => [
                    'total_students' => $totalStudents,
                    'results_count' => $resultsCount,
                    'progress' => $progress,
                    'can_record' => $canRecord,
                    'cannot_record_reason' => $cannotRecordReason,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }


    /**
     * دریافت جزئیات یک وظیفه برای ثبت نتیجه
     */
    public function getTaskForRecording($taskId, Request $request)
    {
        $teacher = $request->user();

        $task = Task::with([
            'taskAssignments.class',
            'taskAssignments.teacher',
            'evaluationCriteria',
            'routineSchedule'
        ])->find($taskId);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'وظیفه مورد نظر یافت نشد'
            ], 404);
        }

        $assignment = $task->taskAssignments->first();
        if (!$assignment || $assignment->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'شما دسترسی به این وظیفه ندارید'
            ], 403);
        }

        $classId = $assignment->class_id;

        // دریافت دانش‌آموزان کلاس
        $students = Student::where('class_id', $classId)
            ->with(['parent'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // دریافت نتایج ثبت شده برای این تسک
        $existingResults = TaskResults::where('task_id', $taskId)
            ->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->with('evaluations')
            ->get()
            ->keyBy('student_id');

        // ساختار داده برای هر دانش‌آموز
        $studentsData = $students->map(function ($student) use ($existingResults, $task) {
            $result = $existingResults->get($student->id);

            return [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->full_name,
                'avatar' => $student->avatar,
                'has_result' => (bool) $result,
                'result_id' => $result?->id,
                'evaluations' => $result ? $result->evaluations->map(function ($e) {
                    return [
                        'evaluation_criterion_id' => $e->evaluation_criterion_id,
                        'score' => $e->score,
                    ];
                }) : collect(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'type' => $task->type,
                    'type_label' => $task->type === 'routine' ? 'روتین' : 'یکبار',
                    'status' => $task->status,
                    'status_label' => $this->getStatusLabel($task->status),
                    'color_code' => $task->color_code,
                    'description' => $task->description,
                    'start_date' => $task->start_date?->toDateTimeString(),
                    'end_date' => $task->end_date?->toDateTimeString(),
                    'class' => [
                        'id' => $assignment->class->id,
                        'name' => $assignment->class->full_name,
                    ],
                ],
                'evaluation_criteria' => $task->evaluationCriteria->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'criterion_type' => $c->criterion_type,
                        'criterion_type_label' => $c->criterion_type === 'trait' ? 'ویژگی' : 'مهارت',
                        'criterion_name' => $c->criterion_name,
                        'max_score' => $c->max_score,
                        'weight' => $c->weight,
                        'icon' => $c->icon,
                        'color' => $c->color,
                    ];
                }),
                'students' => $studentsData,
                'statistics' => [
                    'total' => $students->count(),
                    'completed' => $existingResults->count(),
                    'progress' => $students->count() > 0
                        ? round(($existingResults->count() / $students->count()) * 100, 2)
                        : 0,
                ]
            ]
        ]);
    }
    public function storeBulkResults(Request $request)
    {
        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'results' => 'required|array|min:1',
            'results.*.student_id' => 'required|exists:students,id',
            'results.*.evaluations' => 'required|array|min:1',
            'results.*.evaluations.*.evaluation_criterion_id' => 'required|exists:task_evaluation_criteria,id',
            'results.*.evaluations.*.score' => 'required|numeric|min:0',
            'results.*.description' => 'nullable|string|max:1000',
        ]);

        $teacher = $request->user();

        DB::beginTransaction();

        try {
            // بررسی دسترسی معلم به این تسک
            $assignment = TaskAssignment::where('task_id', $validated['task_id'])
                ->where('teacher_id', $teacher->id)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به این وظیفه ندارید'
                ], 403);
            }

            $classId = $assignment->class_id;
            $results = [];
            $studentNames = [];

            foreach ($validated['results'] as $resultData) {
                // بررسی اینکه دانش‌آموز در کلاس این تسک است
                $student = Student::where('id', $resultData['student_id'])
                    ->where('class_id', $classId)
                    ->first();

                if (!$student) {
                    continue;
                }

                $studentNames[] = $student->full_name;

                // پیدا کردن یا ایجاد نتیجه
                $result = TaskResults::updateOrCreate(
                    [
                        'task_id' => $validated['task_id'],
                        'student_id' => $resultData['student_id'],
                    ],
                    [
                        'description' => $resultData['description'] ?? null,
                        'recorded_by' => $teacher->id,
                    ]
                );

                // حذف ارزیابی‌های قبلی
                $result->evaluations()->delete();

                // ایجاد ارزیابی‌های جدید
                foreach ($resultData['evaluations'] as $evaluation) {
                    TaskResultEvaluation::create([
                        'task_result_id' => $result->id,
                        'evaluation_criterion_id' => $evaluation['evaluation_criterion_id'],
                        'score' => $evaluation['score'],
                    ]);
                }

                $results[] = $result->load('evaluations');
            }

            // بروزرسانی وضعیت تسک
            $task = Task::find($validated['task_id']);
            $this->updateTaskStatus($validated['task_id'], $classId);

            DB::commit();

            // ========== ثبت فعالیت (Activity Log) ==========
            $taskTitle = $task?->title ?? 'نامشخص';
            $studentsList = implode('، ', array_slice($studentNames, 0, 5));
            if (count($studentNames) > 5) {
                $studentsList .= ' و ' . (count($studentNames) - 5) . ' نفر دیگر';
            }

            $description = "ثبت نتایج وظیفه '{$taskTitle}' برای دانش‌آموزان: {$studentsList}";

            ActivityLogger::log($task, 'store_results', $description);

            return response()->json([
                'success' => true,
                'message' => 'نتایج با موفقیت ثبت شد',
                'data' => $results
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت نتایج: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ثبت یا بروزرسانی نتیجه یک دانش‌آموز
     */
    public function storeResult(Request $request)
    {
        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'student_id' => 'required|exists:students,id',
            'evaluations' => 'required|array|min:1',
            'evaluations.*.evaluation_criterion_id' => 'required|exists:task_evaluation_criteria,id',
            'evaluations.*.score' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        $teacher = $request->user();

        DB::beginTransaction();

        try {
            // بررسی دسترسی معلم به این تسک
            $assignment = TaskAssignment::where('task_id', $validated['task_id'])
                ->where('teacher_id', $teacher->id)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به این وظیفه ندارید'
                ], 403);
            }

            // بررسی اینکه دانش‌آموز در کلاس این تسک است
            $student = Student::where('id', $validated['student_id'])
                ->where('class_id', $assignment->class_id)
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'این دانش‌آموز در کلاس مربوط به این تسک نیست'
                ], 404);
            }

            // پیدا کردن یا ایجاد نتیجه
            $result = TaskResults::updateOrCreate(
                [
                    'task_id' => $validated['task_id'],
                    'student_id' => $validated['student_id'],
                ],
                [
                    'description' => $validated['description'] ?? null,
                    'recorded_by' => $teacher->id,
                ]
            );

            // حذف ارزیابی‌های قبلی
            $result->evaluations()->delete();

            // ایجاد ارزیابی‌های جدید
            foreach ($validated['evaluations'] as $evaluation) {
                TaskResultEvaluation::create([
                    'task_result_id' => $result->id,
                    'evaluation_criterion_id' => $evaluation['evaluation_criterion_id'],
                    'score' => $evaluation['score'],
                ]);
            }

            // بروزرسانی وضعیت تسک
            $task = Task::find($validated['task_id']);
            $this->updateTaskStatus($validated['task_id'], $assignment->class_id);

            DB::commit();

            // ========== ثبت فعالیت (Activity Log) ==========
            $taskTitle = $task?->title ?? 'نامشخص';
            $studentName = $student->full_name ?? 'نامشخص';
            $description = "ثبت نتیجه وظیفه '{$taskTitle}' برای دانش‌آموز {$studentName}";

            ActivityLogger::log($task, 'store_result', $description);

            return response()->json([
                'success' => true,
                'message' => 'نتیجه با موفقیت ثبت شد',
                'data' => $result->load('evaluations')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت نتیجه: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * بروزرسانی وضعیت تسک بر اساس تعداد نتایج ثبت شده
     */
    private function updateTaskStatus($taskId, $classId)
    {
        $task = Task::find($taskId);
        if (!$task) return;

        $oldStatus = $task->status;
        $totalStudents = Student::where('class_id', $classId)->count();
        $completedResults = TaskResults::where('task_id', $taskId)
            ->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->count();

        if ($totalStudents == 0) {
            $newStatus = 'todo';
        } elseif ($completedResults == 0) {
            $newStatus = 'todo';
        } elseif ($completedResults < $totalStudents) {
            $newStatus = 'doing';
        } else {
            $newStatus = 'done';
        }

        $task->update(['status' => $newStatus]);

        // ========== ثبت فعالیت تغییر وضعیت تسک (اگر تغییر کرده باشد) ==========
        if ($oldStatus !== $newStatus) {
            $statusLabels = [
                'todo' => 'انجام نشده',
                'doing' => 'در حال انجام',
                'done' => 'انجام شده',
                'closed' => 'بسته شده',
            ];
            $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
            $newLabel = $statusLabels[$newStatus] ?? $newStatus;

            $description = "وضعیت تسک '{$task->title}' از '{$oldLabel}' به '{$newLabel}' تغییر یافت";
            ActivityLogger::log($task, 'update_status', $description);
        }
    }

    /**
     * دریافت آمار وظایف معلم
     */
    public function getStatistics(Request $request)
    {
        $teacher = $request->user();

        $totalTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->count();

        $todoTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('status', 'todo')->count();

        $doingTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('status', 'doing')->count();

        $doneTasks = Task::whereHas('taskAssignments', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereIn('status', ['done', 'closed'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalTasks,
                'todo' => $todoTasks,
                'doing' => $doingTasks,
                'done' => $doneTasks,
            ]
        ]);
    }

    /**
     * دریافت لیست کلاس‌های معلم برای فیلتر
     */
    public function getClasses(Request $request)
    {
        $teacher = $request->user();

        $classes = \Modules\Class\Models\Classes::whereHas('classSubjectTimes', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
            ->with('grade')
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->full_name,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $classes
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

    private function getDayLabel($day)
    {
        $days = [
            'Saturday' => 'شنبه',
            'Sunday' => 'یکشنبه',
            'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه',
            'Wednesday' => 'چهارشنبه',
            'Thursday' => 'پنجشنبه',
        ];
        return $days[$day] ?? $day;
    }
}
