<?php

namespace Modules\Skills\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Notifications\Services\NotificationService;
use Modules\Skills\Http\Requests\SkillsStoreRequest;
use Modules\Skills\Http\Requests\SkillsUpdateRequest;
use Modules\Skills\Models\Skills;

class SkillsController extends Controller
{
  
    /**
     * نمایش لیست تمام مهارت‌ها
     */
    public function index()
    {
        $skills = Skills::all();
        return response()->json([
            'success' => true,
            'data' => $skills,
            'color_palette' => Skills::getColorPalette() // ارسال لیست رنگ‌ها به فرانت
        ], 200);
    }

    /**
     * ذخیره مهارت جدید
     */
    public function store(SkillsStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        // مدیریت آپلود آواتار دانش‌آموز
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon')->store('skills/icon', 'public');
            $validated['icon'] = $icon;
        }
        $skill = Skills::create($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "ثبت مهارت جدید",
            "مهارت {$skill->name} در سیستم ثبت شد",
            "notification_skill",
            [
                'skill_id' => $skill->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'مهارت با موفقیت ایجاد شد',
            'data' => $skill,
            'color_palette' => Skills::getColorPalette()
        ], 201);
    }

    /**
     * نمایش یک مهارت
     */
    public function show($id)
    {
        $skill = Skills::find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'مهارت مورد نظر یافت نشد'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $skill,
        ], 200);
    }

    /**
     * بروزرسانی مهارت
     */
    public function update(SkillsUpdateRequest $request, $id, NotificationService $notifications)
    {
        $skill = Skills::find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'مهارت مورد نظر یافت نشد'
            ], 404);
        }
        $validated = $request->validated();
        if ($request->hasFile('icon')) {
            // حذف آواتار قبلی
            if ($skill->icon && Storage::disk('public')->exists($skill->avatar)) {
                Storage::disk('public')->delete($skill->avatar);
            }
            $validated['icon'] = $request->file('icon')->store('skills/icon', 'public');
        }
        $skill->update($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی مهارت",
            "مهارت {$skill->name} بروزرسانی شد",
            "notification_skill",
            [
                'skill_id' => $skill->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'مهارت با موفقیت بروزرسانی شد',
            'data' => $skill,
        ], 200);
    }

    /**
     * حذف مهارت
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $skill = Skills::find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'مهارت مورد نظر یافت نشد'
            ], 404);
        }

        $skillName = $skill->name;
        $skill->delete();

        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "حذف مهارت",
            "مهارت {$skillName} از سیستم حذف شد",
            "notification_skill",
            [
                'deleted_skill_id' => $id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'مهارت با موفقیت حذف شد'
        ], 200);
    }

    /**
     * دریافت لیست رنگ‌های ثابت (برای استفاده در فرانت)
     */
    public function getColorPalette()
    {
        return response()->json([
            'success' => true,
            'data' => Skills::getColorPalette()
        ], 200);
    }
}
