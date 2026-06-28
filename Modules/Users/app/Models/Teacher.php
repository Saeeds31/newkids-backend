<?php

namespace Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Expertise\Models\Expertise;

// use Modules\Users\Database\Factories\TeacherFactory;

class Teacher extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'national_code',
        'education',
        'education_field',
        'job_history',
        'user_id'
    ];
    protected $table = "teachers";
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expertises()
    {
        return $this->belongsToMany(Expertise::class, 'expertise_teacher');
    }
}
