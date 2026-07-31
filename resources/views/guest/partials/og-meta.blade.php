@php
    $ogTurnamen = $ogTurnamen ?? ($turnamen ?? null);
    $photoService = app(\App\Services\TurnamenPhotoService::class);
    $ogTitle = $ogTitle
        ?? (optional($ogTurnamen)->nama ? optional($ogTurnamen)->nama . ' — Born Padel' : 'Born Padel — Turnamen');
    $ogDescription = $ogDescription ?? (
        $ogTurnamen
            ? trim(sprintf(
                '%s%s%s',
                $ogTurnamen->jenis_label,
                $ogTurnamen->tanggal ? ' · ' . $ogTurnamen->tanggal->format('d M Y') : '',
                $ogTurnamen->status === 'open' ? ' · Pendaftaran dibuka' : ''
            ))
            : 'Turnamen padel Born Padel Club.'
    );
    $ogImage = $ogImage ?? (
        $ogTurnamen
            ? $ogTurnamen->share_image_url
            : $photoService->shareUrl(null)
    );
    $ogUrl = $ogUrl ?? url()->current();
@endphp

<meta name="description" content="{{ $ogDescription }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Born Padel">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:alt" content="{{ optional($ogTurnamen)->nama ?? 'Born Padel' }}">
<meta property="og:url" content="{{ $ogUrl }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">
