{{-- Requirement status (§3.2.4) select — expects $model (wire:model path), $value, $canEdit, $DD --}}
<select class="form-select form-select-sm status-select is-{{ $value ?: 'pending' }}" wire:model.live="{{ $model }}" @disabled(! $canEdit)>
    <option value="">Pending</option>
    @foreach ($DD::REQ_STATUSES as $sk => [$sl])
        <option value="{{ $sk }}">{{ $sl }}</option>
    @endforeach
</select>