<?php

namespace Modules\Interest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Interest\Models\Interest;

class InterestUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $interestID = $this->route('id');
        return [

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('traits', 'name')->ignore($interestID)
            ],
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z_]+$/',
                Rule::unique('traits', 'key')->ignore($interestID)
            ],
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color_code' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function ($attribute, $value, $fail) {
                    if (!Interest::isValidColor($value)) {
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
