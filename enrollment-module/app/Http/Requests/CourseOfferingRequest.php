<?php

namespace App\Http\Requests;

use App\Models\CourseOffering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validation for adding and editing a course offering (section). */
class CourseOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['section' => strtoupper(trim((string) $this->input('section')))]);
    }

    public function rules(): array
    {
        $offering = $this->route('offering');

        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'section' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/',
                // One section code per subject per term.
                Rule::unique('course_offerings', 'section')
                    ->where('subject_id', $this->input('subject_id'))
                    ->where('semester', $this->input('semester'))
                    ->where('academic_year', $this->input('academic_year'))
                    ->ignore($offering),
            ],
            'semester' => ['required', Rule::in(CourseOffering::SEMESTERS)],
            'academic_year' => ['required', Rule::in(CourseOffering::ACADEMIC_YEARS)],
            'instructor' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:40'],
            'schedule' => ['nullable', 'string', 'max:100'],
            'slots' => ['required', 'integer', 'between:1,500'],
            'status' => ['required', Rule::in(CourseOffering::STATUSES)],
        ];
    }

    /** Extra rules that need the database. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $offering = $this->route('offering');
                if (!$offering) {
                    return;
                }
                // Slots cannot drop below the students already holding a seat.
                $enrolled = $offering->enrolledCount();
                if ((int) $this->input('slots') < $enrolled) {
                    $validator->errors()->add('slots', "Slots cannot be lower than the {$enrolled} students already enrolled.");
                }
                // An offering with enrollments cannot move to another term or subject.
                $moved = (int) $this->input('subject_id') !== $offering->subject_id
                    || $this->input('semester') !== $offering->semester
                    || $this->input('academic_year') !== $offering->academic_year;
                if ($moved && $offering->enrollments()->exists()) {
                    $validator->errors()->add('subject_id', 'Students are enrolled in this section, so its subject and term cannot change.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'section.unique' => 'This subject already has that section in the selected term.',
            'section.regex' => 'The section may only contain letters, numbers and hyphens.',
        ];
    }

    public function attributes(): array
    {
        return ['subject_id' => 'subject'];
    }
}
