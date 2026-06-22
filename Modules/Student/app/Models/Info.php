<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Student\Database\Factories\InfoFactory;

class Info extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'nickname',
        'place_of_birth',
        'gender',
        'father_name',
        'father_phone',
        'father_job_name',
        'father_education',
        'mother_education',
        'mother_name',
        'mother_phone',
        'mother_job_name',
        'number_of_siblings',
        'birth_order_of_the_child',
        'address',
        'latitude',
        'longitude',
        'student_id'
    ];
    protected $table = "info";

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
