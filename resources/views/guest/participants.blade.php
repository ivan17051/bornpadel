@extends('layouts.guest')

@section('title', 'Daftar Peserta')

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $turnamen,
        'ogUrl' => route('guest.participants', ['id_turnamen' => $turnamen->id]),
        'ogTitle' => 'Peserta — ' . $turnamen->nama,
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">Daftar Peserta</h1>
            <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
            <span class="badge text-bg-light text-dark border mt-2">{{ $turnamen->jenis_label }}</span>
        </div>

        <div class="card guest-card mb-4">
            <div class="card-body py-3">
                <div class="row text-center g-3">
                    <div class="col-md-4">
                        <div class="info-label">Terdaftar</div>
                        <strong>{{ $participants->count() }} {{ $participantType === 'pairs' ? 'pasangan' : 'peserta' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Kapasitas Disetujui</div>
                        <strong>{{ $turnamen->maks_peserta ? number_format($turnamen->maks_peserta) : 'Tidak Terbatas' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Status Turnamen</div>
                        <strong>{{ ucfirst($turnamen->status) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card guest-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-people me-2"></i>
                    @if ($participantType === 'pairs')
                        Pasangan Terdaftar
                    @elseif ($participantType === 'double_individual')
                        Peserta Terdaftar
                    @else
                        Pemain Terdaftar
                    @endif
                </span>
                <span class="badge text-bg-secondary">{{ $participants->count() }}</span>
            </div>
            <div class="card-body p-0">
                @if ($participants->isEmpty())
                    <div class="text-center text-muted py-5 px-3">
                        <i class="bi bi-person-x display-6 d-block mb-2"></i>
                        Belum ada peserta terdaftar pada turnamen ini.
                    </div>
                @elseif ($participantType === 'pairs')
                    <div class="list-group list-group-flush">
                        @foreach ($participants as $index => $item)
                            <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <span class="text-muted small me-2">#{{ $index + 1 }}</span>
                                    <strong>{{ $item['label'] }}</strong>
                                </div>
                                <span class="badge status-badge-{{ $item['status'] }}">{{ ucfirst($item['status']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:3rem">#</th>
                                    <th>Nama</th>
                                    @if ($participantType === 'double_individual')
                                        <th>Pasangan</th>
                                    @endif
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($participants as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td class="fw-semibold">{{ $item['nama'] }}</td>
                                        @if ($participantType === 'double_individual')
                                            <td>
                                                @if ($item['is_paired'])
                                                    <span class="text-success"><i class="bi bi-link-45deg me-1"></i>{{ $item['partner'] }}</span>
                                                @else
                                                    <span class="text-muted small">Belum berpasangan</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td>
                                            <span class="badge status-badge-{{ $item['status'] }}">{{ ucfirst($item['status']) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="text-center mt-4 d-flex flex-wrap justify-content-center gap-2">
            @if ($turnamen->isRegistrationOpen())
                <a href="{{ route('guest.register', ['id_turnamen' => $turnamen->id]) }}" class="btn btn-bp">
                    <i class="bi bi-person-plus me-1"></i> Daftar Turnamen
                </a>
            @endif
            <a href="{{ route('guest.landing') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>
@endsection
