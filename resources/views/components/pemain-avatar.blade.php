@props([
    'pemain',
    'size' => 48,
    'class' => '',
])

@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $src = $photoService->url(optional($pemain)->foto);
    $placeholder = $photoService->placeholderUrl();
    $alt = optional($pemain)->nama ?? 'Pemain';
    $sizePx = (int) $size;
@endphp

<img src="{{ $src }}"
     alt="{{ $alt }}"
     width="{{ $sizePx }}"
     height="{{ $sizePx }}"
     loading="lazy"
     decoding="async"
     data-fallback="{{ $placeholder }}"
     onerror="if (this.dataset.fallback) { this.onerror = null; this.src = this.dataset.fallback; }"
     {{ $attributes->merge(['class' => 'pemain-avatar rounded-circle object-fit-cover bg-light flex-shrink-0 ' . $class]) }}
     style="width: {{ $sizePx }}px; height: {{ $sizePx }}px; min-width: {{ $sizePx }}px; min-height: {{ $sizePx }}px;">
