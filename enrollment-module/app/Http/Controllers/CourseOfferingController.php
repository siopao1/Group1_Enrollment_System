<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourseOfferingRequest;
use App\Models\CourseOffering;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Course offerings (sections): everyone views; admin adds, edits and deletes. */
class CourseOfferingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => $request->query('q'),
            'semester' => $request->query('semester'),
            'academic_year' => $request->query('academic_year', CourseOffering::ACADEMIC_YEARS[0]),
            'status' => $request->query('status'),
        ];

        $offerings = CourseOffering::query()
            ->with('subject')
            ->withEnrolledCount()
            ->when($filters['q'], fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereHas('subject', fn ($s) => $s->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))
                ->orWhere('instructor', 'like', "%{$term}%")))
            ->when($filters['semester'], fn ($q, $s) => $q->where('semester', $s))
            ->when($filters['academic_year'], fn ($q, $y) => $q->where('academic_year', $y))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->orderBy('semester')
            ->orderBy(Subject::select('code')->whereColumn('subjects.id', 'course_offerings.subject_id'))
            ->orderBy('section')
            ->paginate(10)
            ->withQueryString();

        return view('offerings.index', compact('offerings', 'filters'));
    }

    /** One section with its class list. */
    public function show(CourseOffering $offering): View
    {
        $offering->load(['subject.prerequisite', 'enrollments' => fn ($q) => $q->with('student.program')->orderBy('student_id')]);

        return view('offerings.show', compact('offering'));
    }

    public function create(): View
    {
        return view('offerings.form', [
            'offering' => new CourseOffering([
                'semester' => CourseOffering::SEMESTERS[0],
                'academic_year' => CourseOffering::ACADEMIC_YEARS[0],
                'slots' => 40,
                'status' => 'Open',
            ]),
            'subjects' => Subject::where('status', 'Active')->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(CourseOfferingRequest $request): RedirectResponse
    {
        $offering = CourseOffering::create($request->validated())->load('subject');

        return redirect()->route('offerings.index', ['academic_year' => $offering->academic_year])
            ->with('status', "{$offering->label()} was added for {$offering->semester} {$offering->academic_year}.");
    }

    public function edit(CourseOffering $offering): View
    {
        return view('offerings.form', [
            'offering' => $offering,
            'subjects' => Subject::where('status', 'Active')
                ->orWhere('id', $offering->subject_id)
                ->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(CourseOfferingRequest $request, CourseOffering $offering): RedirectResponse
    {
        $offering->update($request->validated());
        $offering->load('subject');

        return redirect()->route('offerings.show', $offering)->with('status', "{$offering->label()} was updated.");
    }

    public function destroy(CourseOffering $offering): RedirectResponse
    {
        $offering->load('subject');
        $count = $offering->enrollments()->count();
        if ($count > 0) {
            return back()->with('error', "{$offering->label()} is part of {$count} enrollment(s). Remove it from those enrollments first, or set it to Closed.");
        }

        $offering->delete();

        return redirect()->route('offerings.index')->with('status', "{$offering->label()} was deleted.");
    }
}
