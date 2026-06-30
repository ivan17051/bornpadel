@extends('layouts.admin')

@section('title', 'Edit Pemain')
@section('page-title', 'Edit Pemain')

@section('breadcrumb')
    @if (request('from') === 'directory')
        <li class="breadcrumb-item"><a href="{{ route('admin.pemain.directory', request()->only(['search', 'gender', 'registration', 'page'])) }}">Database Pemain</a></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}">Pemain</a></li>
    @endif
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $fotoUrl = $pemain->foto_url;
    $partnerPreviewSrc = $existingPartner && $existingPartner->foto
        ? $photoService->url($existingPartner->foto)
        : null;
@endphp

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ $fotoUrl }}"
                         id="edit-foto-preview"
                         alt="{{ $pemain->nama }}"
                         width="70"
                         height="70"
                         data-fallback="{{ $photoService->placeholderUrl() }}"
                         onerror="if (this.dataset.fallback) { this.onerror = null; this.src = this.dataset.fallback; }"
                         class="pemain-avatar rounded-circle object-fit-cover bg-light flex-shrink-0"
                         style="width: 70px; height: 70px; min-width: 70px; min-height: 70px;">
                    <h5 class="card-title mb-0">{{ $pemain->nama }}</h5>
                </div>
                @if ($pemain->tgl_lahir && $pemain->usia)
                    <span class="badge text-bg-secondary text-right">{{ $pemain->usia }} tahun</span>
                @endif
            </div>
            <div class="card-body">
                @if ($turnamenPesertaEntries->isNotEmpty())
                    <div class="mb-4">
                        <h6 class="text-muted text-uppercase small mb-2">Riwayat Turnamen</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Turnamen</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($turnamenPesertaEntries as $peserta)
                                        @php
                                            $partner = null;
                                            if ((int) $peserta->id_pemain1 === (int) $pemain->id && $peserta->pemain2) {
                                                $partner = $peserta->pemain2;
                                            } elseif ((int) $peserta->id_pemain2 === (int) $pemain->id && $peserta->pemain1) {
                                                $partner = $peserta->pemain1;
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ optional($peserta->turnamen)->nama ?? '—' }}
                                                @if ($partner)
                                                    <div class="small text-muted">Partner: {{ $partner->nama }}</div>
                                                @elseif (optional($peserta->turnamen)->isDouble() && (int) $peserta->id_pemain1 === (int) $pemain->id)
                                                    <div class="small text-warning">Belum ada pemain 2</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge status-badge-{{ $peserta->status }}">
                                                    {{ ucfirst($peserta->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="form-text mb-0">Ubah status pendaftaran dari halaman daftar pemain dengan filter turnamen.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.pemain.update', $pemain) }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    @if (request('from') === 'directory')
                        <input type="hidden" name="from" value="directory">
                        @foreach (request()->only(['search', 'gender', 'registration', 'page']) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                    @elseif (request('id_turnamen'))
                        <input type="hidden" name="id_turnamen" value="{{ request('id_turnamen') }}">
                    @endif
                    <x-pemain-photo-input
                        input-id="edit-foto"
                        preview-id="edit-foto-preview"
                        label="Foto Pemain"
                        :preview-src="$fotoUrl"
                        :show-preview="false" />
                    <p class="form-text">Format: JPG, PNG, atau WebP. Maks. 5 MB. Kosongkan jika tidak ingin mengubah foto.</p>
                    @include('admin.pemain._form', ['pemain' => $pemain])
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                        @if (request('from') === 'directory')
                            <a href="{{ route('admin.pemain.directory', request()->only(['search', 'gender', 'registration', 'page'])) }}" class="btn btn-outline-secondary">Batal</a>
                        @else
                            <a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}" class="btn btn-outline-secondary">Batal</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if ($partnerPeserta)
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-plus me-2"></i> Tambah Pemain 2
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase">Turnamen</div>
                        <strong>{{ $partnerPeserta->turnamen->nama }}</strong>
                    </div>

                    @if (! $showPartnerForm)
                        <div class="alert alert-light border mb-4">
                            <i class="bi bi-search me-2"></i>
                            Cari nomor HP pemain 2 terlebih dahulu. Jika sudah ada di database, data akan otomatis terisi.
                        </div>
                        <form action="{{ route('admin.pemain.peserta.partner.lookup', $partnerPeserta) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="partner_lookup_no_hp" class="form-label">Nomor HP / WhatsApp Pemain 2 <span class="text-danger">*</span></label>
                                <input type="tel"
                                       name="no_hp"
                                       id="partner_lookup_no_hp"
                                       class="form-control @error('no_hp') is-invalid @enderror"
                                       value="{{ old('no_hp') }}"
                                       placeholder="08xxxxxxxxxx"
                                       required>
                                @error('no_hp')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i> Cari & Lanjutkan
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="mb-3">
                            <div class="text-muted small text-uppercase">No. HP Pemain 2</div>
                            <strong>{{ $partnerNoHp }}</strong>
                        </div>

                        @if ($isPartnerExisting)
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="bi bi-person-check me-1"></i>
                                Data pemain 2 ditemukan. Periksa dan perbarui jika perlu.
                            </div>
                        @else
                            <div class="alert alert-light border py-2 small mb-3">
                                <i class="bi bi-person-plus me-1"></i>
                                Pemain 2 belum terdaftar. Lengkapi data di bawah.
                            </div>
                        @endif

                        <form action="{{ route('admin.pemain.peserta.partner.store', $partnerPeserta) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @include('admin.pemain.partials.player-fields', [
                                'prefix' => '',
                                'labelPrefix' => 'Pemain 2',
                                'existingPemain' => $existingPartner,
                                'previewSrc' => $partnerPreviewSrc,
                                'inputId' => 'partner-foto',
                                'previewId' => 'partner-foto-preview',
                                'phoneReadonly' => true,
                                'phoneValue' => $partnerNoHp,
                            ])
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i> Simpan Pemain 2
                                </button>
                                <a href="{{ route('admin.pemain.edit', array_merge(['pemain' => $pemain->id], request()->only('id_turnamen'))) }}"
                                   class="btn btn-outline-secondary">
                                    Ganti Nomor HP
                                </a>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    .pemain-avatar { object-fit: cover; display: block; }
</style>
@endpush
