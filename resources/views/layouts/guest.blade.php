<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Born Padel') — Turnamen Padel</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/css/adminlte.min.css" crossorigin="anonymous">

    <style>
        :root {
            --bp-primary: #0d6e4f;
            --bp-primary-dark: #094d37;
            --bp-accent: #f4c430;
        }

        body {
            font-family: "Source Sans 3", system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            background: linear-gradient(160deg, #f0f7f4 0%, #e8f5ee 40%, #ffffff 100%);
        }

        .guest-navbar {
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 1px 12px rgba(0, 0, 0, 0.04);
        }

        .guest-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--bp-primary);
            text-decoration: none;
        }

        .guest-brand span {
            color: #1a1a1a;
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

        .btn-bp {
            background: var(--bp-primary);
            border-color: var(--bp-primary);
            color: #fff;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            border-radius: 0.5rem;
        }

        .btn-bp:hover,
        .btn-bp:focus {
            background: var(--bp-primary-dark);
            border-color: var(--bp-primary-dark);
            color: #fff;
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
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 79, 0.15);
        }

        .success-icon {
            width: 5rem;
            height: 5rem;
            background: #d1fae5;
            color: var(--bp-primary);
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
    <nav class="guest-navbar navbar navbar-expand-lg sticky-top">
        <div class="container py-2">
            <a href="{{ route('guest.landing') }}" class="guest-brand">
                <i class="bi bi-dribbble me-1"></i> Born <span>Padel</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                @if (! in_array(Route::currentRouteName(), ['guest.register', 'guest.register.success']))
                    <a href="{{ route('guest.register') }}" class="btn btn-bp btn-sm d-none d-sm-inline-flex">
                        <i class="bi bi-pencil-square me-1"></i> Daftar
                    </a>
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
    @stack('scripts')
</body>
</html>
