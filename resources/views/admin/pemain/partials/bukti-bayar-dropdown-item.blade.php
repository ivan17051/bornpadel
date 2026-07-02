@if (($peserta ?? null) && ($turnamen ?? null))
    <li>
        <button type="button"
                class="dropdown-item btn-view-bukti-bayar"
                data-url="{{ $peserta->bukti_bayar_url ?? '' }}"
                data-label="{{ $label ?? $peserta->display_name }}">
            <i class="bi bi-receipt me-2"></i> Lihat Bukti Bayar
        </button>
    </li>
@endif
