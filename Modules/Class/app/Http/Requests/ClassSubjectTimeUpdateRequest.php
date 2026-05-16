<?php

namespace Modules\Class\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassSubjectTimeUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'class_id' => 'sometimes|required|exists:classes,id',
            'teacher_id' => 'sometimes|required|exists:users,id',
            'subject_id' => 'sometimes|required|exists:subjects,id',
            'day_of_week' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:7',
                Rule::in([1, 2, 3, 4, 5, 6, 7])
            ],
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
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
