<?php

namespace Modules\Skills\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Skills\Models\Skills;

class SkillsUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        $skillId = $this->route('id');
        return [

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('traits', 'name')->ignore($skillId)
            ],
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z_]+$/',
                Rule::unique('traits', 'key')->ignore($skillId)
            ],
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color_code' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function ($attribute, $value, $fail) {
                    if (!Skills::isValidColor($value)) {
                        $fail('کد رنگ انتخاب شده معتبر نیست. از رنگ‌های موجود استفاده کنید.');
                    }
                },
            ],
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
