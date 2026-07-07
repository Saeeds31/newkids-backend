<?php

namespace Modules\Consulting\Models;


use Illuminate\Database\Eloquent\Model;

class Consulting extends Model
{
    protected $table = 'consultings';

    protected $fillable = [
        'full_name',
        'mobile',
        'subject',
        'body',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];
}
