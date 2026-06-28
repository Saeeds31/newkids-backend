<?php

namespace Modules\Expertise\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Expertise\Http\Requests\ExpertiseStoreRequest;
use Modules\Expertise\Http\Requests\ExpertiseUpdateRequest;
use Modules\Expertise\Models\Expertise;
use Modules\Notifications\Services\NotificationService;

class ExpertiseController extends Controller
{
   
    /**
     * نمایش لیست تمام تخصص‌ها
     */
    public function index()
    {
        $expertises = Expertise::all();
        return response()->json([
            'success' => true,
            'data' => $expertises,
            'color_palette' => Expertise::getColorPalette() // ارسال لیست رنگ‌ها به فرانت
        ], 200);
    }

    /**
     * ذخیره تخصص جدید
     */
    public function store(ExpertiseStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        // مدیریت آپلود آواتار دانش‌آموز
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon')->store('expertises/icon', 'public');
            $validated['icon'] = $icon;
        }
        $expertise = Expertise::create($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "ثبت تخصص جدید",
            "تخصص {$expertise->name} در سیستم ثبت شد",
            "notification_expertise",
            [
                'expertise_id' => $expertise->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تخصص با موفقیت ایجاد شد',
            'data' => $expertise,
            'color_palette' => Expertise::getColorPalette()
        ], 201);
    }

    /**
     * نمایش یک تخصص
     */
    public function show($id)
    {
        $expertise = Expertise::find($id);

        if (!$expertise) {
            return response()->json([
                'success' => false,
                'message' => 'تخصص مورد نظر یافت نشد'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $expertise,
        ], 200);
    }

    /**
     * بروزرسانی تخصص
     */
    public function update(ExpertiseUpdateRequest $request, $id, NotificationService $notifications)
    {
        $expertise = Expertise::find($id);

        if (!$expertise) {
            return response()->json([
                'success' => false,
                'message' => 'تخصص مورد نظر یافت نشد'
            ], 404);
        }
        $validated = $request->validated();
        if ($request->hasFile('icon')) {
            // حذف آواتار قبلی
            if ($expertise->icon && Storage::disk('public')->exists($expertise->avatar)) {
                Storage::disk('public')->delete($expertise->avatar);
            }
            $validated['icon'] = $request->file('icon')->store('expertise/icon', 'public');
        }
        $expertise->update($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی تخصص",
            "تخصص {$expertise->name} بروزرسانی شد",
            "notification_expertise",
            [
                'expertise_id' => $expertise->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تخصص با موفقیت بروزرسانی شد',
            'data' => $expertise,
        ], 200);
    }

    /**
     * حذف تخصص
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $expertise = Expertise::find($id);

        if (!$expertise) {
            return response()->json([
                'success' => false,
                'message' => 'تخصص مورد نظر یافت نشد'
            ], 404);
        }

        $expertiseName = $expertise->name;
        $expertise->delete();

        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "حذف تخصص",
            "تخصص {$expertiseName} از سیستم حذف شد",
            "notification_expertise",
            [
                'deleted_skill_id' => $id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تخصص با موفقیت حذف شد'
        ], 200);
    }

    /**
     * دریافت لیست رنگ‌های ثابت (برای استفاده در فرانت)
     */
    public function getColorPalette()
    {
        return response()->json([
            'success' => true,
            'data' => Expertise::getColorPalette()
        ], 200);
    }
}
