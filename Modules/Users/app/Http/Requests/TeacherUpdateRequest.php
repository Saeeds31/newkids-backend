<?php


namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;

class TeacherUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            // اطلاعات کاربری
            'first_name' => 'sometimes|required|string|max:50|min:2',
            'last_name' => 'sometimes|required|string|max:50|min:2',
            'mobile' => [
                'sometimes',
                'required',
                'string',
                'size:11',
                'regex:/^09[0-9]{9}$/',
                Rule::unique('users', 'mobile')->ignore($userId)
            ],
            'password' => 'nullable|string|min:6|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',

            // اطلاعات تخصصی معلم
            'national_code' => [
                'sometimes',
                'required',
                'string',
                'size:10',
                'regex:/^[0-9]{10}$/',
                Rule::unique('teachers', 'national_code')->ignore($userId, 'user_id')
            ],
            'education' => 'sometimes|required|string|max:255',
            'education_field' => 'sometimes|required|string|max:255',
            'job_history' => 'nullable|string|max:1000',

            // تخصص‌ها (آرایه‌ای از IDها)
            'expertise_ids' => 'nullable|array',
            'expertise_ids.*' => 'exists:expertises,id',
        ];
    }

    public function messages()
    {
        return [
            'national_code.unique' => 'این کد ملی قبلاً ثبت شده است',
            'national_code.size' => 'کد ملی باید ۱۰ رقم باشد',
            'education.required' => 'مدرک تحصیلی الزامی است',
            'education_field.required' => 'رشته تحصیلی الزامی است',
            'expertise_ids.*.exists' => 'تخصص انتخاب شده معتبر نیست',
        ];
    }
}
