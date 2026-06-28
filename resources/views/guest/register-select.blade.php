@extends('layouts.guest')

@section('title', 'Pilih Turnamen')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">Pilih Turnamen</h1>
            <p class="text-muted mb-0">Beberapa turnamen sedang dibuka untuk pendaftaran.</p>
        </div>

        <div class="row g-3">
            @foreach ($openTournaments as $item)
                <div class="col-md-6">
                    <div class="card guest-card h-100">
                        <div class="card-body d-flex flex-column">
                            <h2 class="h5 mb-2">{{ $item->nama }}</h2>
                            <div class="small text-muted mb-3">
                                <div>{{ $item->jenis_label }} · Rp {{ number_format($item->harga, 0, ',', '.') }}</div>
                            </div>
                            <a href="{{ route('guest.register', ['id_turnamen' => $item->id]) }}"
                               class="btn btn-bp mt-auto">
                                Daftar Turnamen Ini
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
