<?php

namespace Modules\Interest\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Interest\Http\Requests\InterestStoreRequest;
use Modules\Interest\Http\Requests\InterestUpdateRequest;
use Modules\Interest\Models\Interest;
use Modules\Notifications\Services\NotificationService;

class InterestController extends Controller
{


    /**
     * نمایش لیست تمام علاقه‌ها
     */
    public function index()
    {
        $skills = Interest::all();
        return response()->json([
            'success' => true,
            'data' => $skills,
            'color_palette' => Interest::getColorPalette() // ارسال لیست رنگ‌ها به فرانت
        ], 200);
    }

    /**
     * ذخیره علاقه جدید
     */
    public function store(InterestStoreRequest $request, NotificationService $notifications)
    {
        $validated = $request->validated();

        // مدیریت آپلود آواتار دانش‌آموز
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon')->store('interests/icon', 'public');
            $validated['icon'] = $icon;
        }
        $interest = Interest::create($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "ثبت علاقه جدید",
            "علاقه {$interest->name} در سیستم ثبت شد",
            "notification_interest",
            [
                'interest_id' => $interest->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'علاقه با موفقیت ایجاد شد',
            'data' => $interest,
            'color_palette' => Interest::getColorPalette()
        ], 201);
    }

    /**
     * نمایش یک علاقه
     */
    public function show($id)
    {
        $interest = Interest::find($id);

        if (!$interest) {
            return response()->json([
                'success' => false,
                'message' => 'علاقه مورد نظر یافت نشد'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $interest,
        ], 200);
    }

    /**
     * بروزرسانی علاقه
     */
    public function update(InterestUpdateRequest $request, $id, NotificationService $notifications)
    {
        $interest = Interest::find($id);

        if (!$interest) {
            return response()->json([
                'success' => false,
                'message' => 'علاقه مورد نظر یافت نشد'
            ], 404);
        }
        $validated = $request->validated();
        if ($request->hasFile('icon')) {
            // حذف آواتار قبلی
            if ($interest->icon && Storage::disk('public')->exists($interest->avatar)) {
                Storage::disk('public')->delete($interest->avatar);
            }
            $validated['icon'] = $request->file('icon')->store('interest/icon', 'public');
        }
        $interest->update($validated);
        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "بروزرسانی علاقه",
            "علاقه {$interest->name} بروزرسانی شد",
            "notification_interest",
            [
                'interest_id' => $interest->id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'علاقه با موفقیت بروزرسانی شد',
            'data' => $interest,
        ], 200);
    }

    /**
     * حذف علاقه
     */
    public function destroy(Request $request, $id, NotificationService $notifications)
    {
        $interest = Interest::find($id);

        if (!$interest) {
            return response()->json([
                'success' => false,
                'message' => 'علاقه مورد نظر یافت نشد'
            ], 404);
        }

        $interestName = $interest->name;
        $interest->delete();

        // ثبت نوتیفیکیشن
        $maker = $request->user();
        $notifications->create(
            "حذف علاقه",
            "علاقه {$interestName} از سیستم حذف شد",
            "notification_interest",
            [
                'deleted_skill_id' => $id,
                'maker' => $maker->full_name
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'علاقه با موفقیت حذف شد'
        ], 200);
    }

    /**
     * دریافت لیست رنگ‌های ثابت (برای استفاده در فرانت)
     */
    public function getColorPalette()
    {
        return response()->json([
            'success' => true,
            'data' => Interest::getColorPalette()
        ], 200);
    }
}
