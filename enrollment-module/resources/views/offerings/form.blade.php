@extends('layouts.app')

@php($editing = $offering->exists)

@section('title', $editing ? 'Edit Offering' : 'Add Offering')
@section('breadcrumb', 'Course Offerings')

@section('content')
  <div class="card form-card">
    <form method="POST" action="{{ $editing ? route('offerings.update', $offering) : route('offerings.store') }}" novalidate>
      @csrf
      @if ($editing) @method('PUT') @endif

      <div class="form-section">
        <h3>Section</h3>
        <div class="form-grid">
          <div class="field">
            <label for="subject_id">Subject <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('subject_id') is-invalid @enderror" id="subject_id" name="subject_id" required>
                <option value="">Choose a subject</option>
                @foreach ($subjects as $subject)
                  <option value="{{ $subject->id }}" @selected((string) old('subject_id', $offering->subject_id) === (string) $subject->id)>
                    {{ $subject->code }} – {{ $subject->name }}
                  </option>
                @endforeach
              </select>
            </div>
            @error('subject_id')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="section">Section <span class="required">*</span></label>
            <input class="input @error('section') is-invalid @enderror" id="section" name="section" maxlength="20"
                   placeholder="A" value="{{ old('section', $offering->section) }}" required>
            @error('section')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="semester">Semester <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('semester') is-invalid @enderror" id="semester" name="semester" required>
                @foreach (\App\Models\CourseOffering::SEMESTERS as $semester)
                  <option value="{{ $semester }}" @selected(old('semester', $offering->semester) === $semester)>{{ $semester }}</option>
                @endforeach
              </select>
            </div>
            @error('semester')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="academic_year">Academic year <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('academic_year') is-invalid @enderror" id="academic_year" name="academic_year" required>
                @foreach (\App\Models\CourseOffering::ACADEMIC_YEARS as $year)
                  <option value="{{ $year }}" @selected(old('academic_year', $offering->academic_year) === $year)>{{ $year }}</option>
                @endforeach
              </select>
            </div>
            @error('academic_year')<p class="field-error">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>

      <div class="form-section">
        <h3>Schedule and capacity</h3>
        <div class="form-grid">
          <div class="field">
            <label for="instructor">Instructor</label>
            <input class="input @error('instructor') is-invalid @enderror" id="instructor" name="instructor" maxlength="100"
                   value="{{ old('instructor', $offering->instructor) }}">
            @error('instructor')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="schedule">Schedule</label>
            <input class="input @error('schedule') is-invalid @enderror" id="schedule" name="schedule" maxlength="100"
                   placeholder="MWF 9:00–10:00 AM" value="{{ old('schedule', $offering->schedule) }}">
            @error('schedule')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="room">Room</label>
            <input class="input @error('room') is-invalid @enderror" id="room" name="room" maxlength="40"
                   value="{{ old('room', $offering->room) }}">
            @error('room')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="slots">Slots <span class="required">*</span></label>
            <input class="input @error('slots') is-invalid @enderror" id="slots" name="slots" type="number" min="1" max="500"
                   value="{{ old('slots', $offering->slots) }}" required>
            @error('slots')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="status">Status <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                @foreach (\App\Models\CourseOffering::STATUSES as $status)
                  <option value="{{ $status }}" @selected(old('status', $offering->status) === $status)>{{ $status }}</option>
                @endforeach
              </select>
            </div>
            <p class="muted">Closed sections cannot take new enrollments.</p>
            @error('status')<p class="field-error">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>

      <div class="form-actions">
        <a class="btn" href="{{ $editing ? route('offerings.show', $offering) : route('offerings.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="icon icon-save"></i>{{ $editing ? 'Save Changes' : 'Add Offering' }}</button>
      </div>
    </form>
  </div>
@endsection
