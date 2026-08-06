@php
    $kategori = $kategori ?? null;
    $kategoriList = $kategoriList ?? collect();
    $filterRoute = $filterRoute ?? url()->current();
    $preserveParams = $preserveParams ?? [];
    $embedded = $embedded ?? false;
    $showKategoriSelector = $turnamen
        && $kategoriList instanceof \Illuminate\Support\Collection
        && $kategoriList->count() > 1;
@endphp

@if ($showKategoriSelector)
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('public/css/kategori-radios.css') }}?v={{ @filemtime(base_path('public/css/kategori-radios.css')) }}">
    @endpush
@endonce

@php
    $blockClass = $embedded
        ? 'kategori-radio-block kategori-radio-block--embedded'
        : 'kategori-radio-block mb-3';
@endphp

<div class="{{ $blockClass }}"
     @if ($kategori) data-workspace-kategori="{{ $kategori->id }}" @endif>
    <div class="kategori-radio-block__title">Kategori</div>

    @if ($embedded)
        <div class="kategori-radio-pills" role="radiogroup" aria-label="Kategori">
            @foreach ($kategoriList as $item)
                @php
                    $isSelected = $kategori && (int) $kategori->id === (int) $item->id;
                @endphp
                <label class="kategori-radio-pill {{ $isSelected ? 'is-selected' : '' }}">
                    <input type="radio"
                           name="id_kategori"
                           id="admin-id-kategori-{{ $item->id }}"
                           value="{{ $item->id }}"
                           {{ $isSelected ? 'checked' : '' }}
                           onchange="this.form.submit()">
                    <span class="kategori-radio-pill__text">{{ $item->nama }}</span>
                </label>
            @endforeach
        </div>

        <div class="kategori-radio-block__hint">
            Operasi (pemain, matchmaking, klasemen, bracket) hanya untuk kategori yang dipilih.
        </div>
    @else
        <form method="GET" action="{{ $filterRoute }}" id="admin-kategori-radio-form">
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
                        $isSelected = $kategori && (int) $kategori->id === (int) $item->id;
                    @endphp
                    <label class="kategori-radio-pill {{ $isSelected ? 'is-selected' : '' }}">
                        <input type="radio"
                               name="id_kategori"
                               id="admin-id-kategori-{{ $item->id }}"
                               value="{{ $item->id }}"
                               {{ $isSelected ? 'checked' : '' }}
                               onchange="this.form.submit()">
                        <span class="kategori-radio-pill__text">{{ $item->nama }}</span>
                    </label>
                @endforeach
            </div>

            <div class="kategori-radio-block__hint">
                Operasi (pemain, matchmaking, klasemen, bracket) hanya untuk kategori yang dipilih.
            </div>

            <noscript>
                <button type="submit" class="btn btn-primary btn-sm mt-2">
                    <i class="bi bi-funnel me-1"></i> Terapkan
                </button>
            </noscript>
        </form>
    @endif
</div>
@elseif ($turnamen && $kategori && ! $embedded)
    {{-- Single category: silent, expose id for JS (embedded parent can set data-workspace-kategori) --}}
    <div class="d-none" data-workspace-kategori="{{ $kategori->id }}" data-single-kategori="1"></div>
@endif
