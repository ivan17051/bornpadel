<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Born Padel Admin</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/css/adminlte.min.css" crossorigin="anonymous">

    <style>
        :root { --lte-sidebar-width: 260px; }
        .status-badge-pending { background: #ffc107; color: #000; }
        .status-badge-approved { background: #198754; }
        .status-badge-rejected { background: #dc3545; }
        .toast-container { z-index: 1090; }
    </style>
    @stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body border-bottom">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-block">
                        <span class="nav-link fw-semibold">Born Padel Admin</span>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('guest.landing') }}" target="_blank">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Situs Publik
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form action="{{ route('admin.logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <aside class="app-sidebar bg-dark shadow" data-bs-theme="dark">
            <div class="sidebar-brand">
                <a href="{{ route('admin.dashboard') }}" class="brand-link text-decoration-none px-3 py-3 d-block">
                    <i class="bi bi-dribbble me-2"></i>
                    <span class="brand-text fw-semibold">Born Padel</span>
                </a>
            </div>
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav sidebar-menu flex-column" role="menu">
                        <li class="nav-item">
                            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-speedometer2"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.pemain.index') }}" class="nav-link {{ request()->routeIs('admin.pemain.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-people"></i>
                                <p>Manajemen Pemain</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.matchmaking.index') }}" class="nav-link {{ request()->routeIs('admin.matchmaking.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-shuffle"></i>
                                <p>Matchmaking Grup</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.pertandingan.index') }}" class="nav-link {{ request()->routeIs('admin.pertandingan.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-trophy"></i>
                                <p>Pertandingan & Skor</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.standings.index') }}" class="nav-link {{ request()->routeIs('admin.standings.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-bar-chart-steps"></i>
                                <p>Klasemen Grup</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.bracket.index') }}" class="nav-link {{ request()->routeIs('admin.bracket.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-diagram-2"></i>
                                <p>Bracket Knockout</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">@yield('page-title', 'Dashboard')</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end mb-0">
                                @yield('breadcrumb')
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>
        </main>

        <footer class="app-footer">
            <div class="float-end d-none d-sm-inline">Born Padel Tournament</div>
            <strong>&copy; {{ date('Y') }} Born Padel Club.</strong>
        </footer>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/js/adminlte.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
