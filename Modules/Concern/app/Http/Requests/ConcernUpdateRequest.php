<?php

namespace Modules\Concern\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Concern\Models\Concern;

class concernUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $concernID = $this->route('id');
        return [

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('concerns', 'name')->ignore($concernID)
            ],
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z_]+$/',
                Rule::unique('concerns', 'key')->ignore($concernID)
            ],
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color_code' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function ($cc, $value, $fail) {
                    if (!Concern::isValidColor($value)) {
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
