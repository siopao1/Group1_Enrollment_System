{{-- Status badge with the same colors as the main app's badge.js --}}
@php
    $tone = [
        'Active' => 'success', 'Confirmed' => 'success', 'Open' => 'success',
        'Completed' => 'info', 'Pending' => 'warning',
        'Dropped' => 'danger', 'Full' => 'danger', 'Closed' => 'neutral', 'Inactive' => 'neutral',
    ][$status] ?? 'neutral';
@endphp
<span class="badge {{ $tone }}">{{ $status }}</span>
