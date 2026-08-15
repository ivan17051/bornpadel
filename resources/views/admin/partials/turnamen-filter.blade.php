@php
    $filterRoute = $filterRoute ?? url()->current();
    $preserveParams = $preserveParams ?? [];
    $turnamenList = $turnamenList ?? collect();
    $requireTurnamenSelection = $requireTurnamenSelection ?? false;
    $useSelect2TurnamenFilter = $useSelect2TurnamenFilter ?? false;
    $turnamenFilterMax = $turnamenFilterMax ?? null;
    $user = auth()->user();
    $isPanitia = $user && $user->isPanitia();
    $assignedCount = $turnamenList instanceof \Illuminate\Support\Collection ? $turnamenList->count() : 0;
    $emptyOptionLabel = $emptyOptionLabel ?? (
        $requireTurnamenSelection || ($isPanitia && $assignedCount > 1)
            ? 'Pilih turnamen'
            : 'Default (turnamen aktif)'
    );
    $lockedTurnamen = $isPanitia && $assignedCount === 1 ? $turnamenList->first() : null;
    $showTurnamenSelect = ! $isPanitia || $assignedCount > 1;

    $filterTurnamenList = $turnamenList;

    $kategori = $kategori ?? null;
    $kategoriList = $kategoriList ?? collect();
    $showKategoriSelector = $turnamen
        && $kategoriList instanceof \Illuminate\Support\Collection
        && $kategoriList->count() > 1;

    // Never re-submit an old kategori id when switching tournament via hidden preserve.
    $formPreserveParams = collect($preserveParams)
        ->except(['id_kategori', 'id_turnamen'])
        ->all();
@endphp

@if (empty($sweetAlert))
    @if (request('id_turnamen') && ! $turnamen)
        <div class="alert alert-danger mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i>Turnamen tidak ditemukan.
        </div>
    @endif

    @if ($isPanitia && $assignedCount === 0)
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i>Akun panitia belum ditugaskan ke turnamen.
        </div>
    @endif
@endif

@if ($isPanitia && $lockedTurnamen)
    <div class="card mb-3" @if ($kategori) data-workspace-kategori="{{ $kategori->id }}" @endif>
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-lock text-muted"></i>
                <div>
                    <div class="small text-muted mb-0">Turnamen Anda</div>
                    <strong>{{ $lockedTurnamen->nama }}</strong>
                    <span class="badge bg-secondary ms-1">{{ ucfirst($lockedTurnamen->status) }}</span>
                </div>
            </div>

            @if ($showKategoriSelector)
                <form method="GET" action="{{ $filterRoute }}">
                    @foreach ($formPreserveParams as $param => $value)
                        @if ($value !== null && $value !== '')
                            <input type="hidden" name="{{ $param }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="id_turnamen" value="{{ $lockedTurnamen->id }}">

                    @include('admin.partials.kategori-filter', [
                        'embedded' => true,
                        'filterRoute' => $filterRoute,
                        'turnamen' => $turnamen,
                        'kategori' => $kategori,
                        'kategoriList' => $kategoriList,
                    ])
                </form>
            @elseif ($turnamen && $kategori)
                <div class="d-none" data-workspace-kategori="{{ $kategori->id }}" data-single-kategori="1"></div>
            @endif
        </div>
    </div>
@elseif ($showTurnamenSelect)
<div class="card mb-3" @if ($kategori) data-workspace-kategori="{{ $kategori->id }}" @endif>
    <div class="card-body py-3">
        <form method="GET" action="{{ $filterRoute }}" id="admin-turnamen-filter-form">
            @foreach ($formPreserveParams as $param => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="{{ $param }}" value="{{ $value }}">
                @endif
            @endforeach

            <div class="row g-2 align-items-end">
                <div class="col-md-6 col-lg-5 {{ $useSelect2TurnamenFilter ? 'turnamen-filter-select2-wrap' : '' }}">
                    <label for="id_turnamen" class="form-label small text-muted mb-1">Turnamen</label>
                    <select name="id_turnamen"
                            id="id_turnamen"
                            class="form-select {{ $useSelect2TurnamenFilter ? 'turnamen-filter-select2' : '' }}"
                            @if (! $useSelect2TurnamenFilter)
                                onchange="BornPadelAdmin.submitTurnamenFilter(this.form)"
                            @endif
                            @if ($useSelect2TurnamenFilter)
                                data-select2-turnamen="1"
                                data-placeholder="{{ $emptyOptionLabel }}"
                                data-allow-clear="{{ $requireTurnamenSelection ? '0' : '1' }}"
                                @if ($turnamenFilterMax)
                                    data-turnamen-visible-max="{{ $turnamenFilterMax }}"
                                @endif
                            @endif>
                        <option value="" {{ ! request('id_turnamen') ? 'selected' : '' }}>
                            {{ $emptyOptionLabel }}
                        </option>
                        @foreach ($filterTurnamenList as $item)
                            <option value="{{ $item->id }}"
                                {{ (string) request('id_turnamen') === (string) $item->id ? 'selected' : '' }}>
                                {{ $item->nama }} — {{ ucfirst($item->status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <div class="d-flex flex-wrap gap-2">
                        <noscript>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i> Terapkan
                            </button>
                        </noscript>
                        @if (request('id_turnamen'))
                            <a href="{{ $filterRoute }}" class="btn btn-outline-secondary">
                                Reset
                            </a>
                        @endif
                        @if ($turnamen && $user && $user->isAdmin())
                            <a href="{{ route('admin.turnamen.edit', $turnamen) }}"
                               class="btn btn-outline-primary">
                                <i class="bi bi-pencil-square me-1"></i> Edit Turnamen
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            @if ($showKategoriSelector)
                @include('admin.partials.kategori-filter', [
                    'embedded' => true,
                    'filterRoute' => $filterRoute,
                    'turnamen' => $turnamen,
                    'kategori' => $kategori,
                    'kategoriList' => $kategoriList,
                ])
            @elseif ($turnamen && $kategori)
                <div class="d-none" data-workspace-kategori="{{ $kategori->id }}" data-single-kategori="1"></div>
            @endif
        </form>
    </div>
</div>
@endif
