@php
    $userModel = isset($user) ? $user : null;
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text"
           name="name"
           id="name"
           class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', optional($userModel)->name) }}"
           required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
    <input type="text"
           name="username"
           id="username"
           class="form-control @error('username') is-invalid @enderror"
           value="{{ old('username', optional($userModel)->username) }}"
           required>
    @error('username')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email"
           name="email"
           id="email"
           class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', optional($userModel)->email) }}">
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="password" class="form-label">
        Password
        @if (! $userModel)
            <span class="text-danger">*</span>
        @endif
    </label>
    <input type="password"
           name="password"
           id="password"
           class="form-control @error('password') is-invalid @enderror"
           {{ $userModel ? '' : 'required' }}>
    @if ($userModel)
        <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
    @endif
    @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="password_confirmation" class="form-label">
        Konfirmasi Password
        @if (! $userModel)
            <span class="text-danger">*</span>
        @endif
    </label>
    <input type="password"
           name="password_confirmation"
           id="password_confirmation"
           class="form-control"
           {{ $userModel ? '' : 'required' }}>
</div>

<div class="mb-3">
    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
    <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" required>
        @foreach (['admin' => 'Admin (akses semua turnamen)', 'panitia' => 'Panitia (akses satu turnamen)'] as $value => $label)
            <option value="{{ $value }}" {{ old('role', optional($userModel)->role ?? 'panitia') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('role')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3" id="turnamen-field">
    <label for="id_turnamen" class="form-label">Turnamen <span class="text-danger turnamen-required">*</span></label>
    <select name="id_turnamen" id="id_turnamen" class="form-select @error('id_turnamen') is-invalid @enderror">
        <option value="">— Pilih turnamen —</option>
        @foreach ($turnamenList as $turnamen)
            <option value="{{ $turnamen->id }}"
                {{ (string) old('id_turnamen', optional($userModel)->id_turnamen) === (string) $turnamen->id ? 'selected' : '' }}>
                {{ $turnamen->nama }}
                @if ($turnamen->status !== 'draft')
                    ({{ ucfirst($turnamen->status) }})
                @endif
            </option>
        @endforeach
    </select>
    <div class="form-text">Panitia hanya dapat mengakses turnamen yang dipilih.</div>
    @error('id_turnamen')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
    (function () {
        const roleSelect = document.getElementById('role');
        const turnamenField = document.getElementById('turnamen-field');
        const turnamenSelect = document.getElementById('id_turnamen');

        function toggleTurnamenField() {
            const isPanitia = roleSelect.value === 'panitia';
            turnamenField.style.display = isPanitia ? '' : 'none';
            turnamenSelect.required = isPanitia;
            if (!isPanitia) {
                turnamenSelect.value = '';
            }
        }

        roleSelect.addEventListener('change', toggleTurnamenField);
        toggleTurnamenField();
    })();
</script>
@endpush
