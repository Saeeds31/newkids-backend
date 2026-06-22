<?php

namespace Modules\Student\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DrugStudentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'drug_name' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:1000'],
            'time' => ['required', 'string', 'max:100'], // مثلاً: "صبح", "ظهر", "شب", "هر 8 ساعت"
            'days' => ['required', 'string', 'max:255'], // مثلاً: "شنبه تا چهارشنبه", "روزهای فرد", "1 تا 10 ماه"
            'student_id' => ['required', 'exists:students,id'],
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
