@if (! empty($turnamen) && filled($turnamen->syarat))
    <div class="card guest-card guest-tournament-syarat text-start">
        <div class="card-body py-3 px-3 px-sm-4">
            <div class="guest-tournament-syarat__label">
                <i class="bi bi-card-text me-1" aria-hidden="true"></i>Syarat &amp; Ketentuan
            </div>
            <div class="guest-tournament-syarat__text">{{ $turnamen->syarat }}</div>
        </div>
    </div>
@endif
