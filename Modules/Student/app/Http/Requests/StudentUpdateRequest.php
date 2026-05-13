<?php

namespace Modules\Student\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Student\Models\Student;

class StudentUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        $studentId = $this->route('student');
        $parentId = null;

        // اگر دانش‌آموز وجود داشت، parent_id رو بگیر
        if ($studentId) {
            $student = Student::find($studentId);
            if ($student) {
                $parentId = $student->parent_id;
            }
        }

        return [
            // فیلدهای دانش‌آموز
            'first_name' => 'sometimes|required|string|max:50|min:2',
            'last_name' => 'sometimes|required|string|max:50|min:2',
            'national_code' => [
                'sometimes',
                'required',
                'string',
                'size:10',
                'regex:/^\d{10}$/',
                Rule::unique('students', 'national_code')->ignore($studentId)
            ],
            'class_id' => 'sometimes|required|exists:classes,id',
            'birth_date' => 'sometimes|required|date|before:today',
            'student_avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',

            // فیلدهای والد (کاربر)
            'parent_first_name' => 'sometimes|required|string|max:50|min:2',
            'parent_last_name' => 'sometimes|required|string|max:50|min:2',
            'parent_mobile' => [
                'sometimes',
                'required',
                'string',
                'size:11',
                'regex:/^09[0-9]{9}$/',
                Rule::unique('users', 'mobile')->ignore($parentId)
            ],
            'parent_password' => 'nullable|string|min:6|confirmed',
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
