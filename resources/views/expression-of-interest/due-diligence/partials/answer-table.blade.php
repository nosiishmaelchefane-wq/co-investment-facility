{{-- Yes / Partly / No table — expects $items (key => question), $path (wire:model prefix), $rows, $canEdit, $DD --}}
<div class="table-responsive">
    <table class="table table-modern align-middle mb-0 dd-table">
        <tbody>
            @foreach ($items as $key => $question)
                @php $ans = $rows[$key]['answer'] ?? null; @endphp
                <tr wire:key="{{ $path }}-{{ $key }}">
                    <td class="small" style="width: 42%">{{ $question }}</td>
                    <td style="width: 16%">
                        <select class="form-select form-select-sm answer-select is-{{ $ans ?: 'none' }}" wire:model.live="{{ $path }}.{{ $key }}.answer" @disabled(! $canEdit)>
                            <option value="">—</option>
                            @foreach ($DD::ANSWERS as $ak => [$al]) <option value="{{ $ak }}">{{ $al }}</option> @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" wire:model.blur="{{ $path }}.{{ $key }}.note" maxlength="500"
                               placeholder="Note" @disabled(! $canEdit)>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>