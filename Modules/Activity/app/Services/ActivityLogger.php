<?php

namespace Modules\Activity\Services;

use Modules\Activity\Models\Activity;

class ActivityLogger
{
    public static function log($model, $action, $description = null)
    {
        return Activity::create([
            'user_id' => auth()->id(),
            'model' => get_class($model),
            'model_id' => $model->id,
            'action' => $action,
            'description' => $description,
        ]);
    }

    // متدهای کمکی برای عملیات رایج
    public static function created($model, $description = null)
    {
        $desc = $description ?? "ایجاد شد";
        return self::log($model, 'create', $desc);
    }

    public static function updated($model, $description = null)
    {
        $desc = $description ?? "بروزرسانی شد";
        return self::log($model, 'update', $desc);
    }

    public static function deleted($model, $description = null)
    {
        $desc = $description ?? "حذف شد";
        return self::log($model, 'delete', $desc);
    }
}
