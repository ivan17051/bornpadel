@extends('layouts.admin')

@section('title', 'Daftarkan Pemain Existing')
@section('page-title', 'Daftarkan Pemain Existing')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}">Pemain</a></li>
    <li class="breadcrumb-item active">Daftarkan Existing</li>
@endsection

@section('sweetalert-flash', true)

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => route('admin.pemain.available'),
    'sweetAlert' => true,
    'requireTurnamenSelection' => true,
])

@if ($turnamen)
    @if ($turnamen->requiresPairRegistration())
        <div class="alert alert-light border mb-3">
            <i class="bi bi-people me-2"></i>
            Turnamen double: daftarkan pemain individu dulu, lalu atur pasangan sebelum menutup pendaftaran.
        </div>
    @elseif ($turnamen->randomizesPartners())
        <div class="alert alert-light border mb-3">
            <i class="bi bi-shuffle me-2"></i>
            Turnamen single: pemain didaftarkan individu. Pasangan diacak otomatis saat pendaftaran ditutup.
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.pemain.available') }}" class="row g-2 align-items-end">
                <input type="hidden" name="id_turnamen" value="{{ $turnamen->id }}">
                <div class="col-md-5">
                    <label class="form-label small text-muted">Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama atau no. HP..."
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Semua</option>
                        <option value="male" {{ request('gender') === 'male' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="female" {{ request('gender') === 'female' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.pemain.available', ['id_turnamen' => $turnamen->id]) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card"
         id="available-pemain-card"
         data-turnamen-id="{{ $turnamen->id }}"
         data-bulk-register-url="{{ route('admin.pemain.bulk-register') }}"
         data-store-new-url="{{ route('admin.pemain.store-new') }}">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-0">
                    Pemain belum terdaftar
                    <small class="text-muted fw-normal">— {{ $turnamen->nama }}</small>
                </h5>
            </div>
            <div class="ms-auto d-inline-flex align-items-center justify-content-end gap-2 flex-wrap text-end">
                <span class="badge text-bg-secondary">{{ number_format($availableCount) }} tersedia</span>
                <span class="badge text-bg-primary d-none" id="available-selected-count">0 dipilih</span>
                @if ($turnamen->maks_peserta)
                    <span class="badge text-bg-light text-dark border">
                        Maks. {{ $turnamen->maks_peserta }} disetujui
                    </span>
                @endif
                <button type="button"
                        class="btn btn-success btn-sm btn-bulk-register"
                        disabled
                        title="Pilih pemain pada tabel terlebih dahulu">
                    <i class="bi bi-person-check me-1"></i> Daftarkan Terpilih
                </button>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#tambahPemainModal">
                    <i class="bi bi-plus-lg me-1"></i> Tambah
                </button>
                <a href="{{ route('admin.turnamen-operasi.index', ['id_turnamen' => $turnamen->id, 'tab' => 'pemain']) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle" id="available-pemain-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 2.5rem;">
                                <input type="checkbox" class="form-check-input" id="select-all-available" title="Pilih semua di halaman ini">
                            </th>
                            <th style="width: 3.5rem;"></th>
                            <th>#</th>
                            <th>Nama</th>
                            <th class="d-none d-md-table-cell">No. HP</th>
                            <th class="d-none d-lg-table-cell">Gender</th>
                            <th class="d-none d-lg-table-cell">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pemain as $entry)
                            @php
                                $phoneService = app(\App\Services\PhoneNumberService::class);
                                $parsedPhone = $phoneService->parse($entry->no_hp);
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox"
                                           class="form-check-input available-pemain-checkbox"
                                           value="{{ $entry->id }}">
                                </td>
                                <td>
                                    <x-pemain-avatar :pemain="$entry" :size="36" />
                                </td>
                                <td>{{ $pemain->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $entry->nama }}</td>
                                <td class="d-none d-md-table-cell">{{ $parsedPhone['country_code'] }} {{ $parsedPhone['local_number'] }}</td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $entry->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                                </td>
                                <td class="d-none d-lg-table-cell">{{ number_format((float) $entry->rating, 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Tidak ada pemain tersedia untuk didaftarkan
                                    @if (request()->filled('search') || request()->filled('gender'))
                                        dengan filter ini
                                    @endif.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($pemain->hasPages())
            <div class="card-footer">
                {{ $pemain->links() }}
            </div>
        @endif
    </div>

    @include('admin.pemain.partials.tambah-pemain-modal', ['turnamen' => $turnamen])
@else
    <div class="alert alert-light border">
        <i class="bi bi-info-circle me-2"></i>
        Pilih turnamen terlebih dahulu untuk melihat pemain yang belum terdaftar.
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initAvailablePemainActions();

    @if (session('success'))
        BornPadelAdmin.showAlert(@json(session('success')), 'success');
    @endif

    @if (session('error'))
        BornPadelAdmin.showAlert(@json(session('error')), 'error');
    @endif

    @if (request('id_turnamen') && ! $turnamen)
        BornPadelAdmin.showAlert('Turnamen tidak ditemukan.', 'error');
    @endif

    @if (auth()->user()->isPanitia() && $turnamenList->isEmpty())
        BornPadelAdmin.showAlert('Akun panitia belum ditugaskan ke turnamen.', 'warning');
    @endif
});
</script>
@endpush
