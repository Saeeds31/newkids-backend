<?php

namespace Modules\Consulting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Consulting\Models\Consulting;

class ConsultingController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'mobile'    => 'required|string|max:11|min:10',
            'subject'   => 'nullable|string|max:255',
            'body'      => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }
    
        // چک کردن تکراری بودن شماره موبایل
        $existingConsulting = Consulting::where('mobile', $request->mobile)->first();
    
        if ($existingConsulting) {
            return response()->json([
                'success' => false,
                'message' => 'شماره شما از قبل ثبت شده است',
                'data'    => [
                    'existing_record' => $existingConsulting,
                    'registered_at'   => $existingConsulting->created_at->format('Y-m-d H:i:s'),
                ],
            ], 409); // 409 Conflict
        }
    
        $consulting = Consulting::create([
            'full_name' => $request->full_name,
            'mobile'    => $request->mobile,
            'subject'   => $request->subject,
            'body'      => $request->body,
            'status'    => 'pending',
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'درخواست شما با موفقیت ثبت شد',
            'data'    => $consulting,
        ], 201);
    }

    /**
     * لیست درخواست‌ها برای ادمین (پنل مدیریت)
     */
    public function adminIndex(Request $request)
    {
        $query = Consulting::query();

        // فیلتر بر اساس وضعیت
        if ($request->has('status') && in_array($request->status, ['pending', 'seen', 'answered'])) {
            $query->where('status', $request->status);
        }

        // جستجو بر اساس نام یا موبایل
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile', 'LIKE', "%{$search}%");
            });
        }

        // مرتب‌سازی (جدیدترین اول)
        $consultings = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $consultings,
        ]);
    }

    public function adminUpdateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,seen,answered',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $consulting = Consulting::findOrFail($id);
        $consulting->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'وضعیت با موفقیت به‌روزرسانی شد',
            'data'    => $consulting,
        ]);
    }
    /**
     * مشاهده جزئیات یک درخواست (برای ادمین)
     */
    public function adminShow($id)
    {
        $consulting = Consulting::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $consulting,
        ]);
    }

    /**
     * تغییر وضعیت درخواست (برای ادمین)
     */
}
