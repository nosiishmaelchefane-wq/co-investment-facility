{{-- Area completion pill — expects $pct --}}
@if ($pct === 100)
    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Complete</span>
@elseif ($pct > 0)
    <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> {{ $pct }}%</span>
@else
    <span class="pill pill-muted"><i class="bi bi-circle"></i> Not started</span>
@endif