<?php

namespace Modules\Subject\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Notifications\Services\NotificationService;
use Modules\Subject\Models\Subject;

class SubjectController extends Controller
{
    /**
     * نمایش لیست تمام دروس
     */
    public function index(Request $request)
    {
        $subjects = Subject::query()
            ->when($request->get('search'), function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $subjects
        ], 200);
    }

    /**
     * ذخیره درس جدید
     */
    public function store(Request $request, NotificationService $notifications)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:subjects,name',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        DB::beginTransaction();

        try {

            if ($request->hasFile('image')) {
                $subjectImage = $request->file('image')->store('subjects/images', 'public');
                $validated['image'] = $subjectImage;
            }

            // ایجاد درس
            $subject = Subject::create($validated);

            DB::commit();

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "ثبت درس جدید",
                "درس {$subject->name} با موفقیت در سیستم ثبت شد",
                "notification_class",
                [
                    'subject_id' => $subject->id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'درس با موفقیت ایجاد شد',
                'data' => $subject
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت درس: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * نمایش یک درس
     */
    public function show($id)
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'درس مورد نظر یافت نشد'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $subject
        ], 200);
    }

    /**
     * بروزرسانی درس
     */
    public function update(Request $request, $id, NotificationService $notifications)
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'درس مورد نظر یافت نشد'
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('subjects', 'name')->ignore($subject->id)
            ],
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        DB::beginTransaction();

        try {
            if ($request->hasFile('image')) {
                if ($subject->image && Storage::disk('public')->exists($subject->image)) {
                    Storage::disk('public')->delete($subject->image);
                }
                $validated['image'] = $request->file('image')->store('subject/images', 'public');
            }

            // بروزرسانی درس
            $subject->update($validated);

            DB::commit();

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "بروزرسانی درس",
                "درس {$subject->name} بروزرسانی شد",
                "notification_class",
                [
                    'subject_id' => $subject->id,
                    'maker' => $maker->full_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'درس با موفقیت بروزرسانی شد',
                'data' => $subject
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
     * حذف درس (نرم یا سخت)
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'درس مورد نظر یافت نشد'
            ], 404);
        }


        $subjectName = $subject->name;

        DB::beginTransaction();

        try {
            $subject->delete();
            DB::commit();

            // ثبت نوتیفیکیشن
            $maker = $request->user();
            $notifications->create(
                "حذف درس",
                "درس {$subjectName} از سیستم حذف شد",
                "notification_class",
                [
                    'subject_id' => $id,
                    'maker' => $maker->full_name,
                ]
            );

            return response()->json([
                'success' => true,
                'message' =>'درس با موفقیت حذف شد'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف: ' . $e->getMessage()
            ], 500);
        }
    }

}
