@php
    $available = $change['available'];
    $percent = $available ? (float) $change['percent'] : null;
    $isFlat = $available && abs($percent) < 0.005;
    $tone = ! $available
        ? 'text-zinc-500'
        : ($isFlat ? 'text-zinc-400' : ($percent > 0 ? 'text-emerald-400' : 'text-red-400'));
    $arrow = ! $available || $isFlat ? '' : ($percent > 0 ? '▲' : '▼');
@endphp

<p class="text-xs uppercase tracking-wide text-zinc-500">{{ $label }}</p>

@if ($available)
    <p class="font-semibold {{ $tone }}">{{ $arrow }}{{ number_format(abs($percent), 2) }}%</p>
    <p class="text-xs text-zinc-500">
        ${{ number_format((float) $change['previous'], 2) }} &rarr; ${{ number_format((float) $change['current'], 2) }}
    </p>
@else
    <p class="mt-1 text-sm text-zinc-500">Not enough data</p>
@endif
