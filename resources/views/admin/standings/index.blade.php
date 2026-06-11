@extends('layouts.admin')

@section('title', 'Klasemen Grup')
@section('page-title', 'Klasemen Grup')

@section('breadcrumb')
    <li class="breadcrumb-item active">Klasemen</li>
@endsection

@section('content')
@include('admin.partials.turnamen-filter', ['filterRoute' => route('admin.standings.index')])

<x-group-leaderboard :standings="$standings" :turnamen="$turnamen" :refreshable="true" />
@endsection

@push('scripts')
<script src="{{ asset('js/leaderboard.js') }}"></script>
@endpush
