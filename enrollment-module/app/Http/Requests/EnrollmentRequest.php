<?php

namespace App\Http\Requests;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form validation for creating (POST) and editing (PUT) an enrollment.
 * Rules that need the current seat counts are checked in EnrollmentService.
 * On edit, the student and term are fixed; only the status and sections change.
 */
class EnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'registrar') ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'status' => ['required', Rule::in(Enrollment::STATUSES)],
            'offerings' => ['required', 'array', 'min:1'],
            'offerings.*' => ['integer', 'distinct', 'exists:course_offerings,id'],
        ];

        if ($this->isMethod('POST')) {
            $rules += [
                'student_id' => [
                    'required', 'string', 'exists:students,student_id',
                    // One enrollment per student per term.
                    Rule::unique('enrollments', 'student_id')
                        ->where('semester', $this->input('semester'))
                        ->where('academic_year', $this->input('academic_year')),
                ],
                'semester' => ['required', Rule::in(CourseOffering::SEMESTERS)],
                'academic_year' => ['required', Rule::in(CourseOffering::ACADEMIC_YEARS)],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'student_id.unique' => 'This student is already enrolled for that term. Open the existing enrollment to change it.',
            'student_id.exists' => 'Choose a student from the list.',
            'offerings.required' => 'Select at least one subject.',
            'offerings.min' => 'Select at least one subject.',
        ];
    }

    public function attributes(): array
    {
        return ['student_id' => 'student', 'offerings' => 'subjects'];
    }
}
