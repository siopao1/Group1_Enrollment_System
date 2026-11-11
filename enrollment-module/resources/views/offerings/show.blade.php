@extends('layouts.app')

@section('title', "{$offering->subject->code} – {$offering->section}")
@section('breadcrumb', 'Course Offerings')

@section('content')
  @php($holding = $offering->enrollments->where('status', '!=', 'Dropped')->count())

  <div class="page-intro">
    <div>
      <h2>{{ $offering->subject->code }} – {{ $offering->section }}: {{ $offering->subject->name }}</h2>
      <p>{{ $offering->semester }}, {{ $offering->academic_year }}</p>
    </div>
    <div class="actions">
      <a class="btn" href="{{ route('offerings.index', ['academic_year' => $offering->academic_year]) }}">Back to list</a>
      @if (auth()->user()->hasRole('admin'))
        <a class="btn btn-primary" href="{{ route('offerings.edit', $offering) }}"><i class="icon icon-pencil"></i>Edit</a>
      @endif
    </div>
  </div>

  <div class="card" style="margin-bottom:18px">
    <div class="card-body detail-list">
      <div class="info-item"><span>Units</span><strong>{{ $offering->subject->units }}</strong></div>
      <div class="info-item"><span>Prerequisite</span><strong>{{ $offering->subject->prerequisite?->code ?? 'None' }}</strong></div>
      <div class="info-item"><span>Schedule</span><strong>{{ $offering->schedule ?: '—' }}</strong></div>
      <div class="info-item"><span>Room</span><strong>{{ $offering->room ?: '—' }}</strong></div>
      <div class="info-item"><span>Instructor</span><strong>{{ $offering->instructor ?: 'TBA' }}</strong></div>
      <div class="info-item"><span>Seats</span><strong>{{ $holding }} / {{ $offering->slots }} ({{ max(0, $offering->slots - $holding) }} left)</strong></div>
      <div class="info-item"><span>Status</span><strong>@include('partials.badge', ['status' => $offering->status])</strong></div>
    </div>
  </div>

  <div class="card table-card">
    <div class="card-header"><h3>Class list</h3><span>{{ $offering->enrollments->count() }} enrollment(s)</span></div>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Student ID</th><th>Name</th><th>Program</th><th>Year</th><th>Enrollment status</th></tr></thead>
      <tbody>
        @forelse ($offering->enrollments as $enrollment)
          <tr>
            <td>
              @if (Route::has('records.show'))
                <a href="{{ route('records.show', $enrollment->student) }}">{{ $enrollment->student_id }}</a>
              @else
                {{ $enrollment->student_id }}
              @endif
            </td>
            <td>{{ $enrollment->student->full_name }}</td>
            <td>{{ $enrollment->student->program?->name }}</td>
            <td>{{ $enrollment->student->year_level }}</td>
            <td>@include('partials.badge', ['status' => $enrollment->status])</td>
          </tr>
        @empty
          <tr><td colspan="5"><div class="empty"><div class="empty-icon"><i class="icon icon-users"></i></div>
            <h3>No students enrolled yet.</h3></div></td></tr>
        @endforelse
      </tbody>
    </table></div>
  </div>
@endsection
