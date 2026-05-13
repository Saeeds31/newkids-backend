<?php

namespace Modules\Student\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50|min:2',
            'last_name' => 'required|string|max:50|min:2',
            'national_code' => 'required|string|size:10|regex:/^\d{10}$/|unique:students,national_code',
            'class_id' => 'required|exists:classes,id',
            'birth_date' => 'required|date|before:today',
            'student_avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            
            // فیلدهای والد (کاربر)
            'parent_first_name' => 'required|string|max:50|min:2',
            'parent_last_name' => 'required|string|max:50|min:2',
            'parent_mobile' => 'required|string|size:11|regex:/^09[0-9]{9}$/|unique:users,mobile',
            'parent_password' => 'required|string|min:6|confirmed',
            'parent_avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
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
