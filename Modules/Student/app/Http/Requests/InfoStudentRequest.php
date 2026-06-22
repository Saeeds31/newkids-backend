<?php

namespace Modules\Student\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InfoStudentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'nickname' => ['nullable', 'string', 'max:255'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_phone' => ['nullable', 'string', 'max:20'], // یا 'regex:/^[0-9]+$/'
            'father_job_name' => ['nullable', 'string', 'max:255'],
            'father_education' => ['nullable', 'string', 'max:255'],
            'mother_education' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_phone' => ['nullable', 'string', 'max:20'],
            'mother_job_name' => ['nullable', 'string', 'max:255'],
            'number_of_siblings' => ['nullable', 'integer', 'min:0', 'max:50'],
            'birth_order_of_the_child' => ['nullable', 'integer', 'min:1', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'student_id' => ['required', 'exists:students,id'],
        ];
        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
