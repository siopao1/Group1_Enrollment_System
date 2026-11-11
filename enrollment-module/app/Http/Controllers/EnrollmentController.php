<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentRequest;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Enroll Student and Enrollment Records.
 * Admin and registrar create, view and edit; only admin deletes (routes/web.php).
 */
class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => $request->query('q'),
            'semester' => $request->query('semester'),
            'academic_year' => $request->query('academic_year', CourseOffering::ACADEMIC_YEARS[0]),
            'status' => $request->query('status'),
            'program' => $request->query('program'),
        ];

        $enrollments = Enrollment::query()
            ->with(['student.program', 'offerings.subject'])
            ->when($filters['q'], fn ($q, $term) => $q->whereHas('student', fn ($s) => $s
                ->where('student_id', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")))
            ->when($filters['semester'], fn ($q, $s) => $q->where('semester', $s))
            ->when($filters['academic_year'], fn ($q, $y) => $q->where('academic_year', $y))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['program'], fn ($q, $p) => $q->whereHas('student', fn ($s) => $s->where('program_id', $p)))
            ->latest('enrolled_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('enrollments.index', [
            'enrollments' => $enrollments,
            'filters' => $filters,
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    /** Step 1: pick the term (GET ?semester=&academic_year=). Step 2: pick student and sections. */
    public function create(Request $request): View
    {
        $semester = in_array($request->query('semester'), CourseOffering::SEMESTERS, true)
            ? $request->query('semester') : CourseOffering::SEMESTERS[0];
        $academicYear = in_array($request->query('academic_year'), CourseOffering::ACADEMIC_YEARS, true)
            ? $request->query('academic_year') : CourseOffering::ACADEMIC_YEARS[0];

        $enrolledThisTerm = Enrollment::where('semester', $semester)
            ->where('academic_year', $academicYear)
            ->pluck('student_id')->all();

        return view('enrollments.create', [
            'semester' => $semester,
            'academicYear' => $academicYear,
            'students' => Student::with('program')->orderBy('last_name')->orderBy('first_name')->get(),
            'enrolledThisTerm' => $enrolledThisTerm,
            'selectedStudent' => $request->query('student'),
            'offerings' => $this->offeringsForTerm($semester, $academicYear),
        ]);
    }

    public function store(EnrollmentRequest $request): RedirectResponse
    {
        $enrollment = $this->service->create($request->validated(), $request->user())->load('student');

        return redirect()->route('enrollments.show', $enrollment)
            ->with('status', "{$enrollment->student->full_name} is enrolled for {$enrollment->semester} {$enrollment->academic_year}.");
    }

    public function show(Enrollment $enrollment): View
    {
        $enrollment->load(['student.program', 'offerings.subject', 'enrolledBy']);

        return view('enrollments.show', compact('enrollment'));
    }

    public function edit(Enrollment $enrollment): View
    {
        $enrollment->load(['student.program', 'offerings']);

        return view('enrollments.edit', [
            'enrollment' => $enrollment,
            'offerings' => $this->offeringsForTerm($enrollment->semester, $enrollment->academic_year, $enrollment),
        ]);
    }

    public function update(EnrollmentRequest $request, Enrollment $enrollment): RedirectResponse
    {
        $this->service->update($enrollment, $request->validated());

        return redirect()->route('enrollments.show', $enrollment)->with('status', 'The enrollment was updated.');
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $enrollment->load('student');
        $enrollment->delete(); // its enrollment_offering rows are removed by ON DELETE CASCADE

        return redirect()->route('enrollments.index')
            ->with('status', "The {$enrollment->semester} {$enrollment->academic_year} enrollment of {$enrollment->student->full_name} was deleted.");
    }

    /** Open sections of active subjects in a term, plus any already in $enrollment. */
    private function offeringsForTerm(string $semester, string $academicYear, ?Enrollment $enrollment = null)
    {
        $current = $enrollment?->offerings->modelKeys() ?? [];

        return CourseOffering::query()
            ->with('subject.prerequisite')
            ->withEnrolledCount()
            ->forTerm($semester, $academicYear)
            ->where(fn ($q) => $q
                ->where(fn ($open) => $open->where('status', 'Open')->whereHas('subject', fn ($s) => $s->where('status', 'Active')))
                ->orWhereIn('id', $current))
            ->get()
            ->sortBy(fn (CourseOffering $o) => $o->subject->code . ' ' . $o->section)
            ->values();
    }
}
