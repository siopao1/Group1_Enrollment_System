@extends('layouts.app')

@section('title', 'Enrollment Records')

@section('content')
  <div class="page-intro">
    <div>
      <h2>Enrollment Records</h2>
      <p>Each record is one student's enrollment for one term.</p>
    </div>
    <div class="actions">
      <a class="btn btn-primary" href="{{ route('enrollments.create') }}"><i class="icon icon-plus"></i>Enroll Student</a>
    </div>
  </div>

  <div class="card table-card">
    <form method="GET" action="{{ route('enrollments.index') }}" class="table-tools">
      <div class="search-box"><i class="icon icon-search"></i>
        <input name="q" value="{{ $filters['q'] }}" placeholder="Search student ID or name" aria-label="Search enrollments">
      </div>
      <div class="filters">
        <div class="select-wrap" style="width:150px">
          <select class="select" name="academic_year" aria-label="Academic year">
            <option value="">All years</option>
            @foreach (\App\Models\CourseOffering::ACADEMIC_YEARS as $year)
              <option value="{{ $year }}" @selected($filters['academic_year'] === $year)>{{ $year }}</option>
            @endforeach
          </select>
        </div>
        <div class="select-wrap" style="width:150px">
          <select class="select" name="semester" aria-label="Semester">
            <option value="">All semesters</option>
            @foreach (\App\Models\CourseOffering::SEMESTERS as $semester)
              <option value="{{ $semester }}" @selected($filters['semester'] === $semester)>{{ $semester }}</option>
            @endforeach
          </select>
        </div>
        <div class="select-wrap" style="width:200px">
          <select class="select" name="program" aria-label="Program">
            <option value="">All programs</option>
            @foreach ($programs as $program)
              <option value="{{ $program->id }}" @selected((string) $filters['program'] === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="select-wrap" style="width:140px">
          <select class="select" name="status" aria-label="Status">
            <option value="">All statuses</option>
            @foreach (\App\Models\Enrollment::STATUSES as $status)
              <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-small" type="submit">Apply</button>
        <a class="btn btn-small" href="{{ route('enrollments.index') }}">Reset</a>
      </div>
    </form>

    <div class="table-wrap"><table class="data-table">
      <thead><tr>
        <th>Student</th><th>Program</th><th>Term</th><th>Subjects</th><th>Units</th><th>Enrolled on</th><th>Status</th><th class="text-right">Actions</th>
      </tr></thead>
      <tbody>
        @forelse ($enrollments as $enrollment)
          <tr>
            <td><strong>{{ $enrollment->student->full_name }}</strong><div class="muted">{{ $enrollment->student_id }}</div></td>
            <td>{{ $enrollment->student->program?->name }}<div class="muted">{{ $enrollment->student->year_level }}</div></td>
            <td>{{ $enrollment->semester }}<div class="muted">{{ $enrollment->academic_year }}</div></td>
            <td>{{ $enrollment->offerings->count() }}</td>
            <td>{{ $enrollment->totalUnits() }}</td>
            <td>{{ $enrollment->enrolled_at->format('M j, Y') }}</td>
            <td>@include('partials.badge', ['status' => $enrollment->status])</td>
            <td class="text-right">
              <div class="row-actions">
                <a class="icon-action" href="{{ route('enrollments.show', $enrollment) }}" title="View"><i class="icon icon-eye"></i></a>
                <a class="icon-action" href="{{ route('enrollments.edit', $enrollment) }}" title="Edit"><i class="icon icon-pencil"></i></a>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8">
            <div class="empty"><div class="empty-icon"><i class="icon icon-search-x"></i></div>
              <h3>No enrollments found.</h3><p>Try another term or clear the filters.</p></div>
          </td></tr>
        @endforelse
      </tbody>
    </table></div>

    {{ $enrollments->links('partials.pagination') }}
  </div>
@endsection
