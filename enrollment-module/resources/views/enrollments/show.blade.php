@extends('layouts.app')

@section('title', 'Enrollment')
@section('breadcrumb', 'Enrollment Records')

@section('content')
  <div class="page-intro">
    <div>
      <h2>{{ $enrollment->student->full_name }} ({{ $enrollment->student_id }})</h2>
      <p>{{ $enrollment->semester }}, {{ $enrollment->academic_year }}</p>
    </div>
    <div class="actions">
      <a class="btn" href="{{ route('enrollments.index') }}">Back to records</a>
      <a class="btn" href="{{ route('records.show', $enrollment->student) }}"><i class="icon icon-graduation-cap"></i>Student record</a>
      <a class="btn btn-primary" href="{{ route('enrollments.edit', $enrollment) }}"><i class="icon icon-pencil"></i>Edit</a>
      @if (auth()->user()->hasRole('admin'))
        <form class="inline-form" method="POST" action="{{ route('enrollments.destroy', $enrollment) }}"
              onsubmit="return confirm('Delete this enrollment? The student will be removed from all its sections.')">
          @csrf @method('DELETE')
          <button class="btn btn-danger" type="submit"><i class="icon icon-trash-2"></i>Delete</button>
        </form>
      @endif
    </div>
  </div>

  <div class="card" style="margin-bottom:18px">
    <div class="card-body detail-list">
      <div class="info-item"><span>Status</span><strong>@include('partials.badge', ['status' => $enrollment->status])</strong></div>
      <div class="info-item"><span>Program</span><strong>{{ $enrollment->student->program?->name }}</strong></div>
      <div class="info-item"><span>Year level</span><strong>{{ $enrollment->student->year_level }}</strong></div>
      <div class="info-item"><span>Total units</span><strong>{{ $enrollment->totalUnits() }}</strong></div>
      <div class="info-item"><span>Enrolled on</span><strong>{{ $enrollment->enrolled_at->format('M j, Y') }}</strong></div>
      <div class="info-item"><span>Enrolled by</span><strong>{{ $enrollment->enrolledBy?->full_name ?? '—' }}</strong></div>
    </div>
  </div>

  <div class="card table-card">
    <div class="card-header"><h3>Subjects</h3><span>{{ $enrollment->offerings->count() }} section(s)</span></div>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Subject</th><th>Section</th><th>Units</th><th>Schedule</th><th>Room</th><th>Instructor</th></tr></thead>
      <tbody>
        @foreach ($enrollment->offerings->sortBy(fn ($o) => $o->subject->code) as $offering)
          <tr>
            <td><a href="{{ route('offerings.show', $offering) }}"><strong>{{ $offering->subject->code }}</strong></a> {{ $offering->subject->name }}</td>
            <td>{{ $offering->section }}</td>
            <td>{{ $offering->subject->units }}</td>
            <td>{{ $offering->schedule ?: '—' }}</td>
            <td>{{ $offering->room ?: '—' }}</td>
            <td>{{ $offering->instructor ?: 'TBA' }}</td>
          </tr>
        @endforeach
        <tr><td colspan="2" class="text-right"><strong>Total</strong></td><td><strong>{{ $enrollment->totalUnits() }}</strong></td><td colspan="3"></td></tr>
      </tbody>
    </table></div>
  </div>
@endsection
