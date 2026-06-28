<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Born Padel') — Turnamen</title>
    <link rel="icon" type="image/png" href="{{ asset('public/img/bornpadel.png') }}">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/css/adminlte.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('public/css/page-loader.css') }}">

    <style>
        :root {
            --bp-primary: #cda858;
            --bp-primary-dark: #a88642;
            --bp-accent: #e8d49a;
            --bs-primary: #cda858;
            --bs-primary-rgb: 205, 168, 88;
            --bs-link-color: #a88642;
            --bs-link-hover-color: #8a7035;
        }

        body {
            font-family: "Source Sans 3", system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            background: linear-gradient(160deg, #faf6ed 0%, #f5eed8 40%, #ffffff 100%);
        }

        .guest-navbar {
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 1px 12px rgba(0, 0, 0, 0.04);
        }

        .guest-brand {
            text-decoration: none;
        }

        .bp-logo {
            height: 2.25rem;
            width: auto;
            display: block;
        }

        .guest-hero {
            background: linear-gradient(135deg, var(--bp-primary) 0%, var(--bp-primary-dark) 100%);
            color: #fff;
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .guest-hero::after {
            content: "";
            position: absolute;
            right: -2rem;
            top: -2rem;
            width: 12rem;
            height: 12rem;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }

        .guest-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }

        .guest-card .card-header {
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 1rem 1rem 0 0 !important;
            font-weight: 600;
        }

        a.pemain-profile-link {
            color: inherit;
            text-decoration: none;
            font-weight: 600;
        }

        a.pemain-profile-link:hover {
            color: var(--bp-primary-dark);
            text-decoration: underline;
        }

        .btn-bp {
            background: var(--bp-primary);
            border-color: var(--bp-primary);
            color: #1a1a1a;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            border-radius: 0.5rem;
        }

        .btn-bp:hover,
        .btn-bp:focus {
            background: var(--bp-primary-dark);
            border-color: var(--bp-primary-dark);
            color: #1a1a1a;
        }

        .btn-primary {
            --bs-btn-color: #1a1a1a;
            --bs-btn-bg: var(--bp-primary);
            --bs-btn-border-color: var(--bp-primary);
            --bs-btn-hover-color: #1a1a1a;
            --bs-btn-hover-bg: var(--bp-primary-dark);
            --bs-btn-hover-border-color: var(--bp-primary-dark);
            --bs-btn-active-color: #1a1a1a;
            --bs-btn-active-bg: var(--bp-primary-dark);
            --bs-btn-active-border-color: var(--bp-primary-dark);
            --bs-btn-disabled-color: #1a1a1a;
            --bs-btn-disabled-bg: var(--bp-primary);
            --bs-btn-disabled-border-color: var(--bp-primary);
        }

        .badge-open {
            background: var(--bp-accent);
            color: #1a1a1a;
            font-weight: 600;
        }

        .info-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            padding: 0.65rem 0.85rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--bp-primary);
            box-shadow: 0 0 0 0.2rem rgba(205, 168, 88, 0.25);
        }

        .success-icon {
            width: 5rem;
            height: 5rem;
            background: #f5ecd4;
            color: var(--bp-primary-dark);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }

        .guest-footer {
            color: #6c757d;
            font-size: 0.875rem;
        }

        @media (max-width: 576px) {
            .guest-hero {
                padding: 1.75rem 1.25rem;
            }
        }
    </style>

    @stack('styles')
</head>
<body class="layout-fixed">
    @include('components.page-loader')

    <nav class="guest-navbar navbar navbar-expand-lg sticky-top">
        <div class="container py-2">
            <a href="{{ route('guest.landing') }}" class="guest-brand">
                <img src="{{ asset('public/img/bornpadel.png') }}" alt="Born Padel" class="bp-logo">
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ auth()->check() ? route('admin.dashboard') : route('admin.login') }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-shield-lock me-1"></i> Admin
                </a>
                @if (! in_array(Route::currentRouteName(), ['guest.register', 'guest.register.form', 'guest.register.success']))
                    <!-- <a href="{{ route('guest.register') }}" class="btn btn-bp btn-sm d-none d-sm-inline-flex">
                        <i class="bi bi-pencil-square me-1"></i> Daftar
                    </a> -->
                @endif
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show guest-card mb-4" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="guest-footer text-center pb-4">
        <div class="container">
            &copy; {{ date('Y') }} Born Padel Club. All rights reserved.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/js/adminlte.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('public/js/page-loader.js') }}"></script>
    @stack('scripts')
</body>
</html>
