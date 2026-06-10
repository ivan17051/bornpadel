@extends('layouts.guest')

@section('title', 'Pendaftaran Berhasil')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        <div class="card guest-card text-center">
            <div class="card-body p-4 p-md-5">
                <div class="success-icon mb-4">
                    <i class="bi bi-check-lg"></i>
                </div>

                <h1 class="h3 fw-bold text-success mb-2">Pendaftaran Berhasil!</h1>
                <p class="text-muted mb-4">
                    Terima kasih telah mendaftar
                    @if ($turnamen)
                        pada <strong>{{ $turnamen->nama }}</strong>.
                    @endif
                    Data Anda sedang menunggu verifikasi admin.
                </p>

                <div class="card bg-light border-0 text-start mb-4">
                    <div class="card-body py-3">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="info-label">Nama</div>
                                <strong>{{ $pemain['nama'] }}</strong>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label">No. HP</div>
                                <strong>{{ $pemain['no_hp'] }}</strong>
                            </div>
                            <div class="col-12">
                                <div class="info-label">Status</div>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-hourglass-split me-1"></i> Menunggu Verifikasi
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="small text-muted mb-4">
                    Tim kami akan menghubungi Anda melalui WhatsApp setelah pendaftaran disetujui.
                </p>

                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="{{ route('guest.landing') }}" class="btn btn-bp">
                        <i class="bi bi-house me-1"></i> Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
