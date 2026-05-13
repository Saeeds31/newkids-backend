<?php

namespace Modules\Grade\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Grade\Http\Requests\GradeStoreRequest;
use Modules\Grade\Http\Requests\GradeUpdateRequest;
use Modules\Grade\Models\Grade;
use Modules\Notifications\Models\Notification;

class GradeController extends Controller
{

    public function index()
    {
        $grades = Grade::all();
        return response()->json([
            'success' => true,
            'data' => $grades
        ], 200);
    }

    /**
     * ذخیره پایه جدید
     */
    public function store(GradeStoreRequest $request, Notification $notifications)
    {
        $validated = $request->validated();
        $grade = Grade::create($validated);
        $maker = $request->user();
        $notifications->create(
            " ساخت پایه تحصیلی",
            " یک پایه تحصیلی جدید در سیستم ثبت شد",
            "notification_class",
            ['grade' => $grade->id, 'maker' => $maker->full_name]
        );
        return response()->json([
            'success' => true,
            'message' => 'پایه با موفقیت ایجاد شد',
            'data' => $grade
        ], 201);
    }

    /**
     * نمایش یک پایه خاص
     */
    public function show($id)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'پایه مورد نظر یافت نشد'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $grade
        ], 200);
    }

    /**
     * بروزرسانی پایه
     */
    public function update(GradeUpdateRequest $request, $id, Notification $notifications)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'پایه مورد نظر یافت نشد'
            ], 404);
        }

        $validated = $request->validated();


        $grade->update($validated);
        $maker = $request->user();
        $notifications->create(
            " ساخت پایه تحصیلی",
            " یک پایه تحصیلی جدید در سیستم ثبت شد",
            "notification_class",
            ['grade' => $grade->id, 'maker' => $maker->full_name]
        );
        return response()->json([
            'success' => true,
            'message' => 'پایه با موفقیت بروزرسانی شد',
            'data' => $grade
        ], 200);
    }

    /**
     * حذف پایه (Soft Delete)
     */
    public function destroy(Request $request, $id, Notification $notifications)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'پایه مورد نظر یافت نشد'
            ], 404);
        }

        // اگر می‌خواهید قبل از حذف وابستگی‌ها را چک کنید
        if ($grade->classes()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'این پایه به کلاس متصل است، قابل حذف نمی‌باشد'
            ], 409);
        }

        $grade->delete();
        $maker = $request->user();
        $notifications->create(
            " ساخت پایه تحصیلی",
            " یک پایه تحصیلی جدید در سیستم ثبت شد",
            "notification_class",
            ['grade' => $grade->id, 'maker' => $maker->full_name]
        );
        return response()->json([
            'success' => true,
            'message' => 'پایه با موفقیت حذف شد'
        ], 200);
    }

    /**
     * نمایش لیست پایه‌های حذف شده (ترش‌کان)
     */
    public function trashed()
    {
        $trashedGrades = Grade::onlyTrashed()->get();

        return response()->json([
            'success' => true,
            'data' => $trashedGrades
        ], 200);
    }

    /**
     * بازیابی پایه حذف شده
     */
    public function restore($id)
    {
        $grade = Grade::onlyTrashed()->find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'پایه حذف شده‌ای یافت نشد'
            ], 404);
        }

        $grade->restore();

        return response()->json([
            'success' => true,
            'message' => 'پایه با موفقیت بازیابی شد',
            'data' => $grade
        ], 200);
    }

    /**
     * حذف دائمی پایه
     */
    public function forceDelete($id)
    {
        $grade = Grade::onlyTrashed()->find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'پایه حذف شده‌ای یافت نشد'
            ], 404);
        }
        $grade->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'پایه برای همیشه حذف شد'
        ], 200);
    }
}
