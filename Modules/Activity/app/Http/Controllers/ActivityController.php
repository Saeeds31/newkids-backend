<?php

namespace Modules\Activity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Activity\Models\Activity;

class ActivityController extends Controller
{
    // لیست همه فعالیت‌ها
    public function index(Request $request)
    {
        $activities = Activity::with('user')
            ->when($request->get('user_id'), function ($query, $userId) {
                return $query->where('user_id', $userId);
            })
            ->when($request->get('model'), function ($query, $model) {
                return $query->where('model', $model);
            })
            ->when($request->get('action'), function ($query, $action) {
                return $query->where('action', $action);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    // نمایش فعالیت‌های یک مدل خاص
    public function getModelActivities($model, $modelId)
    {
        $activities = Activity::with('user')
            ->forModel($model, $modelId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    // نمایش فعالیت‌های یک کاربر خاص
    public function getUserActivities(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر مورد نظر یافت نشد'
            ], 404);
        }

        $activities = Activity::with('user')
            ->forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }
}
