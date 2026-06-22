<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Student\Database\Factories\MedicationFactory;

class Medication extends Model
{
    use HasFactory;

    protected $fillable = [
        'drug_name',
        'instructions',
        'time',
        'days',
        'student_id',
    ];
    public function student(){
        return $this->belongsTo(Student::class);
    }
}
