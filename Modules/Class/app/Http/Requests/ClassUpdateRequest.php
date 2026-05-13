<?php

namespace Modules\Class\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $classId = $this->route('id');
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('classes', 'name')->ignore($classId)
            ],
            'grade_id' => 'required|exists:grades,id',
            'academic_year' => 'required|string|size:9|regex:/^\d{4}-\d{4}$/', // 1403-1404
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048'
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
