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
