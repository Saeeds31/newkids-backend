<?php

namespace Modules\Attribute\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Attribute\Http\Requests\attributesStoreRequest;
use Modules\Attribute\Http\Requests\attributesUpdateRequest;
use Modules\Attribute\Models\Attribute;
use Modules\Notifications\Services\NotificationService;

class AttributeController extends Controller
{


    /**
     * نمایش لیست تمام ویژگی‌ها
     */
    public function index()
    {
        $attributes = Attribute::all();
        return response()->json([
            'success' => true,
            'data' => $attributes,
            'color_palette' => Attribute::getColorPalette() // ارسال لیست رنگ‌ها به فرانت
        ], 200);
    }

    /**
     * ذخیره ویژگی جدید
     */
    public function store(attributesStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        // مدیریت آپلود آواتار دانش‌آموز
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon')->store('attributes/icon', 'public');
            $validated['icon'] = $icon;
        }
        $attribute = Attribute::create($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "ثبت ویژگی جدید",
            "ویژگی {$attribute->name} در سیستم ثبت شد",
            "notification_attribute",
            [
                'attribute_id' => $attribute->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'ویژگی با موفقیت ایجاد شد',
            'data' => $attribute,
            'color_palette' => Attribute::getColorPalette()
        ], 201);
    }

    /**
     * نمایش یک ویژگی
     */
    public function show($id)
    {
        $attribute = Attribute::find($id);

        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'ویژگی مورد نظر یافت نشد'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $attribute,
        ], 200);
    }

    /**
     * بروزرسانی ویژگی
     */
    public function update(attributesUpdateRequest $request, $id, NotificationService $notifications)
    {
        $attribute = Attribute::find($id);

        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'ویژگی مورد نظر یافت نشد'
            ], 404);
        }
        $validated = $request->validated();
        if ($request->hasFile('icon')) {
            // حذف آواتار قبلی
            if ($attribute->icon && Storage::disk('public')->exists($attribute->avatar)) {
                Storage::disk('public')->delete($attribute->avatar);
            }
            $validated['icon'] = $request->file('icon')->store('attributes/icon', 'public');
        }
        $attribute->update($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی ویژگی",
            "ویژگی {$attribute->name} بروزرسانی شد",
            "notification_attribute",
            [
                'attribute_id' => $attribute->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'ویژگی با موفقیت بروزرسانی شد',
            'data' => $attribute,
        ], 200);
    }

    /**
     * حذف ویژگی
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $attribute = Attribute::find($id);

        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'ویژگی مورد نظر یافت نشد'
            ], 404);
        }

        $attributeName = $attribute->name;
        $attribute->delete();

        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "حذف ویژگی",
            "ویژگی {$attributeName} از سیستم حذف شد",
            "notification_attribute",
            [
                'deleted_attribute_id' => $id,
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
            'data' => Attribute::getColorPalette()
        ], 200);
    }
}
