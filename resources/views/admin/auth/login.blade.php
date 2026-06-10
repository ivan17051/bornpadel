<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin — Born Padel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/css/adminlte.min.css" crossorigin="anonymous">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; background: #f4f6f9; }
        .login-card { border: none; border-radius: 1rem; box-shadow: 0 8px 32px rgba(0,0,0,.08); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="text-center mb-4">
                    <h1 class="h3 fw-bold"><i class="bi bi-dribbble text-success"></i> Born Padel</h1>
                    <p class="text-muted">Admin Dashboard</p>
                </div>
                <div class="card login-card">
                    <div class="card-body p-4">
                        <form action="{{ route('admin.login.submit') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" id="username"
                                       class="form-control @error('username') is-invalid @enderror"
                                       value="{{ old('username') }}" required autofocus autocomplete="username">
                                @error('username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password"
                                       class="form-control @error('password') is-invalid @enderror" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="remember" id="remember" class="form-check-input">
                                <label for="remember" class="form-check-label">Ingat saya</label>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Login
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
