@extends('layouts.app')

@section('title', 'Student Record')
@section('breadcrumb', 'Student Records')

@section('content')
  @php($canEnroll = auth()->user()->hasRole('admin', 'registrar'))

  <div class="page-intro">
    <div>
      <h2>{{ $student->full_name }} ({{ $student->student_id }})</h2>
      <p>{{ $student->program?->name }} · {{ $student->year_level }} · {{ $student->email }}</p>
    </div>
    <div class="actions">
      <a class="btn" href="{{ route('records.index') }}">Back to records</a>
      @if ($canEnroll)
        <a class="btn btn-primary" href="{{ route('enrollments.create', ['student' => $student->student_id]) }}"><i class="icon icon-plus"></i>Enroll this student</a>
      @endif
    </div>
  </div>

  @forelse ($student->enrollments as $enrollment)
    <div class="card table-card" style="margin-bottom:18px">
      <div class="card-header">
        <h3>{{ $enrollment->semester }}, {{ $enrollment->academic_year }}</h3>
        <span>@include('partials.badge', ['status' => $enrollment->status]) · {{ $enrollment->totalUnits() }} units
          @if ($canEnroll) · <a href="{{ route('enrollments.show', $enrollment) }}">Open enrollment</a>@endif
        </span>
      </div>
      <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Subject</th><th>Section</th><th>Units</th><th>Schedule</th><th>Instructor</th></tr></thead>
        <tbody>
          @foreach ($enrollment->offerings->sortBy(fn ($o) => $o->subject->code) as $offering)
            <tr>
              <td><strong>{{ $offering->subject->code }}</strong> {{ $offering->subject->name }}</td>
              <td>{{ $offering->section }}</td>
              <td>{{ $offering->subject->units }}</td>
              <td>{{ $offering->schedule ?: '—' }}</td>
              <td>{{ $offering->instructor ?: 'TBA' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
    </div>
  @empty
    <div class="card"><div class="card-body">
      <div class="empty"><div class="empty-icon"><i class="icon icon-clipboard-list"></i></div>
        <h3>Not enrolled in any term yet.</h3>
        @if ($canEnroll)<a class="btn btn-small" href="{{ route('enrollments.create', ['student' => $student->student_id]) }}">Enroll this student</a>@endif
      </div>
    </div></div>
  @endforelse
@endsection
