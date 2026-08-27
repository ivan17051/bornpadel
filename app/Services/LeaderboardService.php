<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;

class LeaderboardService
{
    protected $mahjongRanker;

    public function __construct(MahjongStandingRanker $mahjongRanker)
    {
        $this->mahjongRanker = $mahjongRanker;
    }

    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::whereIn('status', ['open', 'ongoing', 'completed'])
            ->latest('doc')
            ->first();
    }

    public function getStandings(?int $turnamenId = null, $idKategori = null): Collection
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen) {
            return collect();
        }

        if ($turnamen->isFriendly()) {
            return $this->getFriendlyStandings($turnamen->id, $idKategori);
        }

        $grupQuery = $turnamen->isMahjong()
            ? $turnamen->competitionActiveGrup($idKategori)
            : $turnamen->competitionGrup($idKategori);

        return $grupQuery
            ->with(['members' => function ($query) use ($turnamen) {
                $query->with(array_merge(
                    ['pemain', 'turnamenPeserta.pemain1'],
                    TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')
                ));

                if ($turnamen->isMahjong()) {
                    $query->orderByDesc('poin_akumulasi')
                        ->orderByDesc('poin_didapat');
                } else {
                    $query->orderedForPadelStandings();
                }
            }])
            ->withCount([
                'pertandingan as matches_total',
                'pertandingan as matches_completed' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->orderBy('nama')
            ->get()
            ->map(function (Grup $grup) use ($turnamen) {
                $matchesTotal = (int) ($grup->matches_total ?? 0);
                $matchesCompleted = (int) ($grup->matches_completed ?? 0);

                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'babak' => $grup->babak,
                    'is_double' => $turnamen->playsAsPairs(),
                    'is_mahjong' => $turnamen->isMahjong(),
                    'matches_complete' => $matchesTotal > 0 && $matchesTotal === $matchesCompleted,
                    'standings' => $grup->members->values()->map(function ($member, $index) use ($turnamen, $grup) {
                        $row = [
                            'rank' => $index + 1,
                            'id_grup' => $grup->id,
                            'id_pemain' => $member->id_pemain,
                            'id_peserta' => $member->id_turnamen_peserta,
                            'pemain_ids' => $this->resolveStandingPemainIds($member),
                            'nama' => $member->display_name,
                            'poin_didapat' => $member->poin_didapat,
                            'set_menang' => $member->set_menang,
                            'games_menang' => $member->games_menang,
                            'games_diff_label' => GrupMember::formatGameDifference($member->games_menang),
                            'stats_reached_at' => optional($member->stats_reached_at)->toIso8601String(),
                        ];

                        if ($turnamen->isMahjong()) {
                            $row['poin_akumulasi'] = (int) $member->poin_akumulasi;
                            $row['total_poin'] = $member->total_poin;
                        }

                        return $row;
                    }),
                ];
            });
    }

    /**
     * Cross-group ranking banded by in-group place (1st places, then 2nds, …).
     * Qualifiers are highlighted only after the knockout bracket exists.
     *
     * @return array{sections: Collection, has_bracket: bool, is_double: bool}
     */
    public function getPostLeagueRanking(?int $turnamenId = null, $idKategori = null): array
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        $empty = [
            'sections' => collect(),
            'has_bracket' => false,
            'is_double' => false,
        ];

        if (! $turnamen || ! $turnamen->usesKnockoutBracket()) {
            return $empty;
        }

        $standings = $this->getStandings($turnamen->id, $idKategori);

        if ($standings->isEmpty()) {
            return [
                'sections' => collect(),
                'has_bracket' => false,
                'is_double' => $turnamen->playsAsPairs(),
            ];
        }

        $knockout = app(KnockoutBracketService::class);
        $hasBracket = $knockout->hasKnockoutBracket($turnamen, $idKategori);
        $qualifierPeserta = [];
        $qualifierPemain = [];

        if ($hasBracket) {
            $keys = $knockout->getKnockoutParticipantKeys($turnamen, $idKategori);
            $qualifierPeserta = array_fill_keys($keys['peserta_ids'], true);
            $qualifierPemain = array_fill_keys($keys['pemain_ids'], true);
        }

        $byPlace = [];

        foreach ($standings as $grup) {
            foreach ($grup['standings'] as $row) {
                $place = (int) ($row['rank'] ?? 0);
                if ($place < 1) {
                    continue;
                }

                $byPlace[$place][] = [
                    'place' => $place,
                    'id_grup' => $row['id_grup'] ?? $grup['id'],
                    'grup' => $grup['nama'],
                    'id_pemain' => $row['id_pemain'] ?? null,
                    'id_peserta' => $row['id_peserta'] ?? null,
                    'pemain_ids' => $row['pemain_ids'] ?? [],
                    'nama' => $row['nama'],
                    'poin_didapat' => (int) ($row['poin_didapat'] ?? 0),
                    'set_menang' => (int) ($row['set_menang'] ?? 0),
                    'games_menang' => (int) ($row['games_menang'] ?? 0),
                    'games_diff_label' => $row['games_diff_label']
                        ?? GrupMember::formatGameDifference((int) ($row['games_menang'] ?? 0)),
                    'stats_reached_at' => $row['stats_reached_at'] ?? null,
                    'group_rank' => $place,
                ];
            }
        }

        ksort($byPlace, SORT_NUMERIC);

        $overallRank = 0;
        $sections = collect();

        foreach ($byPlace as $place => $rows) {
            $sorted = collect($rows)
                ->sort(fn (array $a, array $b) => GrupMember::comparePadelStandingRows($a, $b))
                ->values()
                ->map(function (array $row) use (&$overallRank, $hasBracket, $qualifierPeserta, $qualifierPemain) {
                    $overallRank++;
                    $pesertaId = (int) ($row['id_peserta'] ?? 0);
                    $pemainId = (int) ($row['id_pemain'] ?? 0);
                    $advances = $hasBracket && (
                        ($pesertaId > 0 && isset($qualifierPeserta[$pesertaId]))
                        || ($pemainId > 0 && isset($qualifierPemain[$pemainId]))
                    );

                    $row['overall_rank'] = $overallRank;
                    $row['advances'] = $advances;

                    return $row;
                });

            $sections->push([
                'place' => (int) $place,
                'label' => 'Juara ' . $place,
                'rows' => $sorted,
            ]);
        }

        return [
            'sections' => $sections,
            'has_bracket' => $hasBracket,
            'is_double' => $turnamen->playsAsPairs(),
        ];
    }

    public function getFriendlyStandings(?int $turnamenId = null, $idKategori = null): Collection
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen || ! $turnamen->isFriendly()) {
            return collect();
        }

        $grups = $turnamen->competitionGrup($idKategori)
            ->with(['members.pemain'])
            ->get();

        $matchStats = $this->buildFriendlyGroupMatchStats($turnamen, $idKategori, $grups);

        return $grups
            ->map(function (Grup $grup) use ($matchStats) {
                $stats = $matchStats[(int) $grup->id] ?? ['main' => 0, 'menang' => 0, 'kalah' => 0];

                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'main' => (int) $stats['main'],
                    'menang' => (int) $stats['menang'],
                    'kalah' => (int) $stats['kalah'],
                    'poin_didapat' => (int) $grup->poin_didapat,
                    'set_menang' => (int) $grup->set_menang,
                    'games_menang' => (int) $grup->games_menang,
                    'games_diff_label' => GrupMember::formatGameDifference($grup->games_menang),
                    'stats_reached_at' => optional($grup->stats_reached_at)->toIso8601String(),
                    'members' => $grup->members->map(function (GrupMember $member) {
                        return [
                            'id_pemain' => $member->id_pemain,
                            'nama' => optional($member->pemain)->nama,
                        ];
                    })->values(),
                ];
            })
            ->sort(fn (array $a, array $b) => Grup::compareLeagueRows($a, $b))
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    /**
     * Win / loss counts per competition group from completed Friendly matches.
     *
     * @param  iterable  $grups
     * @return array<int, array{main:int, menang:int, kalah:int}>
     */
    protected function buildFriendlyGroupMatchStats(Turnamen $turnamen, $idKategori, $grups): array
    {
        $stats = [];
        foreach ($grups as $grup) {
            $stats[(int) $grup->id] = ['main' => 0, 'menang' => 0, 'kalah' => 0];
        }

        $kategori = $turnamen->resolveKategori($idKategori);
        if (! $kategori || $stats === []) {
            return $stats;
        }

        $matches = Pertandingan::query()
            ->where('id_kategori', $kategori->id)
            ->where('nama_ronde', 'Friendly')
            ->where('status', 'completed')
            ->whereNotNull('id_pemenang')
            ->get([
                'id',
                'id_grup1',
                'id_grup2',
                'id_pemain1',
                'id_pemain2',
                'id_pemain1_partner',
                'id_pemain2_partner',
                'id_pemenang',
            ]);

        foreach ($matches as $match) {
            $winnerGrupId = $match->resolveWinnerGrupId();
            $loserGrupId = $match->resolveLoserGrupId();

            if ($winnerGrupId && isset($stats[$winnerGrupId])) {
                $stats[$winnerGrupId]['main']++;
                $stats[$winnerGrupId]['menang']++;
            }

            if ($loserGrupId && isset($stats[$loserGrupId])) {
                $stats[$loserGrupId]['main']++;
                $stats[$loserGrupId]['kalah']++;
            }
        }

        return $stats;
    }

    public function getMahjongGlobalStandings(?int $turnamenId = null): Collection
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen || ! $turnamen->isMahjong()) {
            return collect();
        }

        $members = $this->collectMahjongStandingMembers($turnamen);

        if ($members->isNotEmpty()) {
            return $members
                ->sortByDesc(function (GrupMember $member) {
                    return $member->total_poin;
                })
                ->values()
                ->map(function (GrupMember $member, $index) {
                    return $this->formatMahjongStandingRow($member, $index + 1);
                });
        }

        return TurnamenPeserta::query()
            ->forKategori($turnamen->resolveKategori()->id)
            ->approved()
            ->with('pemain1')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (TurnamenPeserta $peserta, $index) {
                return [
                    'rank' => $index + 1,
                    'id_pemain' => $peserta->id_pemain1,
                    'id_peserta' => $peserta->id,
                    'pemain_ids' => $peserta->pemainIds(),
                    'nama' => $peserta->display_name,
                    'grup_nama' => null,
                    'poin_akumulasi' => 0,
                    'poin_didapat' => 0,
                    'poin_babak' => 0,
                    'total_poin' => 0,
                ];
            });
    }

    public function getMahjongStandingsByBabak(?int $turnamenId = null, $idKategori = null): array
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen || ! $turnamen->isMahjong()) {
            return [
                'sections' => collect(),
                'recap' => collect(),
                'babak_numbers' => collect(),
            ];
        }

        $babakNumbers = Grup::query()
            ->where('id_kategori', $turnamen->resolveKategori($idKategori)->id)
            ->distinct()
            ->orderByDesc('babak')
            ->pluck('babak');

        $sections = $babakNumbers->map(function ($babak) use ($turnamen, $idKategori) {
            $table = $this->buildMahjongBabakTable($turnamen, (int) $babak, $idKategori);
            $groups = $this->resolveMahjongGrupBatchForBabak($turnamen, (int) $babak, $idKategori);

            return [
                'babak' => (int) $babak,
                'is_active' => $groups->contains(function (Grup $grup) {
                    return $grup->is_aktif;
                }),
                'rounds' => $table['rounds'],
                'rows' => $table['rows'],
                'groups' => collect(),
                'recap' => $table['rows'],
            ];
        })->sortByDesc('babak')->values();

        return [
            'sections' => $sections,
            'recap' => $sections->map(function (array $section) {
                return [
                    'babak' => $section['babak'],
                    'is_active' => $section['is_active'],
                    'rounds' => $section['rounds'],
                    'standings' => $section['rows'],
                ];
            })->values(),
            'babak_numbers' => $babakNumbers->values(),
        ];
    }

    /**
     * Point history under matchmaking “Akumulasi”.
     * - Full totals for completed prior babaks
     * - Per-ronde totals for earlier rondes in the current babak (after reshuffle)
     * Excludes the active ronde’s poin babak (shown in Poin Babak column).
     *
     * @return array<int, list<array{label: string, poin: int}>>
     */
    public function getMahjongPriorBabakBreakdown(Turnamen $turnamen, $idKategori = null): array
    {
        if (! $turnamen->isMahjong()) {
            return [];
        }

        $kategori = $turnamen->resolveKategori($idKategori);
        $currentBabak = (int) ($kategori->activeGrup()->max('babak') ?: 0);
        $hasActiveGroups = $currentBabak > 0;

        if (! $hasActiveGroups) {
            $maxBabak = (int) ($kategori->grup()->max('babak') ?: 0);
            if ($maxBabak < 1) {
                return [];
            }
            // Finished / no active groups: treat as past the last babak.
            $currentBabak = $maxBabak + 1;
        }

        $breakdown = [];

        // Completed prior babaks (full babak totals).
        for ($babak = 1; $babak < $currentBabak; $babak++) {
            $table = $this->buildMahjongBabakTable($turnamen, $babak, $kategori->id);

            foreach ($table['rows'] as $row) {
                $pesertaId = (int) ($row['id_peserta'] ?? 0);
                if ($pesertaId <= 0) {
                    continue;
                }

                if (! isset($breakdown[$pesertaId])) {
                    $breakdown[$pesertaId] = [];
                }

                $breakdown[$pesertaId][] = [
                    'label' => 'Babak ' . $babak,
                    'poin' => (int) ($row['total_babak'] ?? $row['poin_babak'] ?? 0),
                ];
            }
        }

        // Earlier rondes within the current active babak (reshuffles stay on same babak).
        if ($hasActiveGroups) {
            $table = $this->buildMahjongBabakTable($turnamen, $currentBabak, $kategori->id);

            foreach ($table['rows'] as $row) {
                $pesertaId = (int) ($row['id_peserta'] ?? 0);
                if ($pesertaId <= 0) {
                    continue;
                }

                $scores = $row['round_scores'] ?? [];
                // Drop the active (latest) ronde — that is Poin Babak, not Akumulasi history.
                if (count($scores) > 0) {
                    array_pop($scores);
                }

                foreach ($scores as $index => $poin) {
                    if (! isset($breakdown[$pesertaId])) {
                        $breakdown[$pesertaId] = [];
                    }

                    $breakdown[$pesertaId][] = [
                        'label' => 'Ronde ' . ($index + 1),
                        'poin' => (int) $poin,
                    ];
                }
            }
        }

        return $breakdown;
    }

    public function buildMahjongBabakTable(Turnamen $turnamen, int $babak, $idKategori = null): array
    {
        $roundBatches = $this->getMahjongRoundBatchesForBabak($turnamen, $babak, $idKategori);

        if ($roundBatches->isEmpty()) {
            return [
                'rounds' => collect(),
                'rows' => collect(),
            ];
        }

        $rounds = $roundBatches->values()->map(function ($batch, int $index) {
            return [
                'round' => $index + 1,
                'label' => 'Ronde ' . ($index + 1),
            ];
        });

        $pesertaIds = $roundBatches
            ->flatMap(function (Collection $batch) {
                return $batch->flatMap(function (Grup $grup) {
                    return $grup->members->pluck('id_turnamen_peserta');
                });
            })
            ->unique()
            ->filter()
            ->values();

        $rows = $pesertaIds->map(function ($pesertaId) use ($roundBatches, $babak, $turnamen) {
            $roundScores = [];
            $latestMember = null;

            foreach ($roundBatches as $roundIndex => $batch) {
                $member = $this->findMahjongMemberInBatch($batch, (int) $pesertaId);

                if ($member) {
                    $latestMember = $member;
                    $roundScores[] = $this->resolveMahjongRoundPoints(
                        $member,
                        $roundBatches,
                        (int) $roundIndex,
                        $babak,
                        $turnamen
                    );
                } else {
                    $roundScores[] = 0;
                }
            }

            if (! $latestMember) {
                return null;
            }

            $totalBabak = array_sum($roundScores);

            return array_merge($this->formatMahjongStandingRow($latestMember, 0), [
                'round_scores' => $roundScores,
                'menang' => $this->resolveMahjongBabakWins($roundBatches, (int) $pesertaId),
                'total_babak' => $totalBabak,
                'poin_babak' => $totalBabak,
                'total_poin' => $this->resolveMahjongTotalPoints(
                    $latestMember,
                    $totalBabak,
                    $babak,
                    $turnamen
                ),
            ]);
        })
            ->filter()
            ->pipe(function (Collection $rows) {
                return $this->mahjongRanker->rankRows($rows);
            });

        return [
            'rounds' => $rounds,
            'rows' => $rows,
        ];
    }

    public function buildMahjongBabakRecap(Collection $groups): Collection
    {
        return $groups
            ->flatMap(function (array $grup) {
                return $grup['standings'];
            })
            ->sortByDesc(function (array $row) {
                return (int) ($row['poin_babak'] ?? $row['poin_didapat'] ?? 0);
            })
            ->values()
            ->map(function (array $row, $index) {
                return [
                    'rank' => $index + 1,
                    'id_pemain' => $row['id_pemain'],
                    'id_peserta' => $row['id_peserta'],
                    'pemain_ids' => $row['pemain_ids'] ?? [],
                    'nama' => $row['nama'],
                    'grup_nama' => $row['grup_nama'] ?? null,
                    'poin_babak' => (int) ($row['poin_babak'] ?? $row['poin_didapat'] ?? 0),
                    'total_poin' => (int) ($row['total_poin'] ?? 0),
                ];
            });
    }

    protected function getMahjongRoundBatchesForBabak(Turnamen $turnamen, int $babak, $idKategori = null): Collection
    {
        $kategoriId = $turnamen->resolveKategori($idKategori)->id;

        $groups = Grup::query()
            ->where('id_kategori', $kategoriId)
            ->where('babak', $babak)
            ->with(array_merge(
                ['members.pemain', 'members.poinEntries', 'members.turnamenPeserta.pemain1'],
                TurnamenPeserta::partnerPemainEagerLoadsFor('members.turnamenPeserta')
            ))
            ->orderBy('ronde')
            ->orderBy('id')
            ->get();

        return $groups
            ->groupBy(function (Grup $grup) {
                return (int) ($grup->ronde ?: 1);
            })
            ->sortKeys()
            ->values();
    }

    protected function findMahjongMemberInBatch(Collection $batch, int $pesertaId): ?GrupMember
    {
        foreach ($batch as $grup) {
            $member = $grup->members->firstWhere('id_turnamen_peserta', $pesertaId);

            if ($member) {
                return $member;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Grup>>  $roundBatches
     */
    protected function resolveMahjongBabakWins(Collection $roundBatches, int $pesertaId): int
    {
        $wins = 0;

        foreach ($roundBatches as $batch) {
            $member = $this->findMahjongMemberInBatch($batch, $pesertaId);

            if ($member) {
                $wins += (int) $member->menang;
            }
        }

        return $wins;
    }

    protected function resolveMahjongRoundPoints(
        GrupMember $member,
        Collection $roundBatches,
        int $roundIndex,
        int $babak,
        Turnamen $turnamen
    ): int {
        if ($member->grup && $member->grup->is_aktif) {
            return (int) $member->poin_didapat;
        }

        if ((int) $member->poin_didapat !== 0) {
            return (int) $member->poin_didapat;
        }

        $startTotal = $this->resolveMahjongRoundStartTotal(
            $member->id_turnamen_peserta,
            $roundBatches,
            $roundIndex,
            $babak,
            $turnamen
        );

        return (int) $member->poin_akumulasi - $startTotal;
    }

    protected function resolveMahjongRoundStartTotal(
        ?int $pesertaId,
        Collection $roundBatches,
        int $roundIndex,
        int $babak,
        Turnamen $turnamen
    ): int {
        // Each babak resets the scoreboard. Ronde 1 always starts from 0.
        if ($roundIndex <= 0) {
            return 0;
        }

        $prevBatch = $roundBatches->get($roundIndex - 1);

        if ($prevBatch && $pesertaId) {
            $prevMember = $this->findMahjongMemberInBatch($prevBatch, $pesertaId);

            if ($prevMember) {
                // After commit, total sits in poin_akumulasi (poin_didapat = 0).
                // Before commit on a historical row, total is akumulasi + didapat.
                return (int) $prevMember->poin_akumulasi + (int) $prevMember->poin_didapat;
            }
        }

        return 0;
    }

    protected function resolveMahjongGrupBatchForBabak(Turnamen $turnamen, int $babak, $idKategori = null): Collection
    {
        $kategoriId = $turnamen->resolveKategori($idKategori)->id;
        $relations = array_merge(
            ['members.pemain', 'members.turnamenPeserta.pemain1'],
            TurnamenPeserta::partnerPemainEagerLoadsFor('members.turnamenPeserta')
        );

        $active = Grup::query()
            ->where('id_kategori', $kategoriId)
            ->where('babak', $babak)
            ->where('is_aktif', true)
            ->with($relations)
            ->orderBy('nama')
            ->get();

        if ($active->isNotEmpty()) {
            return $active;
        }

        $latestCreatedAt = Grup::query()
            ->where('id_kategori', $kategoriId)
            ->where('babak', $babak)
            ->where('is_aktif', false)
            ->max('created_at');

        if (! $latestCreatedAt) {
            return collect();
        }

        return Grup::query()
            ->where('id_kategori', $kategoriId)
            ->where('babak', $babak)
            ->where('is_aktif', false)
            ->where('created_at', $latestCreatedAt)
            ->with($relations)
            ->orderBy('nama')
            ->get();
    }

    protected function resolveMahjongTotalPoints(GrupMember $member, int $babakPoints, int $babak, Turnamen $turnamen): int
    {
        if ($member->grup && $member->grup->is_aktif) {
            return $member->total_poin;
        }

        if ((int) $member->poin_didapat !== 0) {
            return (int) $member->poin_akumulasi + (int) $member->poin_didapat;
        }

        return (int) $member->poin_akumulasi;
    }

    protected function collectMahjongStandingMembers(Turnamen $turnamen): Collection
    {
        $kategoriId = $turnamen->resolveKategori()->id;

        $activeMembers = GrupMember::query()
            ->whereHas('grup', function ($query) use ($kategoriId) {
                $query->where('id_kategori', $kategoriId)->where('is_aktif', true);
            })
            ->with(array_merge(
                ['pemain', 'turnamenPeserta.pemain1', 'grup'],
                TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')
            ))
            ->get();

        if ($activeMembers->isNotEmpty()) {
            return $activeMembers;
        }

        $latestBabak = $turnamen->competitionGrup()->max('babak');

        if (! $latestBabak) {
            return collect();
        }

        return GrupMember::query()
            ->whereHas('grup', function ($query) use ($kategoriId, $latestBabak) {
                $query->where('id_kategori', $kategoriId)->where('babak', $latestBabak);
            })
            ->with(array_merge(
                ['pemain', 'turnamenPeserta.pemain1', 'grup'],
                TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')
            ))
            ->get();
    }

    protected function formatMahjongStandingRow(GrupMember $member, int $rank): array
    {
        return [
            'rank' => $rank,
            'id_pemain' => $member->id_pemain,
            'id_peserta' => $member->id_turnamen_peserta,
            'pemain_ids' => $this->resolveStandingPemainIds($member),
            'nama' => $member->display_name,
            'grup_nama' => optional($member->grup)->nama,
            'poin_akumulasi' => (int) $member->poin_akumulasi,
            'poin_didapat' => (int) $member->poin_didapat,
            'poin_babak' => (int) $member->poin_didapat,
            'total_poin' => $member->total_poin,
            'menang' => (int) $member->menang,
        ];
    }

    protected function resolveStandingPemainIds($member): array
    {
        if ($member->turnamenPeserta) {
            return $member->turnamenPeserta->pemainIds();
        }

        return $member->id_pemain ? [(int) $member->id_pemain] : [];
    }
}
