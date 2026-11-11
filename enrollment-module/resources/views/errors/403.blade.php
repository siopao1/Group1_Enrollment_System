@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
  <div class="card"><div class="card-body">
    <div class="empty">
      <div class="empty-icon"><i class="icon icon-lock"></i></div>
      <h3>You do not have access to this page.</h3>
      <p>{{ $exception->getMessage() ?: 'Your account role does not include this permission.' }}</p>
      <a class="btn btn-small" href="{{ route('offerings.index') }}">Back to Course Offerings</a>
    </div>
  </div></div>
@endsection
