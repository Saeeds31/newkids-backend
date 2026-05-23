<?php

namespace Modules\Activity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Users\Models\User;

// use Modules\Activity\Database\Factories\ActivityFactory;

class Activity extends Model
{
    protected $table = 'activities';

    protected $fillable = [
        'user_id',
        'model',
        'model_id',
        'action',
        'description',
    ];

    // رابطه با کاربر
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // سکوپ برای فیلتر بر اساس مدل
    public function scopeForModel($query, $model, $modelId = null)
    {
        $query->where('model', $model);
        
        if ($modelId) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    // سکوپ برای فیلتر بر اساس کاربر
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // سکوپ برای فیلتر بر اساس عملیات
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }
}
