<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The business rules for enrolling a student in a term:
 *   - every chosen section belongs to the enrollment's term
 *   - new sections must be Open and their subject Active
 *   - no two sections of the same subject
 *   - a section cannot go over its slots (dropped enrollments free their seat)
 *   - at most MAX_UNITS units per term
 *
 * Saving runs in a database transaction and locks the chosen sections
 * (SELECT … FOR UPDATE), so two registrars cannot take the last seat at the
 * same moment. Any broken rule throws a ValidationException, which rolls the
 * transaction back and sends the messages back to the form.
 */
class EnrollmentService
{
    public const MAX_UNITS = 26;

    public function create(array $data, User $enrolledBy): Enrollment
    {
        return DB::transaction(function () use ($data, $enrolledBy) {
            $offerings = $this->checkOfferings($data['offerings'], $data['semester'], $data['academic_year'], $data['status']);

            $enrollment = Enrollment::create([
                'student_id'    => $data['student_id'],
                'semester'      => $data['semester'],
                'academic_year' => $data['academic_year'],
                'status'        => $data['status'],
                'enrolled_at'   => now()->toDateString(),
                'enrolled_by'   => $enrolledBy->id,
            ]);
            $enrollment->offerings()->sync($offerings->modelKeys());

            return $enrollment;
        });
    }

    public function update(Enrollment $enrollment, array $data): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $data) {
            $offerings = $this->checkOfferings(
                $data['offerings'], $enrollment->semester, $enrollment->academic_year, $data['status'], $enrollment
            );

            $enrollment->update(['status' => $data['status']]);
            $enrollment->offerings()->sync($offerings->modelKeys());

            return $enrollment;
        });
    }

    /**
     * @param int[] $offeringIds
     * @return Collection<int, CourseOffering>
     * @throws ValidationException
     */
    private function checkOfferings(array $offeringIds, string $semester, string $academicYear, string $status, ?Enrollment $current = null): Collection
    {
        $offerings = CourseOffering::with('subject')
            ->whereIn('id', $offeringIds)
            ->lockForUpdate()
            ->get();

        $alreadyIn = $current ? $current->offerings()->pluck('course_offerings.id')->all() : [];
        $takesSeat = $status !== 'Dropped';
        $errors = [];

        if ($offerings->count() !== count(array_unique($offeringIds))) {
            $errors[] = 'One of the selected sections no longer exists.';
        }

        foreach ($offerings as $offering) {
            $label = $offering->label();
            $isNew = !in_array($offering->id, $alreadyIn, true);

            if ($offering->semester !== $semester || $offering->academic_year !== $academicYear) {
                $errors[] = "{$label} is offered in {$offering->semester} {$offering->academic_year}, not in {$semester} {$academicYear}.";
                continue;
            }
            if ($isNew && $offering->status !== 'Open') {
                $errors[] = "{$label} is closed to new enrollments.";
            }
            if ($isNew && $offering->subject->status !== 'Active') {
                $errors[] = "{$offering->subject->code} is inactive and cannot be taken.";
            }

            if ($takesSeat) {
                // Seats held by other active enrollments (this enrollment's own seat is not counted).
                $held = $offering->enrollments()
                    ->where('enrollments.status', '!=', 'Dropped')
                    ->when($current, fn ($q) => $q->where('enrollments.id', '!=', $current->id))
                    ->count();
                if ($held >= $offering->slots) {
                    $errors[] = "{$label} is full ({$offering->slots} of {$offering->slots} slots taken).";
                }
            }
        }

        $duplicates = $offerings->groupBy('subject_id')->filter(fn ($group) => $group->count() > 1);
        foreach ($duplicates as $group) {
            $errors[] = "Choose only one section of {$group->first()->subject->code}.";
        }

        $units = $offerings->sum(fn (CourseOffering $o) => $o->subject->units);
        if ($units > self::MAX_UNITS) {
            $errors[] = "The selected subjects total {$units} units; the maximum per term is " . self::MAX_UNITS . '.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['offerings' => $errors]);
        }

        return $offerings;
    }
}
