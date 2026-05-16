<?php

namespace Modules\Class\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassSubjectTimeStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'class_id' => 'required|exists:classes,id',
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'day_of_week' => [
                'required',
                'integer',
                'min:1',
                'max:7',
                Rule::in([1, 2, 3, 4, 5, 6, 7])
            ],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
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
