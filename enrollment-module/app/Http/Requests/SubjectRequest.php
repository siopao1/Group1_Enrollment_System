<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for adding and editing a subject (runs before the controller). */
class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /** Normalize before validating: "it 101" → "IT 101". */
    protected function prepareForValidation(): void
    {
        $code = preg_replace('/\s+/', ' ', strtoupper(trim((string) $this->input('code'))));
        $this->merge([
            'code' => $code,
            'prerequisite_id' => $this->input('prerequisite_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z]{2,5} ?[0-9]{2,4}[A-Z]?$/',
                Rule::unique('subjects', 'code')->ignore($subject),
            ],
            'name' => ['required', 'string', 'max:120'],
            'units' => ['required', 'integer', 'between:1,6'],
            'department' => ['required', Rule::in(Subject::DEPARTMENTS)],
            'description' => ['nullable', 'string', 'max:1000'],
            'prerequisite_id' => [
                'nullable', 'integer', 'exists:subjects,id',
                // A subject cannot be its own prerequisite.
                Rule::notIn(array_filter([$subject?->id])),
            ],
            'status' => ['required', Rule::in(Subject::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The code must look like "IT 101" (letters, a space, then digits).',
            'prerequisite_id.not_in' => 'A subject cannot be its own prerequisite.',
        ];
    }

    public function attributes(): array
    {
        return ['prerequisite_id' => 'prerequisite'];
    }
}
