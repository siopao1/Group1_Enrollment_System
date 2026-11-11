{{--
  Section picker for the Enroll and Edit pages.
  Expects: $offerings (with subject and enrolled_count), $selected (int[] of chosen offering ids).
  Full sections can't be ticked unless already part of this enrollment. The server
  checks every rule again (EnrollmentService); this is only a convenience.
--}}
<div class="table-wrap"><table class="data-table">
  <thead><tr>
    <th style="width:42px"></th><th>Subject</th><th>Section</th><th>Units</th><th>Schedule</th><th>Instructor</th><th>Seats</th>
  </tr></thead>
  <tbody>
    @forelse ($offerings as $offering)
      @php
          $checked = in_array($offering->id, $selected);
          $full = $offering->enrolled_count >= $offering->slots;
          $blocked = $full && !$checked;
      @endphp
      <tr class="offering-row{{ $blocked ? ' is-full' : '' }}">
        <td>
          <input type="checkbox" name="offerings[]" value="{{ $offering->id }}" id="off-{{ $offering->id }}"
                 data-units="{{ $offering->subject->units }}" @checked($checked) @disabled($blocked)
                 aria-label="{{ $offering->subject->code }} section {{ $offering->section }}">
        </td>
        <td><label for="off-{{ $offering->id }}"><strong>{{ $offering->subject->code }}</strong> {{ $offering->subject->name }}</label>
          @if ($offering->subject->prerequisite_id)
            <div class="muted">Prerequisite: {{ $offering->subject->prerequisite?->code }}</div>
          @endif
        </td>
        <td>{{ $offering->section }}</td>
        <td>{{ $offering->subject->units }}</td>
        <td>{{ $offering->schedule ?: '—' }}<div class="muted">{{ $offering->room }}</div></td>
        <td>{{ $offering->instructor ?: 'TBA' }}</td>
        <td>
          {{ $offering->enrolled_count }} / {{ $offering->slots }}
          @if ($full) @include('partials.badge', ['status' => 'Full']) @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty"><div class="empty-icon"><i class="icon icon-calendar-x"></i></div>
        <h3>No open sections in this term.</h3><p>Add course offerings for this term first.</p></div></td></tr>
    @endforelse
  </tbody>
</table></div>

<p class="muted" style="margin-top:10px">
  Selected: <span class="units-total" id="unitsTotal">0</span> units
  (maximum {{ \App\Services\EnrollmentService::MAX_UNITS }} per term)
  <span id="unitsWarning" style="color:var(--danger);display:none"> — over the limit</span>
</p>

@push('scripts')
<script>
  // Live total of the ticked subjects' units.
  (function () {
    const max = {{ \App\Services\EnrollmentService::MAX_UNITS }};
    const boxes = document.querySelectorAll('input[name="offerings[]"]');
    const total = document.getElementById('unitsTotal');
    const warning = document.getElementById('unitsWarning');
    function update() {
      let units = 0;
      boxes.forEach((box) => { if (box.checked) units += Number(box.dataset.units); });
      total.textContent = units;
      warning.style.display = units > max ? 'inline' : 'none';
    }
    boxes.forEach((box) => box.addEventListener('change', update));
    update();
  })();
</script>
@endpush
