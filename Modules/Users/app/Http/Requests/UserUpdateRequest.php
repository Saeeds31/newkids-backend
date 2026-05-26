<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name'     => ['sometimes', 'string', 'max:255'],
            'last_name'     => ['sometimes', 'string', 'max:255'],
            'mobile'        => ['sometimes', 'string', 'size:11', Rule::unique('users', 'mobile')->ignore($this->route('user'))],
            'password'      => ['sometimes', 'string', 'min:6'],
            'avatar' => ['sometimes', 'file', 'max:1024'],
            'is_active'    => ['sometimes', 'boolean'],
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
