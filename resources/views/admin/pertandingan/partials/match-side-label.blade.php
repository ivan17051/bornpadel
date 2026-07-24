@php
    if ($match->isFriendlyMatch()) {
        $ids = $side === 1
            ? array_values(array_filter([(int) $match->id_pemain1, (int) $match->id_pemain1_partner]))
            : array_values(array_filter([(int) $match->id_pemain2, (int) $match->id_pemain2_partner]));
        $label = $side === 1 ? $match->side1_label : $match->side2_label;
    } else {
        $peserta = $side === 1 ? $match->peserta1 : $match->peserta2;
        $pemain = $side === 1 ? $match->pemain1 : $match->pemain2;
        $ids = $peserta ? $peserta->pemainIds() : ($pemain ? [$pemain->id] : []);
        $label = $side === 1 ? $match->side1_label : $match->side2_label;
    }
@endphp

<x-pemain-names :pemain-ids="$ids" :nama="$label" />
