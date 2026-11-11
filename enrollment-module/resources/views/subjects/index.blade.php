@extends('layouts.app')

@section('title', 'Subjects')

@section('content')
  <div class="page-intro">
    <div>
      <h2>Subjects</h2>
      <p>Subjects offered by the college, with units and prerequisites.</p>
    </div>
    <div class="actions">
      @if (auth()->user()->hasRole('admin'))
        <a class="btn btn-primary" href="{{ route('subjects.create') }}"><i class="icon icon-plus"></i>Add Subject</a>
      @endif
    </div>
  </div>

  <div class="card table-card">
    <form method="GET" action="{{ route('subjects.index') }}" class="table-tools">
      <div class="search-box"><i class="icon icon-search"></i>
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search code or name" aria-label="Search subjects">
      </div>
      <div class="filters">
        <div class="select-wrap" style="width:200px">
          <select class="select" name="department" aria-label="Department">
            <option value="">All departments</option>
            @foreach (\App\Models\Subject::DEPARTMENTS as $department)
              <option value="{{ $department }}" @selected(($filters['department'] ?? '') === $department)>{{ $department }}</option>
            @endforeach
          </select>
        </div>
        <div class="select-wrap" style="width:140px">
          <select class="select" name="status" aria-label="Status">
            <option value="">All statuses</option>
            @foreach (\App\Models\Subject::STATUSES as $status)
              <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-small" type="submit">Apply</button>
        <a class="btn btn-small" href="{{ route('subjects.index') }}">Reset</a>
      </div>
    </form>

    <div class="table-wrap"><table class="data-table">
      <thead><tr>
        <th>Code</th><th>Name</th><th>Units</th><th>Department</th><th>Prerequisite</th><th>Offerings</th><th>Status</th>
        @if (auth()->user()->hasRole('admin'))<th class="text-right">Actions</th>@endif
      </tr></thead>
      <tbody>
        @forelse ($subjects as $subject)
          <tr>
            <td><strong>{{ $subject->code }}</strong></td>
            <td>{{ $subject->name }}<div class="muted">{{ \Illuminate\Support\Str::limit($subject->description, 70) }}</div></td>
            <td>{{ $subject->units }}</td>
            <td>{{ $subject->department }}</td>
            <td>{{ $subject->prerequisite?->code ?? '—' }}</td>
            <td>{{ $subject->offerings_count }}</td>
            <td>@include('partials.badge', ['status' => $subject->status])</td>
            @if (auth()->user()->hasRole('admin'))
              <td class="text-right">
                <div class="row-actions">
                  <a class="icon-action" href="{{ route('subjects.edit', $subject) }}" title="Edit"><i class="icon icon-pencil"></i></a>
                  <form class="inline-form" method="POST" action="{{ route('subjects.destroy', $subject) }}"
                        onsubmit="return confirm('Delete subject {{ $subject->code }}?')">
                    @csrf @method('DELETE')
                    <button class="icon-action danger" type="submit" title="Delete"><i class="icon icon-trash-2"></i></button>
                  </form>
                </div>
              </td>
            @endif
          </tr>
        @empty
          <tr><td colspan="8">
            <div class="empty"><div class="empty-icon"><i class="icon icon-search-x"></i></div>
              <h3>No subjects found.</h3><p>Try adjusting your search or filters.</p></div>
          </td></tr>
        @endforelse
      </tbody>
    </table></div>

    {{ $subjects->links('partials.pagination') }}
  </div>
@endsection
