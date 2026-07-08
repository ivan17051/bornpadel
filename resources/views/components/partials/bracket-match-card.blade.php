@php
    $isThirdPlace = $isThirdPlace ?? ! empty($match['is_third_place']);
    $slot1Editable = $slot1Editable ?? false;
    $slot2Editable = $slot2Editable ?? false;
@endphp
<div class="bracket-match {{ $isThirdPlace ? 'is-third-place' : '' }} {{ ($match['status'] ?? '') === 'completed' ? 'is-completed' : '' }} {{ ! empty($match['pemenang_id']) ? 'has-winner' : '' }}">
    <div class="bracket-player {{ ! empty($match['pemenang_id']) && ($match['pemain1_id'] ?? null) === $match['pemenang_id'] ? 'is-winner' : '' }} {{ empty($match['pemain1_id']) && empty($match['pemain1_ids']) ? 'is-tbd' : '' }} {{ $slot1Editable ? 'is-editable' : '' }}"
        @if($slot1Editable)
            role="button"
            tabindex="0"
            title="Klik untuk menukar peserta"
            data-match-id="{{ $match['id'] }}"
            data-slot="1"
            data-pemain-id="{{ $match['pemain1_id'] }}"
            data-peserta-id="{{ $match['peserta1_id'] }}"
        @endif>
        <span class="bracket-player-name">
            @if (! empty($match['pemain1_ids']))
                <x-pemain-names :pemain-ids="$match['pemain1_ids']" :nama="$match['pemain1']" />
            @else
                {{ $match['pemain1'] }}
            @endif
        </span>
        @if (! empty($match['skor']) && ($match['status'] ?? '') === 'completed')
            <span class="bracket-score-badge">{{ collect(explode(', ', $match['skor']))->map(fn($s) => explode('-', $s)[0] ?? '')->implode(' ') }}</span>
        @endif
    </div>
    <div class="bracket-player {{ ! empty($match['pemenang_id']) && ($match['pemain2_id'] ?? null) === $match['pemenang_id'] ? 'is-winner' : '' }} {{ empty($match['pemain2_id']) && empty($match['pemain2_ids']) ? 'is-tbd' : '' }} {{ $slot2Editable ? 'is-editable' : '' }}"
        @if($slot2Editable)
            role="button"
            tabindex="0"
            title="Klik untuk menukar peserta"
            data-match-id="{{ $match['id'] }}"
            data-slot="2"
            data-pemain-id="{{ $match['pemain2_id'] }}"
            data-peserta-id="{{ $match['peserta2_id'] }}"
        @endif>
        <span class="bracket-player-name">
            @if (! empty($match['pemain2_ids']))
                <x-pemain-names :pemain-ids="$match['pemain2_ids']" :nama="$match['pemain2']" />
            @else
                {{ $match['pemain2'] }}
            @endif
        </span>
        @if (! empty($match['skor']) && ($match['status'] ?? '') === 'completed')
            <span class="bracket-score-badge">{{ collect(explode(', ', $match['skor']))->map(fn($s) => explode('-', $s)[1] ?? '')->implode(' ') }}</span>
        @endif
    </div>
    @if (($match['status'] ?? '') === 'scheduled' && ! empty($match['pemain1_id']) && ! empty($match['pemain2_id']))
        <div class="bracket-match-status"><span class="badge bg-secondary">Upcoming</span></div>
    @elseif (($match['status'] ?? '') === 'scheduled')
        <div class="bracket-match-status"><span class="badge bg-light text-dark border">Menunggu</span></div>
    @elseif (! empty($match['is_bye']))
        <div class="bracket-match-status"><span class="badge bg-info text-dark">Bye</span></div>
    @endif
</div>
