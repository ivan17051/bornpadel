@php
    $kategori = $kategori ?? null;
    $kategoriList = $kategoriList ?? collect();
    $filterRoute = $filterRoute ?? url()->current();
    $preserveParams = $preserveParams ?? [];
    $selectorHint = $selectorHint ?? 'Pilih kategori kompetisi untuk menampilkan data yang sesuai.';
    $radioName = $radioName ?? 'guest-id-kategori';
    $disableWhenNotOpen = $disableWhenNotOpen ?? false;
    $showKategoriSelector = isset($turnamen)
        && $kategoriList instanceof \Illuminate\Support\Collection
        && $kategoriList->count() > 1;
@endphp

@if ($showKategoriSelector)
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('public/css/kategori-radios.css') }}?v={{ @filemtime(base_path('public/css/kategori-radios.css')) }}">
    @endpush
@endonce

<div class="kategori-radio-block kategori-radio-block--guest mb-4">
    <div class="kategori-radio-block__title">Kategori</div>

    <form method="GET" action="{{ $filterRoute }}" id="guest-kategori-radio-form">
        @foreach ($preserveParams as $param => $value)
            @if ($value !== null && $value !== '')
                <input type="hidden" name="{{ $param }}" value="{{ $value }}">
            @endif
        @endforeach

        @if ($turnamen)
            <input type="hidden" name="id_turnamen" value="{{ $turnamen->id }}">
        @endif

        <div class="kategori-radio-pills" role="radiogroup" aria-label="Kategori">
            @foreach ($kategoriList as $item)
                @php
                    $isOpen = $item->isRegistrationOpen();
                    $isDisabled = $disableWhenNotOpen && ! $isOpen;
                    $isSelected = $kategori && (int) $kategori->id === (int) $item->id && ! $isDisabled;
                @endphp
                <label class="kategori-radio-pill {{ $isSelected ? 'is-selected' : '' }} {{ $isDisabled ? 'is-disabled' : '' }}"
                       @if ($isDisabled) title="Pendaftaran kategori ini ditutup" @endif>
                    <input type="radio"
                           name="id_kategori"
                           id="{{ $radioName }}-{{ $item->id }}"
                           value="{{ $item->id }}"
                           {{ $isSelected ? 'checked' : '' }}
                           {{ $isDisabled ? 'disabled' : '' }}
                           @if (! $isDisabled) onchange="this.form.submit()" @endif>
                    <span class="kategori-radio-pill__text">{{ $item->nama }}</span>
                </label>
            @endforeach
        </div>

        @if (! empty($selectorHint))
            <div class="kategori-radio-block__hint">{{ $selectorHint }}</div>
        @endif

        <noscript>
            <button type="submit" class="btn btn-bp btn-sm mt-2">Terapkan</button>
        </noscript>
    </form>
</div>
@endif
