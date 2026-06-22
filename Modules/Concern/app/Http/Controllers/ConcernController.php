<?php

namespace Modules\Concern\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Concern\Http\Requests\concernStoreRequest;
use Modules\Concern\Http\Requests\concernUpdateRequest;
use Modules\Concern\Models\Concern;
use Modules\Notifications\Services\NotificationService;

class ConcernController extends Controller
{


    /**
     * نمایش لیست تمام دغدغه‌ها
     */
    public function index()
    {
        $concerns = Concern::all();
        return response()->json([
            'success' => true,
            'data' => $concerns,
            'color_palette' => Concern::getColorPalette() // ارسال لیست رنگ‌ها به فرانت
        ], 200);
    }

    /**
     * ذخیره دغدغه جدید
     */
    public function store(concernStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon')->store('concerns/icon', 'public');
            $validated['icon'] = $icon;
        }
        $concern = Concern::create($validated);
        $maker = $request->user();
        $notifications->create(
            "ثبت دغدغه جدید",
            "دغدغه {$concern->name} در سیستم ثبت شد",
            "notification_concern",
            [
                'concern_id' => $concern->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'دغدغه با موفقیت ایجاد شد',
            'data' => $concern,
            'color_palette' => Concern::getColorPalette()
        ], 201);
    }

    /**
     * نمایش یک دغدغه
     */
    public function show($id)
    {
        $concern = Concern::find($id);

        if (!$concern) {
            return response()->json([
                'success' => false,
                'message' => 'دغدغه مورد نظر یافت نشد'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $concern,
        ], 200);
    }

    /**
     * بروزرسانی دغدغه
     */
    public function update(concernUpdateRequest $request, $id, NotificationService $notifications)
    {
        $concern = Concern::find($id);

        if (!$concern) {
            return response()->json([
                'success' => false,
                'message' => 'دغدغه مورد نظر یافت نشد'
            ], 404);
        }
        $validated = $request->validated();
        if ($request->hasFile('icon')) {
            // حذف آواتار قبلی
            if ($concern->icon && Storage::disk('public')->exists($concern->avatar)) {
                Storage::disk('public')->delete($concern->avatar);
            }
            $validated['icon'] = $request->file('icon')->store('concerns/icon', 'public');
        }
        $concern->update($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی دغدغه",
            "دغدغه {$concern->name} بروزرسانی شد",
            "notification_concern",
            [
                'concern_id' => $concern->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'دغدغه با موفقیت بروزرسانی شد',
            'data' => $concern,
        ], 200);
    }

    /**
     * حذف دغدغه
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $concern = Concern::find($id);

        if (!$concern) {
            return response()->json([
                'success' => false,
                'message' => 'دغدغه مورد نظر یافت نشد'
            ], 404);
        }

        $concernName = $concern->name;
        $concern->delete();

        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "حذف دغدغه",
            "دغدغه {$concernName} از سیستم حذف شد",
            "notification_concern",
            [
                'deleted_concern_id' => $id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'دغدغه با موفقیت حذف شد'
        ], 200);
    }

    /**
     * دریافت لیست رنگ‌های ثابت (برای استفاده در فرانت)
     */
    public function getColorPalette()
    {
        return response()->json([
            'success' => true,
            'data' => Concern::getColorPalette()
        ], 200);
    }
}
