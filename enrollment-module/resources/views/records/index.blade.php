@extends('layouts.app')

@section('title', 'Student Records')

@section('content')
  <div class="page-intro">
    <div>
      <h2>Student Records</h2>
      <p>Open a student to see their enrollments and subjects for every term.</p>
    </div>
  </div>

  <div class="card table-card">
    <form method="GET" action="{{ route('records.index') }}" class="table-tools">
      <div class="search-box"><i class="icon icon-search"></i>
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search student ID or name" aria-label="Search students">
      </div>
      <div class="filters">
        <div class="select-wrap" style="width:200px">
          <select class="select" name="program" aria-label="Program">
            <option value="">All programs</option>
            @foreach ($programs as $program)
              <option value="{{ $program->id }}" @selected((string) ($filters['program'] ?? '') === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="select-wrap" style="width:130px">
          <select class="select" name="year" aria-label="Year level">
            <option value="">All years</option>
            @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $year)
              <option value="{{ $year }}" @selected(($filters['year'] ?? '') === $year)>{{ $year }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-small" type="submit">Apply</button>
        <a class="btn btn-small" href="{{ route('records.index') }}">Reset</a>
      </div>
    </form>

    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Student ID</th><th>Name</th><th>Program</th><th>Year</th><th>Student status</th><th>Enrollments</th><th class="text-right">Record</th></tr></thead>
      <tbody>
        @forelse ($students as $student)
          <tr>
            <td>{{ $student->student_id }}</td>
            <td><strong>{{ $student->full_name }}</strong></td>
            <td>{{ $student->program?->name }}</td>
            <td>{{ $student->year_level }}</td>
            <td>@include('partials.badge', ['status' => $student->status])</td>
            <td>{{ $student->enrollments_count }}</td>
            <td class="text-right"><a class="icon-action" href="{{ route('records.show', $student) }}" title="Open record"><i class="icon icon-eye"></i></a></td>
          </tr>
        @empty
          <tr><td colspan="7">
            <div class="empty"><div class="empty-icon"><i class="icon icon-search-x"></i></div>
              <h3>No students found.</h3><p>Students are added in the main system's Students screen.</p></div>
          </td></tr>
        @endforelse
      </tbody>
    </table></div>

    {{ $students->links('partials.pagination') }}
  </div>
@endsection
