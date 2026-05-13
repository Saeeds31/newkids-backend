<?php

namespace Modules\Class\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Class\Http\Requests\ClassStoreRequest;
use Modules\Class\Http\Requests\ClassUpdateRequest;
use Modules\Class\Models\Classes;
use Modules\Notifications\Services\NotificationService;


class ClassController extends Controller
{
    /**
     * نمایش لیست تمام کلاس‌ها
     */
    public function index()
    {
        $classes = Classes::with(['grade'])->get();

        return response()->json([
            'success' => true,
            'data' => $classes
        ], 200);
    }

    /**
     * ذخیره کلاس جدید
     */
    public function store(ClassStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('class', 'public');
        }

        $class = Classes::create($validated);
        $class->load('grade');
        $maker = $request->user();
        $notifications->create(
            "ثبت کلاس جدید",
            "یک کلاس جدید به نام {$class->name} در سیستم ثبت شد",
            "notification_class",
            [
                'class' => $class->id,
                'maker' => $maker->full_name,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'کلاس با موفقیت ایجاد شد',
            'data' => $class
        ], 201);
    }

    /**
     * نمایش یک کلاس خاص
     */
    public function show($id)
    {
        $class = Classes::with('grade')->find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'کلاس مورد نظر یافت نشد'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $class
        ], 200);
    }

    /**
     * بروزرسانی کلاس
     */
    public function update(ClassUpdateRequest $request, $id, NotificationService $notifications)
    {
        $class = Classes::find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'کلاس مورد نظر یافت نشد'
            ], 404);
        }
        $validated = $request->validated();
        if ($request->hasFile('image')) {
            if ($class->image && Storage::disk('public')->exists($class->image)) {
                Storage::disk('public')->delete($class->image);
            }
            $validated['image'] = $request->file('image')->store('class', 'public');
        }

        // بروزرسانی کلاس
        $class->update($validated);
        $class->load('grade');
        // ثبت نوتیفیکیشن برای بروزرسانی
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی کلاس",
            "کلاس {$class->name} بروزرسانی شد",
            "notification_class",
            [
                'class' => $class->id,
                'maker' => $maker->full_name,
                'updated_fields' => array_keys($validated)
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'کلاس با موفقیت بروزرسانی شد',
            'data' => $class
        ], 200);
    }

    /**
     * حذف نرم کلاس
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $class = Classes::find($id);
        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'کلاس مورد نظر یافت نشد'
            ], 404);
        }
        if ($class->students()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'در این کلاس دانش آموز ثبت شده و قابل حذف نیست'
            ], 403);
        }
        $className = $class->name;
        if ($class->image) {
            Storage::disk('public')->delete($class->image);
        }
        $class->delete();
        $maker = $request->user();
        $notifications->create(
            "حذف کلاس",
            "کلاس {$className} از سیستم حذف شد",
            "notification_class",
            [
                'deleted_class' => $class->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'کلاس با موفقیت حذف شد'
        ], 200);
    }
}
