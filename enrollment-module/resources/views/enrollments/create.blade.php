@extends('layouts.app')

@section('title', 'Enroll Student')

@section('content')
  <div class="page-intro">
    <div>
      <h2>Enroll Student</h2>
      <p>Choose the term, the student, and the sections they will take.</p>
    </div>
  </div>

  {{-- Step 1: term. Changing it reloads the page with that term's sections. --}}
  <div class="card" style="margin-bottom:18px">
    <div class="card-header"><h3>1. Term</h3></div>
    <div class="card-body">
      <form method="GET" action="{{ route('enrollments.create') }}" class="term-picker">
        <div class="field">
          <label for="t-academic_year">Academic year</label>
          <div class="select-wrap">
            <select class="select" id="t-academic_year" name="academic_year" onchange="this.form.submit()">
              @foreach (\App\Models\CourseOffering::ACADEMIC_YEARS as $year)
                <option value="{{ $year }}" @selected($academicYear === $year)>{{ $year }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="field">
          <label for="t-semester">Semester</label>
          <div class="select-wrap">
            <select class="select" id="t-semester" name="semester" onchange="this.form.submit()">
              @foreach (\App\Models\CourseOffering::SEMESTERS as $option)
                <option value="{{ $option }}" @selected($semester === $option)>{{ $option }}</option>
              @endforeach
            </select>
          </div>
        </div>
        @if ($selectedStudent)<input type="hidden" name="student" value="{{ $selectedStudent }}">@endif
        <noscript><button class="btn btn-small" type="submit">Show sections</button></noscript>
      </form>
    </div>
  </div>

  {{-- Step 2: student and sections. --}}
  <form method="POST" action="{{ route('enrollments.store') }}" novalidate>
    @csrf
    <input type="hidden" name="semester" value="{{ $semester }}">
    <input type="hidden" name="academic_year" value="{{ $academicYear }}">

    <div class="card" style="margin-bottom:18px">
      <div class="card-header"><h3>2. Student</h3><span>{{ $semester }}, {{ $academicYear }}</span></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field">
            <label for="student_id">Student <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('student_id') is-invalid @enderror" id="student_id" name="student_id" required>
                <option value="">Choose a student</option>
                @foreach ($students as $student)
                  @php($taken = in_array($student->student_id, $enrolledThisTerm, true))
                  <option value="{{ $student->student_id }}" @disabled($taken)
                          @selected(old('student_id', $selectedStudent) === $student->student_id)>
                    {{ $student->student_id }} — {{ $student->last_name }}, {{ $student->first_name }}
                    ({{ $student->program?->name }}, {{ $student->year_level }}){{ $taken ? ' — already enrolled this term' : '' }}
                  </option>
                @endforeach
              </select>
            </div>
            @error('student_id')<p class="field-error">{{ $message }}</p>@enderror
            <p class="muted">Students are added in the main system's Students screen.</p>
          </div>
          <div class="field">
            <label for="status">Enrollment status <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                @foreach (\App\Models\Enrollment::STATUSES as $status)
                  <option value="{{ $status }}" @selected(old('status', 'Pending') === $status)>{{ $status }}</option>
                @endforeach
              </select>
            </div>
            @error('status')<p class="field-error">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>
    </div>

    <div class="card table-card">
      <div class="card-header"><h3>3. Sections</h3><span>{{ $offerings->count() }} open section(s)</span></div>
      @error('offerings')<div class="card-body" style="padding-bottom:0">@foreach ($errors->get('offerings') as $message)<p class="field-error">{{ $message }}</p>@endforeach</div>@enderror
      <div class="card-body">
        @include('enrollments._offering-picker', ['offerings' => $offerings, 'selected' => array_map('intval', old('offerings', []))])
        <div class="form-actions">
          <a class="btn" href="{{ route('enrollments.index') }}">Cancel</a>
          <button class="btn btn-primary" type="submit"><i class="icon icon-check"></i>Enroll Student</button>
        </div>
      </div>
    </div>
  </form>
@endsection
