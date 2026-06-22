<?php

namespace Modules\BehavioralInformation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\BehavioralInformation\Database\Factories\BehavioralInformationFactory;

class BehavioralInformation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'mobile_time',
        'tv_time',
        'shy',
        'sensitivity',
        'aggression',
        'fear',
        'shy',
        'anxiety',
        'Dependence_parent',
        'find_firends',
        'express_fear',
        'express_anger',
        'React_not',
    ];
    protected $table = "behavioral_information";
}
