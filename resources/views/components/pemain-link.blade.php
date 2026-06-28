@props([
    'pemain' => null,
    'id' => null,
    'name' => null,
    'class' => '',
])

@php
    $pemainId = $id ?? optional($pemain)->id;
    $label = $name ?? optional($pemain)->nama;
@endphp

@if ($pemainId && $label)
    <a href="{{ route('guest.pemain.show', $pemainId) }}"
       class="pemain-profile-link {{ $class }}"
       title="Lihat profil {{ $label }}">
        {{ $label }}
    </a>
@elseif ($label)
    <span class="{{ $class }}">{{ $label }}</span>
@else
    <span class="text-muted">—</span>
@endif
