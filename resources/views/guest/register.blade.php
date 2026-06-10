@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">Form Pendaftaran</h1>
            <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
        </div>

        <div class="card guest-card mb-4">
            <div class="card-body py-3 px-4">
                <div class="row text-center g-3">
                    <div class="col-6">
                        <div class="info-label">Biaya</div>
                        <strong class="text-success">Rp {{ number_format($turnamen->harga, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Status</div>
                        <span class="badge badge-open">Pendaftaran Dibuka</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card guest-card">
            <div class="card-header py-3">
                <i class="bi bi-person-vcard me-2"></i> Data Peserta
            </div>
            <div class="card-body p-4">
                <form action="{{ route('guest.register.store') }}" method="POST" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="nama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text"
                               name="nama"
                               id="nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama') }}"
                               placeholder="Masukkan nama lengkap"
                               required
                               autocomplete="name">
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="tgl_lahir" class="form-label fw-semibold">Tanggal Lahir <span class="text-danger">*</span></label>
                        <input type="date"
                               name="tgl_lahir"
                               id="tgl_lahir"
                               class="form-control @error('tgl_lahir') is-invalid @enderror"
                               value="{{ old('tgl_lahir') }}"
                               required
                               max="{{ date('Y-m-d', strtotime('-1 day')) }}">
                        @error('tgl_lahir')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="gender" class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="gender"
                                id="gender"
                                class="form-select @error('gender') is-invalid @enderror"
                                required>
                            <option value="" disabled {{ old('gender') ? '' : 'selected' }}>Pilih jenis kelamin</option>
                            <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                        @error('gender')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="no_hp" class="form-label fw-semibold">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
                        <input type="tel"
                               name="no_hp"
                               id="no_hp"
                               class="form-control @error('no_hp') is-invalid @enderror"
                               value="{{ old('no_hp') }}"
                               placeholder="08xxxxxxxxxx"
                               required
                               inputmode="tel"
                               autocomplete="tel">
                        @error('no_hp')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="rating" class="form-label fw-semibold">Rating (opsional)</label>
                        <input type="number"
                               name="rating"
                               id="rating"
                               class="form-control @error('rating') is-invalid @enderror"
                               value="{{ old('rating') }}"
                               placeholder="Contoh: 3.5"
                               min="0"
                               max="10"
                               step="0.1">
                        <div class="form-text">Perkiraan level permainan Anda (skala 0–10).</div>
                        @error('rating')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-bp btn-lg">
                            <i class="bi bi-send me-2"></i> Kirim Pendaftaran
                        </button>
                        <a href="{{ route('guest.landing') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
