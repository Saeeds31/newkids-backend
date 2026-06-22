<?php

namespace Modules\Interest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Interest\Models\Interest;

class InterestStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:traits,name',
            'key' => 'required|string|max:50|unique:traits,key|regex:/^[a-z_]+$/',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|file|max:1024',
            'color_code' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function ($attribute, $value, $fail) {
                    if (!Interest::isValidColor($value)) {
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
