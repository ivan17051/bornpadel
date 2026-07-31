@extends('layouts.guest')

@section('title', 'Beranda')

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $featuredTurnamen ?? null,
        'ogUrl' => route('guest.landing'),
        'ogTitle' => 'Born Padel',
        'ogDescription' => 'Jadwal turnamen padel, klasemen, bracket, dan pendaftaran di Born Padel.',
    ])
@endsection

@php
    use Carbon\Carbon;

    $formatMonth = static fn (int $month) => Carbon::createFromDate(2000, $month, 1)
        ->locale('id')
        ->translatedFormat('F');
@endphp

@push('styles')
<style>
    .guest-card-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.75rem;
    }

    .guest-card-meta-price {
        margin-left: auto;
        text-align: right;
    }

    .guest-card-syarat-text {
        white-space: pre-line;
    }

    .guest-card-actions {
        display: grid;
        gap: 0.5rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .guest-card-actions--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .guest-card-actions .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
        min-height: 2.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
    }

    @media (min-width: 576px) {
        .guest-card-actions--3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .guest-card-actions--3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .guest-card-actions--3 .btn:last-child {
            grid-column: 1 / -1;
        }
    }

    .guest-completed-section {
        margin-top: 3rem;
        padding-top: 2.5rem;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
    }

    .guest-completed-filter {
        max-width: 28rem;
        margin-left: auto;
        margin-right: auto;
    }
</style>
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11 col-xl-10">
        <div class="text-center mb-4 mb-md-5">
            <h1 class="h3 fw-bold mb-2">Turnamen Born Padel</h1>
            <p class="text-muted mb-0">Daftar turnamen terbuka atau lihat klasemen dan bracket turnamen berlangsung.</p>
        </div>

        @if ($activeTournaments->isNotEmpty())
            <div class="row g-4">
                @foreach ($activeTournaments as $item)
                    @include('guest.partials.tournament-card', ['item' => $item])
                @endforeach
            </div>
        @else
            <div class="card guest-card text-center py-5 px-3">
                <div class="card-body">
                    <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
                    <h2 class="h4 fw-bold mb-2">Belum Ada Turnamen Aktif</h2>
                    <p class="text-muted mb-0 mx-auto" style="max-width: 28rem;">
                        Saat ini tidak ada turnamen dengan pendaftaran terbuka atau sedang berlangsung.
                    </p>
                </div>
            </div>
        @endif

        @if ($completedFilter['hasAny'])
            <section class="guest-completed-section" id="turnamen-selesai">
                <div class="text-center mb-4">
                    <h2 class="h4 fw-bold mb-2">Turnamen Selesai</h2>
                    <p class="text-muted mb-0">Lihat hasil turnamen yang sudah selesai.</p>
                </div>

                <form method="GET"
                      action="{{ route('guest.landing') }}"
                      class="guest-completed-filter row g-2 align-items-end justify-content-center mb-4"
                      id="completed-tournament-filter">
                    <div class="col-6 col-sm-5">
                        <label for="completed_month" class="form-label small text-muted mb-1">Bulan</label>
                        <select name="completed_month" id="completed_month" class="form-select form-select-sm">
                            @foreach ($completedMonths as $monthOption)
                                <option value="{{ $monthOption }}"
                                    {{ (int) $completedFilter['month'] === $monthOption ? 'selected' : '' }}>
                                    {{ $formatMonth($monthOption) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-sm-4">
                        <label for="completed_year" class="form-label small text-muted mb-1">Tahun</label>
                        <select name="completed_year" id="completed_year" class="form-select form-select-sm">
                            @foreach ($completedFilter['years'] as $yearOption)
                                <option value="{{ $yearOption }}"
                                    {{ (int) $completedFilter['year'] === $yearOption ? 'selected' : '' }}>
                                    {{ $yearOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-3">
                        <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-funnel me-1"></i> Tampilkan
                        </button>
                    </div>
                </form>

                @if ($completedTournaments->isNotEmpty())
                    <div class="row g-4">
                        @foreach ($completedTournaments as $item)
                            @include('guest.partials.tournament-card', ['item' => $item])
                        @endforeach
                    </div>
                @else
                    <div class="card guest-card text-center py-4 px-3">
                        <div class="card-body">
                            <i class="bi bi-calendar2-week display-6 text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">
                                Tidak ada turnamen selesai pada
                                {{ $formatMonth($completedFilter['month']) }}
                                {{ $completedFilter['year'] }}.
                            </p>
                        </div>
                    </div>
                @endif
            </section>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('completed-tournament-filter');
    if (form) {
        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => form.submit());
        });
    }

    async function copyShareUrl(url) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
            return;
        }

        const input = document.createElement('input');
        input.value = url;
        input.setAttribute('readonly', '');
        input.style.position = 'absolute';
        input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }

    document.querySelectorAll('.js-share-register').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.shareUrl;
            const title = button.dataset.shareTitle || 'Born Padel';
            const text = button.dataset.shareText || title;
            const originalHtml = button.innerHTML;

            try {
                if (navigator.share) {
                    await navigator.share({ title, text, url });
                    return;
                }

                await copyShareUrl(url);
                button.innerHTML = '<i class="bi bi-check2 me-1"></i> Link Disalin';
                window.setTimeout(() => {
                    button.innerHTML = originalHtml;
                }, 2000);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                try {
                    await copyShareUrl(url);
                    button.innerHTML = '<i class="bi bi-check2 me-1"></i> Link Disalin';
                    window.setTimeout(() => {
                        button.innerHTML = originalHtml;
                    }, 2000);
                } catch (_) {
                    window.prompt('Salin link pendaftaran:', url);
                }
            }
        });
    });
});
</script>
@endpush
