<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Student Records: each student's enrollments by term. Every role may view. */
class StudentRecordController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'program', 'year']);

        $students = Student::query()
            ->with('program')
            ->withCount('enrollments')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('student_id', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")))
            ->when($filters['program'] ?? null, fn ($q, $p) => $q->where('program_id', $p))
            ->when($filters['year'] ?? null, fn ($q, $y) => $q->where('year_level', $y))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString();

        return view('records.index', [
            'students' => $students,
            'filters' => $filters,
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function show(Student $student): View
    {
        $student->load([
            'program',
            'enrollments' => fn ($q) => $q->orderByDesc('academic_year')->orderByDesc('semester'),
            'enrollments.offerings.subject',
        ]);

        return view('records.show', compact('student'));
    }
}
