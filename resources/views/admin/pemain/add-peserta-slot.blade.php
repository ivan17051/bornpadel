@extends('layouts.admin')

@section('title', 'Tambah Pemain ' . $slot)
@section('page-title', 'Tambah Pemain ' . $slot)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen]) }}">Pemain</a></li>
    <li class="breadcrumb-item active">Tambah Pemain {{ $slot }}</li>
@endsection

@section('content')
@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $previewSrc = $existingPemain && $existingPemain->foto
        ? $photoService->url($existingPemain->foto)
        : null;
@endphp

<div class="row justify-content-center">
    <div class="col-lg-5 mb-4 mb-lg-0">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Pendaftaran Turnamen</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small text-uppercase">Turnamen</div>
                    <strong>{{ $peserta->turnamen->nama }}</strong>
                    <span class="badge text-bg-light text-dark border ms-1">{{ $peserta->turnamen->jenis_label }}</span>
                </div>

                @if ($otherPemain)
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase">Pemain {{ $slot === 1 ? 2 : 1 }}</div>
                        <strong>{{ $otherPemain->nama }}</strong>
                        <div class="small text-muted">{{ $otherPemain->no_hp }}</div>
                    </div>
                @endif

                @if ($showForm)
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase">No. HP Pemain {{ $slot }}</div>
                        <strong>{{ $noHp }}</strong>
                    </div>
                    <hr>
                    <a href="{{ route('admin.pemain.peserta.slot.create', ['peserta' => $peserta->id, 'slot' => $slot]) }}"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Ganti Nomor HP
                    </a>
                @endif

                <div class="{{ $showForm ? 'mt-3' : '' }}">
                    <div class="text-muted small text-uppercase">Status Pendaftaran</div>
                    <span class="badge status-badge-{{ $peserta->status }}">{{ ucfirst($peserta->status) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                @if (! $showForm)
                    <div class="alert alert-light border mb-4">
                        <i class="bi bi-search me-2"></i>
                        Masukkan nomor HP pemain {{ $slot }}. Jika sudah ada di database, data akan ditampilkan untuk diperiksa dan diperbarui.
                    </div>
                    <form action="{{ route('admin.pemain.peserta.slot.lookup', ['peserta' => $peserta->id, 'slot' => $slot]) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <x-phone-input name="no_hp"
                                           id="slot_lookup_no_hp"
                                           label="Nomor HP / WhatsApp Pemain {{ $slot }}"
                                           :value="old('no_hp')" />
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Cari & Lanjutkan
                            </button>
                            <a href="{{ route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen]) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                @else
                    @if ($existingPemain)
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-person-check me-1"></i>
                            Data pemain ditemukan. Periksa dan perbarui jika perlu.
                        </div>
                    @else
                        <div class="alert alert-light border py-2 small mb-3">
                            <i class="bi bi-person-plus me-1"></i>
                            Nomor HP belum terdaftar. Lengkapi data di bawah.
                        </div>
                    @endif

                    <form action="{{ route('admin.pemain.peserta.slot.store', ['peserta' => $peserta->id, 'slot' => $slot]) }}"
                          method="POST"
                          enctype="multipart/form-data">
                        @csrf
                        @include('admin.pemain.partials.player-fields', [
                            'prefix' => '',
                            'labelPrefix' => 'Pemain ' . $slot,
                            'existingPemain' => $existingPemain,
                            'previewSrc' => $previewSrc,
                            'inputId' => 'slot-foto',
                            'previewId' => 'slot-foto-preview',
                            'phoneReadonly' => true,
                            'phoneValue' => $noHp,
                        ])
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Simpan Pemain {{ $slot }}
                            </button>
                            <a href="{{ route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen]) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
