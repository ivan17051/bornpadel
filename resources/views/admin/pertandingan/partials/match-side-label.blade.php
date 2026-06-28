@php
    $peserta = $side === 1 ? $match->peserta1 : $match->peserta2;
    $pemain = $side === 1 ? $match->pemain1 : $match->pemain2;
    $ids = $peserta ? $peserta->pemainIds() : ($pemain ? [$pemain->id] : []);
    $label = $side === 1 ? $match->side1_label : $match->side2_label;
@endphp

<x-pemain-names :pemain-ids="$ids" :nama="$label" />
