<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50|min:2',
            'last_name' => 'required|string|max:50|min:2',
            'mobile' => 'required|string|size:11|regex:/^09[0-9]{9}$/|unique:users,mobile',
            'password' => 'required|string|min:6|confirmed',
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'national_code' => 'required|string|size:10|regex:/^[0-9]{10}$/|unique:teachers,national_code',
            'education' => 'required|string|max:255',
            'education_field' => 'required|string|max:255',
            'job_history' => 'nullable|string|max:1000',
            'expertise_ids' => 'nullable|array',
            'expertise_ids.*' => 'exists:expertises,id',
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
