<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Student\Database\Factories\MedicalInformationFactory;

class MedicalInformation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "height",
        "weight",
        "blood_type",
        "special_disease",
        "food_allergy",
        "drug_allergy",
        "skin_sensitivity",
        "sleep_time",
        "sleep_quality",
        "favorite_food",
        "unfavorite_food",
        "doctor_name",
        "doctor_phone",
        "emergency_phone",
        'student_id'
    ];
    protected $table = "medical_information";
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
