<?php

namespace Modules\Student\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalStudentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'height' => ['required', 'integer', 'min:30', 'max:250'], // سانتی‌متر
            'weight' => ['required', 'integer', 'min:5', 'max:200'], // کیلوگرم
            'blood_type' => ['required', 'integer', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'], 
            'special_disease' => ['nullable', 'string', 'max:500'],
            'food_allergy' => ['nullable', 'string', 'max:500'],
            'drug_allergy' => ['nullable', 'string', 'max:500'],
            'skin_sensitivity' => ['nullable', 'string', 'max:500'],
            'sleep_time' => ['nullable', 'string', 'max:50'], // مثلاً "22:00 to 06:00"
            'sleep_quality' => ['nullable', 'string', 'max:100'], // مثلاً "good", "medium", "bad"
            'favorite_food' => ['nullable', 'string', 'max:500'],
            'unfavorite_food' => ['nullable', 'string', 'max:500'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_phone' => ['nullable', 'string', 'max:20'], // شماره تلفن
            'emergency_phone' => ['nullable', 'string', 'max:20'], // شماره اضطراری
            'student_id' => ['required', 'exists:students,id'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
