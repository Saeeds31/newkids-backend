<?php

namespace Modules\Concern\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concern\Models\Concern;

class concernStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:concerns,name',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|file|max:1024',
            'color_code' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function ($cc, $value, $fail) {
                    if (!Concern::isValidColor($value)) {
                        $fail('کد رنگ انتخاب شده معتبر نیست. از رنگ‌های موجود استفاده کنید.');
                    }
                },
            ]
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
