@php
    $hasAny = ! empty($first) || ! empty($second) || ! empty($third);
    $placeholder = app(\App\Services\PemainPhotoService::class)->placeholderUrl();
@endphp
@if ($hasAny)
    <div class="bracket-podium mb-4">
        <div class="bracket-podium-grid">
            @foreach ([
                ['place' => 2, 'label' => 'Juara 2', 'icon' => 'bi-award-fill', 'entry' => $second ?? null, 'mod' => 'second'],
                ['place' => 1, 'label' => 'Juara 1', 'icon' => 'bi-trophy-fill', 'entry' => $first ?? null, 'mod' => 'first'],
                ['place' => 3, 'label' => 'Juara 3', 'icon' => 'bi-award-fill', 'entry' => $third ?? null, 'mod' => 'third'],
            ] as $slot)
                @if (! empty($slot['entry']['label']))
                    <div class="bracket-podium-card bracket-podium-card--{{ $slot['mod'] }}">
                        <div class="bracket-podium-rank">
                            <i class="bi {{ $slot['icon'] }}"></i>
                            <span>{{ $slot['label'] }}</span>
                        </div>
                        <div class="bracket-podium-photos">
                            @forelse ($slot['entry']['players'] ?? [] as $player)
                                <img src="{{ $player['foto_url'] }}"
                                     alt="{{ $player['nama'] }}"
                                     class="bracket-podium-photo"
                                     width="56"
                                     height="56"
                                     loading="lazy"
                                     decoding="async"
                                     title="{{ $player['nama'] }}"
                                     onerror="this.onerror=null;this.src='{{ $placeholder }}';">
                            @empty
                                <div class="bracket-podium-photo bracket-podium-photo--empty">
                                    <i class="bi bi-person"></i>
                                </div>
                            @endforelse
                        </div>
                        <div class="bracket-podium-names">
                            @if (! empty($slot['entry']['players']))
                                @foreach ($slot['entry']['players'] as $player)
                                    <x-pemain-link :id="$player['id'] ?? null" :name="$player['nama'] ?? ''" />@if (! $loop->last)<span class="text-muted"> / </span>@endif
                                @endforeach
                            @else
                                {{ $slot['entry']['label'] }}
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
