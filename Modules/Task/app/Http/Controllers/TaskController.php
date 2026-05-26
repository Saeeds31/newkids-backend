<?php

namespace Modules\Task\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Services\NotificationService;
use Modules\Student\Models\Student;
use Modules\Task\Models\RoutineSchedules;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskAssignment;
use Modules\Task\Models\TaskEvaluationCriteria;
use Modules\Task\Models\TaskResultEvaluation;
use Modules\Task\Models\TaskResults;

class TaskController extends Controller
{


    public function completeTask(Request $request, $taskId, NotificationService $notifications)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'student_id' => 'required|exists:students,id',
                'description' => 'nullable|string|max:1000',
                'evaluations' => 'required|array|min:1',
                'evaluations.*.criterion_type' => 'required|string|in:trait,skill',
                'evaluations.*.criterion_id' => 'required|exists:task_evaluation_criteria,id',
                'evaluations.*.score' => 'nullable|numeric|min:0|max:100',
            ]);

            $task = Task::with([
                'evaluationCriteria',
                'taskAssignments' => function ($q) {
                    $q->with(['class']);
                }
            ])->find($taskId);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'تسک مورد نظر یافت نشد'
                ], 404);
            }
            $teacher = $request->user();

            $assignment = TaskAssignment::where('teacher_id', $teacher->id)->where('task_id', $taskId)->first();
            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به ثبت نتیجه برای این تسک را ندارید'
                ], 403);
            }
            $classId = $assignment->class_id;
            $student = Student::where('id', $validated['student_id'])
                ->where('class_id', $classId)
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'این دانش‌آموز در کلاس مربوط به این تسک ثبت نام نشده است'
                ], 404);
            }
            $existingResult = TaskResults::whereHas('taskOccurrence', function ($q) use ($taskId, $classId) {
                $q->where('task_id', $taskId)
                    ->where('class_id', $classId);
            })->where('student_id', $validated['student_id'])->first();

            if ($existingResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'نتیجه این تسک برای این دانش‌آموز قبلاً ثبت شده است',
                    'data' => $existingResult
                ], 409);
            }
            $result = TaskResults::create([
                'task_id' => $taskId,
                'student_id' => $validated['student_id'],
                'description' => $validated['description'] ?? null,
                'recorded_by' => $teacher->id,
            ]);

            foreach ($validated['evaluations'] as $evaluation) {
                $criterion = TaskEvaluationCriteria::find($evaluation['criterion_id']);
                if (!$criterion || $criterion->task_id != $taskId) {
                    continue;
                }

                $resultEvaluation = TaskResultEvaluation::create([
                    'task_result_id' => $result->id,
                    'evaluation_criterion_id' => $criterion->id,
                    'score' => $evaluation['score'],
                ]);
            }
            $totalStudentsInClass = Student::where('class_id', $classId)->count();
            $studentsWhoCompleted = TaskResults::where('task_id', $taskId)
                ->whereHas('student', function ($q) use ($classId) {
                    $q->where('class_id', $classId);
                })
                ->distinct()
                ->count('student_id');

            $isTaskDone = ($totalStudentsInClass == $studentsWhoCompleted);

            if ($isTaskDone) {
                $status = 'done';
            } else {
                $status = 'doing';
            }
            $task->update([
                'status' => $status
            ]);

            DB::commit();
            $notifications->create(
                "ثبت نتیجه تسک",
                "مربی {$teacher->full_name} نتیجه تسک {$task->title} را برای دانش‌آموز {$student->full_name} ثبت کرد",
                "notification_task",
                [
                    'task_id' => $taskId,
                    'student_id' => $student->id,
                    'maker' => $teacher->id,
                ]
            );
            $notifications->create(
                "ثبت نتیجه تسک",
                "شما نتیجه تسک {$task->title} را برای دانش‌آموز {$student->full_name} ثبت کردید",
                "notification_user_" . $teacher->id,
                [
                    'task_id' => $taskId,
                    'maker' => $teacher->id,
                ]
            );
            if ($student->parent_id) {
                $notifications->create(
                    "نتیجه فعالیت فرزندتان",
                    "نتیجه فعالیت {$task->title} برای فرزند شما {$student->full_name} ثبت شد. ",
                    "notification_student_" . $student->id,
                    [
                        'task_id' => $taskId,
                        'maker' => $teacher->id,
                    ]
                );
            }
            return response()->json([
                'success' => true,
                'message' => 'نتیجه تسک با موفقیت ثبت شد',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطای اعتبارسنجی',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت نتیجه تسک: ' . $e->getMessage()
            ], 500);
        }
    }



    public function getTeacherDashboardTasks(Request $request)
    {
        $user = $request->user();
        $onceTasks = Task::with(['creator', 'taskAssignments.class', 'taskAssignments.teacher'])
            ->where('type', 'once')
            ->where(function ($query) use ($user) {
                // تسک‌هایی که کاربر ایجاد کرده یا بهش انتساب داده شده
                $query->where('created_by', $user->id)
                    ->orWhereHas('taskAssignments', function ($q) use ($user) {
                        $q->where('teacher_id', $user->id);
                    });
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // تسک‌های روتین (routine) - ۱۰ تای آخر
        $routineTasks = Task::with(['creator', 'routineSchedule', 'taskAssignments.class', 'taskAssignments.teacher'])
            ->where('type', 'routine')
            ->where(function ($query) use ($user) {
                // تسک‌هایی که کاربر ایجاد کرده یا بهش انتساب داده شده
                $query->where('created_by', $user->id)
                    ->orWhereHas('taskAssignments', function ($q) use ($user) {
                        $q->where('teacher_id', $user->id);
                    });
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'once_tasks' => [
                    'count' => $onceTasks->count(),
                    'tasks' => $onceTasks
                ],
                'routine_tasks' => [
                    'count' => $routineTasks->count(),
                    'tasks' => $routineTasks
                ],
                'total' => $onceTasks->count() + $routineTasks->count()
            ]
        ], 200);
    }

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

                // زمان‌بندی روتین (فقط برای نوع routine - یک رکورد)
                'day_of_week' => 'required_if:type,routine|integer|min:1|max:7',
                'start_time' => 'required_if:type,routine|date_format:H:i',
                'end_time' => 'required_if:type,routine|date_format:H:i|after:start_time',
                'duration_days' => 'required_if:type,routine|integer|min:1',
                'routine_expire_at' => 'required_if:type,routine|date|after:today',

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
            $taskData = [
                'title' => $validated['title'],
                'labels' => $validated['labels'] ?? null,
                'color_code' => $validated['color_code'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
                'type' => $validated['type'],
                'start_date' => $validated['start_date'] ?? null,
            ];

            // اگر نوع روتین است
            if ($validated['type'] === 'routine') {
                $taskData['end_date'] = null;  // تسک روتین end_date ندارد
                $taskData['duration_days'] = $validated['duration_days'];
                $taskData['routine_expire_at'] = $validated['routine_expire_at'];
            } else {
                // تسک یکبار انجام
                $taskData['end_date'] = $validated['end_date'] ?? null;
                $taskData['duration_days'] = null;
                $taskData['routine_expire_at'] = null;
            }

            $task = Task::create($taskData);

            // 2. ایجاد زمان‌بندی روتین (فقط برای نوع routine - یک رکورد)
            if ($validated['type'] === 'routine') {
                RoutineSchedules::create([
                    'task_id' => $task->id,
                    'day_of_week' => $validated['day_of_week'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                    'duration_days' => $validated['duration_days'],
                    'routine_expire_at' => $validated['routine_expire_at'],
                ]);
            }

            // 3. ایجاد انتسابات (TaskAssignments)
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

            // 4. ایجاد معیارهای ارزیابی
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
            $task->load(['creator', 'assignments', 'evaluationCriteria', 'routineSchedule']);

            // ثبت نوتیفیکیشن اصلی
            $notifications->create(
                "ایجاد وظیفه جدید",
                "وظیفه {$task->title} با موفقیت ایجاد شد",
                "notification_task",
                [
                    'task_id' => $task->id,
                    'maker' => $request->user()->full_name,
                    'type' => $task->type
                ]
            );

            // ثبت نوتیفیکیشن برای معلمان
            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $assignment) {
                    $notifications->create(
                        "وظیفه جدید",
                        "یک وظیفه با عنوان {$task->title} برای شما ثبت شد",
                        "notification_user_" . $assignment['teacher_id'],
                        [
                            'task_id' => $task->id,
                            'task_type' => $task->type,
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
            'taskAssignments.class',
            'taskAssignments.teacher',
            'taskAssignments.taskOccurrences.taskResults.student',
            'taskAssignments.taskOccurrences.taskResults.evaluations',
            'evaluationCriteria',
            'taskOccurrences.taskResults'
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
                'status' => 'nullable|string',
                'color_code' => [
                    'sometimes',
                    'required',
                    'string',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                    function ($attribute, $value, $fail) {
                        if (!Task::isValidColor($value)) {
                            $fail('کد رنگ انتخاب شده معتبر نیست.');
                        }
                    },
                ],
                'description' => 'nullable|string',
                'type' => 'sometimes|required|in:routine,once',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',

                // زمان‌بندی روتین (برای نوع routine)
                'day_of_week' => 'nullable|integer|min:1|max:7',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'duration_days' => 'nullable|integer|min:1',
                'routine_expire_at' => 'nullable|date|after:today',
            ]);

            // تشخیص تغییر نوع تسک
            $oldType = $task->type;
            $newType = $validated['type'] ?? $oldType;
            $typeChanged = ($oldType !== $newType);

            // ========== بروزرسانی تسک ==========
            $taskData = [];

            if (isset($validated['title'])) $taskData['title'] = $validated['title'];
            if (isset($validated['labels'])) $taskData['labels'] = $validated['labels'];
            if (isset($validated['color_code'])) $taskData['color_code'] = $validated['color_code'];
            if (isset($validated['description'])) $taskData['description'] = $validated['description'];
            if (isset($validated['start_date'])) $taskData['start_date'] = $validated['start_date'];

            // بروزرسانی نوع تسک
            if (isset($validated['type'])) {
                $taskData['type'] = $validated['type'];
            }

            // مدیریت فیلدها بر اساس نوع جدید
            if ($newType === 'routine') {
                // اگر تسک روتین است
                if (isset($validated['duration_days'])) {
                    $taskData['duration_days'] = $validated['duration_days'];
                } elseif ($typeChanged && $newType === 'routine') {
                    $taskData['duration_days'] = null; // اگر از once به routine تغییر کرده
                }

                if (isset($validated['routine_expire_at'])) {
                    $taskData['routine_expire_at'] = $validated['routine_expire_at'];
                } elseif ($typeChanged && $newType === 'routine') {
                    $taskData['routine_expire_at'] = null;
                }

                // پاک کردن end_date برای تسک روتین
                $taskData['end_date'] = null;
            } else {
                // اگر تسک یکبار انجام است
                if (isset($validated['end_date'])) {
                    $taskData['end_date'] = $validated['end_date'];
                } elseif ($typeChanged && $newType === 'once') {
                    $taskData['end_date'] = null;
                }

                // پاک کردن فیلدهای روتین
                $taskData['duration_days'] = null;
                $taskData['routine_expire_at'] = null;
            }

            $task->update($taskData);

            // ========== مدیریت جدول routine_schedules ==========
            if ($newType === 'routine') {
                // اگر نوع جدید روتین است
                $routineData = [];

                if (isset($validated['day_of_week'])) $routineData['day_of_week'] = $validated['day_of_week'];
                if (isset($validated['start_time'])) $routineData['start_time'] = $validated['start_time'];
                if (isset($validated['end_time'])) $routineData['end_time'] = $validated['end_time'];
                if (isset($validated['duration_days'])) $routineData['duration_days'] = $validated['duration_days'];
                if (isset($validated['routine_expire_at'])) $routineData['routine_expire_at'] = $validated['routine_expire_at'];

                // اگر از once به routine تغییر کرده و داده روتین ارسال نشده
                if ($typeChanged && empty($routineData)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'برای تبدیل تسک به نوع روتین، باید اطلاعات زمان‌بندی (day_of_week, start_time, end_time, duration_days, routine_expire_at) ارسال شود.'
                    ], 422);
                }

                if (!empty($routineData)) {
                    if ($task->routineSchedule) {
                        // بروزرسانی رکورد موجود
                        $task->routineSchedule->update($routineData);
                    } else {
                        // ایجاد رکورد جدید
                        $routineData['task_id'] = $task->id;
                        RoutineSchedules::create($routineData);
                    }
                }
            } else {
                // اگر نوع جدید یکبار انجام است، رکورد routine_schedules را حذف کن
                if ($task->routineSchedule) {
                    $task->routineSchedule->delete();
                }
            }

            // ========== اگر نوع از routine به once تغییر کرده بود ==========
            // و کاربر فیلدهای تسک یکبار انجام را ارسال نکرده بود
            if ($typeChanged && $newType === 'once' && !isset($validated['end_date'])) {
                // اگر نیازی به تغییر end_date نباشد، مشکلی نیست
                // فقط فیلدهای روتین پاک شدند
            }

            DB::commit();

            // بارگذاری مجدد روابط
            $task->load(['creator', 'assignments', 'evaluationCriteria', 'routineSchedule']);

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "بروزرسانی وظیفه",
                "وظیفه {$task->title} بروزرسانی شد" . ($typeChanged ? " و نوع آن از {$oldType} به {$newType} تغییر کرد" : ""),
                "notification_task",
                [
                    'task_id' => $task->id,
                    'maker' => $maker->full_name,
                    'old_type' => $oldType,
                    'new_type' => $newType
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'وظیفه با موفقیت بروزرسانی شد' . ($typeChanged ? " و نوع آن تغییر کرد" : ""),
                'data' => $task
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطای اعتبارسنجی',
                'errors' => $e->errors()
            ], 422);
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
    public function destroy(Request $request, $id, NotificationService $notifications)
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
