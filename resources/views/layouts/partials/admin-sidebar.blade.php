<aside class="app-sidebar bg-dark shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="brand-link text-decoration-none px-3 py-3 d-block">
            <img src="{{ asset('public/img/bornpadel.png') }}" alt="Born Padel" class="bp-logo">
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
                
                <div class="nav-header">DATA MASTER</div>
                @if (auth()->user()->isAdmin())
                <li class="nav-item">
                    <a href="{{ route('admin.turnamen.index') }}" class="nav-link {{ request()->routeIs('admin.turnamen.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-calendar-event"></i>
                        <p>Turnamen</p>
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a href="{{ route('admin.pemain.directory') }}" class="nav-link {{ request()->routeIs('admin.pemain.directory') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-database"></i>
                        <p>Semua Pemain</p>
                    </a>
                </li>
                @if (auth()->user()->isAdmin())
                <li class="nav-item">
                    <a href="{{ route('admin.pengguna.index') }}" class="nav-link {{ request()->routeIs('admin.pengguna.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-person-gear"></i>
                        <p>Pengguna</p>
                    </a>
                </li>
                @endif

                <div class="nav-header">TURNAMEN</div>
                <li class="nav-item">
                    <a href="{{ route('admin.pemain.index') }}" class="nav-link {{ request()->routeIs('admin.pemain.index') || request()->routeIs('admin.pemain.create') || request()->routeIs('admin.pemain.edit') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-people"></i>
                        <p>Pemain Turnamen</p>
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
                
            </ul>
        </nav>
    </div>
</aside>
