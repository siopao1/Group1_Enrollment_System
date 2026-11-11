@extends('layouts.app')

@section('title', 'Edit Enrollment')
@section('breadcrumb', 'Enrollment Records')

@section('content')
  <div class="page-intro">
    <div>
      <h2>{{ $enrollment->student->full_name }} ({{ $enrollment->student_id }})</h2>
      <p>{{ $enrollment->semester }}, {{ $enrollment->academic_year }} · {{ $enrollment->student->program?->name }}, {{ $enrollment->student->year_level }}</p>
    </div>
  </div>

  <form method="POST" action="{{ route('enrollments.update', $enrollment) }}" novalidate>
    @csrf
    @method('PUT')

    <div class="card" style="margin-bottom:18px">
      <div class="card-header"><h3>Status</h3></div>
      <div class="card-body">
        <div class="field" style="max-width:280px">
          <label for="status">Enrollment status <span class="required">*</span></label>
          <div class="select-wrap">
            <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
              @foreach (\App\Models\Enrollment::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $enrollment->status) === $status)>{{ $status }}</option>
              @endforeach
            </select>
          </div>
          <p class="muted">A Dropped enrollment frees its seats in every section.</p>
          @error('status')<p class="field-error">{{ $message }}</p>@enderror
        </div>
      </div>
    </div>

    <div class="card table-card">
      <div class="card-header"><h3>Sections</h3><span>Student and term cannot change; create a new enrollment for another term.</span></div>
      @error('offerings')<div class="card-body" style="padding-bottom:0">@foreach ($errors->get('offerings') as $message)<p class="field-error">{{ $message }}</p>@endforeach</div>@enderror
      <div class="card-body">
        @include('enrollments._offering-picker', [
            'offerings' => $offerings,
            'selected' => array_map('intval', old('offerings', $enrollment->offerings->modelKeys())),
        ])
        <div class="form-actions">
          <a class="btn" href="{{ route('enrollments.show', $enrollment) }}">Cancel</a>
          <button class="btn btn-primary" type="submit"><i class="icon icon-save"></i>Save Changes</button>
        </div>
      </div>
    </div>
  </form>
@endsection
