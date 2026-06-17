@php
    $filterRoute = $filterRoute ?? url()->current();
    $preserveParams = $preserveParams ?? [];
    $turnamenList = $turnamenList ?? collect();
    $user = auth()->user();
    $isPanitia = $user && $user->isPanitia();
    $lockedTurnamen = $isPanitia ? $turnamenList->first() : null;
@endphp

@if (empty($sweetAlert))
    @if (request('id_turnamen') && ! $turnamen)
        <div class="alert alert-danger mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i>Turnamen tidak ditemukan.
        </div>
    @endif

    @if ($isPanitia && ! $lockedTurnamen)
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i>Akun panitia belum ditugaskan ke turnamen.
        </div>
    @endif
@endif

@if ($isPanitia && $lockedTurnamen)
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-lock text-muted"></i>
                <div>
                    <div class="small text-muted mb-0">Turnamen Anda</div>
                    <strong>{{ $lockedTurnamen->nama }}</strong>
                    <span class="badge bg-secondary ms-1">{{ ucfirst($lockedTurnamen->status) }}</span>
                </div>
            </div>
        </div>
    </div>
@elseif (! $isPanitia)
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ $filterRoute }}" class="row g-2 align-items-end">
            @foreach ($preserveParams as $param => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="{{ $param }}" value="{{ $value }}">
                @endif
            @endforeach

            <div class="col-md-6 col-lg-5">
                <label for="id_turnamen" class="form-label small text-muted mb-1">Turnamen</label>
                <select name="id_turnamen" id="id_turnamen" class="form-select" onchange="this.form.submit()">
                    <option value="" {{ ! request('id_turnamen') ? 'selected' : '' }}>
                        Default (turnamen aktif)
                    </option>
                    @foreach ($turnamenList as $item)
                        <option value="{{ $item->id }}"
                            {{ (string) request('id_turnamen') === (string) $item->id ? 'selected' : '' }}>
                            {{ $item->nama }} — {{ ucfirst($item->status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
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
            </div>
        </form>
    </div>
</div>
@endif
