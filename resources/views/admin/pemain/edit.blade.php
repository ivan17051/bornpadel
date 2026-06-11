@extends('layouts.admin')

@section('title', 'Edit Pemain')
@section('page-title', 'Edit Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index') }}">Pemain</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <x-pemain-avatar :pemain="$pemain" :size="56" />
                    <h5 class="card-title mb-0">{{ $pemain->nama }}</h5>
                </div>
                @if ($pemain->usia)
                    <span class="badge text-bg-secondary">{{ $pemain->usia }} tahun</span>
                @endif
            </div>
            <div class="card-body">
                @if ($pemain->turnamenPeserta->isNotEmpty())
                    <div class="mb-4">
                        <h6 class="text-muted text-uppercase small mb-2">Pendaftaran Turnamen</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Turnamen</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pemain->turnamenPeserta as $peserta)
                                        <tr>
                                            <td>{{ optional($peserta->turnamen)->nama ?? '—' }}</td>
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
                    <hr class="my-4">
                @endif

                <form action="{{ route('admin.pemain.update', $pemain) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    @if (request('id_turnamen'))
                        <input type="hidden" name="id_turnamen" value="{{ request('id_turnamen') }}">
                    @endif
                    @include('admin.pemain._form', ['pemain' => $pemain])
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
