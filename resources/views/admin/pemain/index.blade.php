@extends('layouts.admin')

@section('title', 'Manajemen Pemain')
@section('page-title', 'Manajemen Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item active">Pemain</li>
@endsection

@section('sweetalert-flash', true)

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => route('admin.pemain.index'),
    'sweetAlert' => true,
    'requireTurnamenSelection' => true,
])

@include('admin.pemain.partials.registered-panel', [
    'filterRoute' => route('admin.pemain.index'),
])
@endsection

@push('styles')
<style>
    .pemain-table-card,
    .pemain-table-card > .card-body {
        overflow: visible;
    }

    #pemain-table-wrapper .dropdown-menu {
        z-index: 1080;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initPemainActions();

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
