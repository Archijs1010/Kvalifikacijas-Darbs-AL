@php
    $phases = [
        'Phase 1', 'Phase 2', 'Phase 3', 'Phase 4',
        'Black Pearl', 'Pearl', 'Ruby', 'Sapphire', 'Emerald',
    ];
@endphp

<datalist id="phase-options">
    @foreach ($phases as $phase)
        <option value="{{ $phase }}"></option>
    @endforeach
</datalist>
