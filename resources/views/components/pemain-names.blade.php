@props([
    'pemainIds' => [],
    'pemain' => null,
    'nama' => null,
    'class' => '',
])

@php
    $ids = $pemainIds;

    if ($pemain) {
        $ids = [$pemain->id];
        $nama = $nama ?? $pemain->nama;
    }

    $ids = array_values(array_filter($ids));
@endphp

@if (count($ids) > 1)
    @foreach ($ids as $index => $pemainId)
        @php
            $player = \App\Models\Pemain::find($pemainId);
            $playerName = $player->nama ?? (explode(' / ', (string) $nama)[$index] ?? 'Pemain');
        @endphp
        <x-pemain-link :id="$pemainId" :name="$playerName" :class="$class" />@if (! $loop->last)<span class="text-muted"> / </span>@endif
    @endforeach
@elseif (count($ids) === 1)
    @php
        $player = \App\Models\Pemain::find($ids[0]);
    @endphp
    <x-pemain-link :pemain="$player" :name="$nama ?? optional($player)->nama" :class="$class" />
@elseif ($nama)
    <span class="{{ $class }}">{{ $nama }}</span>
@else
    <span class="text-muted">—</span>
@endif
