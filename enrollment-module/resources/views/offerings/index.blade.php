@extends('layouts.app')

@section('title', 'Course Offerings')

@section('content')
  <div class="page-intro">
    <div>
      <h2>Course Offerings</h2>
      <p>Sections offered each term, with schedule, instructor and available slots.</p>
    </div>
    <div class="actions">
      @if (auth()->user()->hasRole('admin'))
        <a class="btn btn-primary" href="{{ route('offerings.create') }}"><i class="icon icon-plus"></i>Add Offering</a>
      @endif
    </div>
  </div>

  <div class="card table-card">
    <form method="GET" action="{{ route('offerings.index') }}" class="table-tools">
      <div class="search-box"><i class="icon icon-search"></i>
        <input name="q" value="{{ $filters['q'] }}" placeholder="Search subject or instructor" aria-label="Search offerings">
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
        <div class="select-wrap" style="width:130px">
          <select class="select" name="status" aria-label="Status">
            <option value="">All statuses</option>
            @foreach (\App\Models\CourseOffering::STATUSES as $status)
              <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-small" type="submit">Apply</button>
        <a class="btn btn-small" href="{{ route('offerings.index') }}">Reset</a>
      </div>
    </form>

    <div class="table-wrap"><table class="data-table">
      <thead><tr>
        <th>Subject</th><th>Section</th><th>Term</th><th>Schedule</th><th>Instructor</th><th>Enrolled</th><th>Status</th>
        <th class="text-right">Actions</th>
      </tr></thead>
      <tbody>
        @forelse ($offerings as $offering)
          @php($full = $offering->enrolled_count >= $offering->slots)
          <tr>
            <td><strong>{{ $offering->subject->code }}</strong><div class="muted">{{ $offering->subject->name }}</div></td>
            <td>{{ $offering->section }}</td>
            <td>{{ $offering->semester }}<div class="muted">{{ $offering->academic_year }}</div></td>
            <td>{{ $offering->schedule ?: '—' }}<div class="muted">{{ $offering->room }}</div></td>
            <td>{{ $offering->instructor ?: 'TBA' }}</td>
            <td>{{ $offering->enrolled_count }} / {{ $offering->slots }}</td>
            <td>@include('partials.badge', ['status' => $offering->status === 'Open' && $full ? 'Full' : $offering->status])</td>
            <td class="text-right">
              <div class="row-actions">
                <a class="icon-action" href="{{ route('offerings.show', $offering) }}" title="View class list"><i class="icon icon-eye"></i></a>
                @if (auth()->user()->hasRole('admin'))
                  <a class="icon-action" href="{{ route('offerings.edit', $offering) }}" title="Edit"><i class="icon icon-pencil"></i></a>
                  <form class="inline-form" method="POST" action="{{ route('offerings.destroy', $offering) }}"
                        onsubmit="return confirm('Delete {{ $offering->subject->code }} – {{ $offering->section }}?')">
                    @csrf @method('DELETE')
                    <button class="icon-action danger" type="submit" title="Delete"><i class="icon icon-trash-2"></i></button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8">
            <div class="empty"><div class="empty-icon"><i class="icon icon-search-x"></i></div>
              <h3>No offerings found.</h3><p>Try another term or clear the filters.</p></div>
          </td></tr>
        @endforelse
      </tbody>
    </table></div>

    {{ $offerings->links('partials.pagination') }}
  </div>
@endsection
