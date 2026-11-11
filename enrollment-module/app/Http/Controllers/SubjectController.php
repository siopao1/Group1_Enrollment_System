<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Subjects: everyone views; admin adds, edits and deletes (see routes/web.php). */
class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'department', 'status']);

        $subjects = Subject::query()
            ->with('prerequisite')
            ->withCount('offerings')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(
                fn ($w) => $w->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
            ))
            ->when($filters['department'] ?? null, fn ($q, $d) => $q->where('department', $d))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('subjects.index', compact('subjects', 'filters'));
    }

    public function create(): View
    {
        return view('subjects.form', [
            'subject' => new Subject(['status' => 'Active', 'units' => 3]),
            'prerequisites' => Subject::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        return redirect()->route('subjects.index')->with('status', "Subject {$subject->code} was added.");
    }

    public function edit(Subject $subject): View
    {
        return view('subjects.form', [
            'subject' => $subject,
            'prerequisites' => Subject::whereKeyNot($subject->id)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()->route('subjects.index')->with('status', "Subject {$subject->code} was updated.");
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $offerings = $subject->offerings()->count();
        if ($offerings > 0) {
            return back()->with('error', "{$subject->code} has {$offerings} course offering(s). Delete those first, or set the subject to Inactive.");
        }

        $subject->delete();

        return redirect()->route('subjects.index')->with('status', "Subject {$subject->code} was deleted.");
    }
}
