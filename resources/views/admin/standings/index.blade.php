@extends('layouts.admin')

@section('title', 'Klasemen Grup')
@section('page-title', 'Klasemen Grup')

@section('breadcrumb')
    <li class="breadcrumb-item active">Klasemen</li>
@endsection

@section('content')
<x-group-leaderboard :standings="$standings" :turnamen="$turnamen" :refreshable="true" />
@endsection

@push('scripts')
<script src="{{ asset('js/leaderboard.js') }}"></script>
@endpush
