<?php

namespace Modules\Traits\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Notifications\Services\NotificationService;
use Modules\Traits\Http\Requests\TraitsStoreRequest;
use Modules\Traits\Http\Requests\TraitsUpdateRequest;
use Modules\Traits\Models\Traits;

class TraitsController extends Controller
{
    /**
     * نمایش لیست تمام ویژگی‌ها
     */
    public function index()
    {
        $traits = Traits::all();
        return response()->json([
            'success' => true,
            'data' => $traits,
            'color_palette' => Traits::getColorPalette() // ارسال لیست رنگ‌ها به فرانت
        ], 200);
    }

    /**
     * ذخیره ویژگی جدید
     */
    public function store(TraitsStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        // مدیریت آپلود آواتار دانش‌آموز
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon')->store('traits/icon', 'public');
            $validated['icon'] = $icon;
        }
        $trait = Traits::create($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "ثبت ویژگی جدید",
            "ویژگی {$trait->name} در سیستم ثبت شد",
            "notification_trait",
            [
                'trait_id' => $trait->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'ویژگی با موفقیت ایجاد شد',
            'data' => $trait,
            'color_palette' => Traits::getColorPalette()
        ], 201);
    }

    /**
     * نمایش یک ویژگی
     */
    public function show($id)
    {
        $trait = Traits::find($id);

        if (!$trait) {
            return response()->json([
                'success' => false,
                'message' => 'ویژگی مورد نظر یافت نشد'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $trait,
        ], 200);
    }

    /**
     * بروزرسانی ویژگی
     */
    public function update(TraitsUpdateRequest $request, $id, NotificationService $notifications)
    {
        $trait = Traits::find($id);

        if (!$trait) {
            return response()->json([
                'success' => false,
                'message' => 'ویژگی مورد نظر یافت نشد'
            ], 404);
        }
        $validated = $request->validated();
        if ($request->hasFile('icon')) {
            // حذف آواتار قبلی
            if ($trait->icon && Storage::disk('public')->exists($trait->avatar)) {
                Storage::disk('public')->delete($trait->avatar);
            }
            $validated['icon'] = $request->file('icon')->store('traits/icon', 'public');
        }
        $trait->update($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی ویژگی",
            "ویژگی {$trait->name} بروزرسانی شد",
            "notification_trait",
            [
                'trait_id' => $trait->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'ویژگی با موفقیت بروزرسانی شد',
            'data' => $trait,
        ], 200);
    }

    /**
     * حذف ویژگی
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $trait = Traits::find($id);

        if (!$trait) {
            return response()->json([
                'success' => false,
                'message' => 'ویژگی مورد نظر یافت نشد'
            ], 404);
        }

        $traitName = $trait->name;
        $trait->delete();

        // ثبت نوتیفیکیشن
        $maker = request()->user();
        $notifications->create(
            "حذف ویژگی",
            "ویژگی {$traitName} از سیستم حذف شد",
            "notification_trait",
            [
                'deleted_trait_id' => $id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'ویژگی با موفقیت حذف شد'
        ], 200);
    }

    /**
     * دریافت لیست رنگ‌های ثابت (برای استفاده در فرانت)
     */
    public function getColorPalette()
    {
        return response()->json([
            'success' => true,
            'data' => Traits::getColorPalette()
        ], 200);
    }
}
