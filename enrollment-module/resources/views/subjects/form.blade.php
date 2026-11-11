@extends('layouts.app')

@php($editing = $subject->exists)

@section('title', $editing ? "Edit {$subject->code}" : 'Add Subject')
@section('breadcrumb', 'Subjects')

@section('content')
  <div class="card form-card">
    <form method="POST" action="{{ $editing ? route('subjects.update', $subject) : route('subjects.store') }}" novalidate>
      @csrf
      @if ($editing) @method('PUT') @endif

      <div class="form-section">
        <h3>Subject details</h3>
        <div class="form-grid">
          <div class="field">
            <label for="code">Code <span class="required">*</span></label>
            <input class="input @error('code') is-invalid @enderror" id="code" name="code" maxlength="20"
                   placeholder="IT 101" value="{{ old('code', $subject->code) }}" required>
            @error('code')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="name">Name <span class="required">*</span></label>
            <input class="input @error('name') is-invalid @enderror" id="name" name="name" maxlength="120"
                   value="{{ old('name', $subject->name) }}" required>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="units">Units <span class="required">*</span></label>
            <input class="input @error('units') is-invalid @enderror" id="units" name="units" type="number" min="1" max="6"
                   value="{{ old('units', $subject->units) }}" required>
            @error('units')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="department">Department <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('department') is-invalid @enderror" id="department" name="department" required>
                <option value="">Choose a department</option>
                @foreach (\App\Models\Subject::DEPARTMENTS as $department)
                  <option value="{{ $department }}" @selected(old('department', $subject->department) === $department)>{{ $department }}</option>
                @endforeach
              </select>
            </div>
            @error('department')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="prerequisite_id">Prerequisite</label>
            <div class="select-wrap">
              <select class="select @error('prerequisite_id') is-invalid @enderror" id="prerequisite_id" name="prerequisite_id">
                <option value="">None</option>
                @foreach ($prerequisites as $option)
                  <option value="{{ $option->id }}" @selected((string) old('prerequisite_id', $subject->prerequisite_id) === (string) $option->id)>
                    {{ $option->code }} – {{ $option->name }}
                  </option>
                @endforeach
              </select>
            </div>
            @error('prerequisite_id')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="status">Status <span class="required">*</span></label>
            <div class="select-wrap">
              <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                @foreach (\App\Models\Subject::STATUSES as $status)
                  <option value="{{ $status }}" @selected(old('status', $subject->status) === $status)>{{ $status }}</option>
                @endforeach
              </select>
            </div>
            @error('status')<p class="field-error">{{ $message }}</p>@enderror
          </div>
          <div class="field" style="grid-column:1/-1">
            <label for="description">Description</label>
            <textarea class="input @error('description') is-invalid @enderror" id="description" name="description"
                      maxlength="1000">{{ old('description', $subject->description) }}</textarea>
            @error('description')<p class="field-error">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>

      <div class="form-actions">
        <a class="btn" href="{{ route('subjects.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="icon icon-save"></i>{{ $editing ? 'Save Changes' : 'Add Subject' }}</button>
      </div>
    </form>
  </div>
@endsection
