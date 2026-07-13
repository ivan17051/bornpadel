@once
@push('styles')
<style>
    .bracket-podium {
        padding: 0.25rem 0;
    }

    .bracket-podium-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        align-items: end;
    }

    .bracket-podium-card {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.85rem;
        padding: 0.85rem 0.65rem;
        text-align: center;
        min-height: 100%;
    }

    .bracket-podium-card--first {
        order: 2;
        border-color: rgba(255, 193, 7, 0.45);
        box-shadow: 0 6px 18px rgba(255, 193, 7, 0.15);
        transform: translateY(-0.35rem);
    }

    .bracket-podium-card--second { order: 1; }
    .bracket-podium-card--third { order: 3; }

    .bracket-podium-rank {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.65rem;
        color: #6c757d;
    }

    .bracket-podium-card--first .bracket-podium-rank { color: #a07800; }
    .bracket-podium-card--first .bracket-podium-rank .bi { color: #ffc107; font-size: 1.1rem; }
    .bracket-podium-card--second .bracket-podium-rank .bi { color: #868e96; }
    .bracket-podium-card--third .bracket-podium-rank { color: #8a5a2b; }
    .bracket-podium-card--third .bracket-podium-rank .bi { color: #cd7f32; }

    .bracket-podium-photos {
        display: flex;
        justify-content: center;
        gap: 0.35rem;
        flex-wrap: wrap;
        margin-bottom: 0.65rem;
    }

    .bracket-podium-photo {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(0, 0, 0, 0.08);
        background: #f8f9fa;
    }

    .bracket-podium-card--first .bracket-podium-photo {
        width: 64px;
        height: 64px;
        border-color: rgba(255, 193, 7, 0.55);
    }

    .bracket-podium-photo--empty {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
        font-size: 1.35rem;
    }

    .bracket-podium-names {
        font-size: 0.92rem;
        font-weight: 600;
        line-height: 1.35;
        word-break: break-word;
    }

    .bracket-podium-card--first .bracket-podium-names {
        font-size: 1rem;
    }

    .bracket-podium-names .pemain-profile-link {
        color: inherit;
        font-weight: inherit;
    }

    @media (max-width: 767.98px) {
        .bracket-podium-grid {
            grid-template-columns: 1fr;
        }

        .bracket-podium-card--first,
        .bracket-podium-card--second,
        .bracket-podium-card--third {
            order: 0;
            transform: none;
        }
    }
</style>
@endpush
@endonce
