@extends('layouts.admin')

@section('title', 'Bracket Knockout')
@section('page-title', 'Bracket Knockout')

@section('breadcrumb')
    <li class="breadcrumb-item active">Bracket</li>
@endsection

@section('content')
@include('admin.partials.turnamen-filter', ['filterRoute' => route('admin.bracket.index')])

<x-tournament-bracket :bracket="$bracket" :turnamen="$turnamen" :refreshable="true" />
@endsection

@push('scripts')
<script src="{{ asset('public/js/bracket.js') }}"></script>
@endpush
