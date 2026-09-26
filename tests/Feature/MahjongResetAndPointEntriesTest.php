<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\MahjongPoinEntry;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\GroupMatchmakingService;
use App\Services\LeaderboardService;
use App\Services\MahjongMatchmakingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MahjongResetAndPointEntriesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_reset_only_when_ongoing_with_groups(): void
    {
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $this->assertTrue($service->canReset($turnamen->fresh()));
        $this->assertTrue(app(GroupMatchmakingService::class)->canResetGroupsAndMatches($turnamen->fresh()));

        $turnamen->update(['status' => 'completed']);
        $this->assertFalse($service->canReset($turnamen->fresh()));
        $this->assertFalse(app(GroupMatchmakingService::class)->canResetGroupsAndMatches($turnamen->fresh()));
    }

    public function test_reset_wipes_groups_and_points_while_ongoing(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $member = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id))
            ->first();
        $service->addMemberPointEntry($member, 10);
        $service->addMemberPointEntry($member->fresh(), -3);

        $this->actingAs($admin)
            ->deleteJson(route('admin.matchmaking.reset-groups'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $turnamen->refresh();
        $this->assertSame('ongoing', $turnamen->status);
        $this->assertFalse((bool) $turnamen->mahjong_is_final);
        $this->assertSame(0, Grup::where('id_turnamen', $turnamen->id)->count());
        $this->assertSame(0, MahjongPoinEntry::query()
            ->whereHas('grupMember.grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id);
            })
            ->count());
    }

    public function test_reset_rejected_when_completed(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');
        $turnamen->update(['status' => 'completed']);

        $this->actingAs($admin)
            ->deleteJson(route('admin.matchmaking.reset-groups'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertStatus(422);

        $this->assertSame(2, Grup::where('id_turnamen', $turnamen->id)->count());
    }

    public function test_point_entries_sum_and_delete_updates_total(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $member = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->first();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-point-entries.store', $member), [
                'poin' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.poin_didapat', 10);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-point-entries.store', $member), [
                'poin' => -3,
            ])
            ->assertOk()
            ->assertJsonPath('data.poin_didapat', 7)
            ->assertJsonPath('data.total_poin', 7);

        $member->refresh();
        $this->assertSame(2, $member->poinEntries()->count());
        $entry = $member->poinEntries()->where('poin', 10)->first();

        $this->actingAs($admin)
            ->deleteJson(route('admin.matchmaking.mahjong-point-entries.destroy', [
                'member' => $member->id,
                'entry' => $entry->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.poin_didapat', -3);

        $this->assertSame(1, $member->fresh()->poinEntries()->count());
    }

    public function test_group_point_entries_save_all_four_members(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $this->assertNotNull($grup);
        $this->assertCount(4, $grup->members);

        $scores = $grup->members->values()->map(function (GrupMember $member, int $index) {
            return [
                'id' => $member->id,
                'poin' => [8, -2, -3, -3][$index],
            ];
        })->all();

        $winnerId = (int) $grup->members->first()->id;

        $response = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-group-point-entries.store', $grup), [
                'scores' => $scores,
                'id_grup_member_pemenang' => $winnerId,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $membersPayload = $response->json('data.members');
        $this->assertCount(4, $membersPayload);

        foreach ($scores as $score) {
            $member = GrupMember::findOrFail($score['id']);
            $this->assertSame($score['poin'], (int) $member->poin_didapat);
            $this->assertSame(1, $member->poinEntries()->count());
            $this->assertSame($score['poin'], (int) $member->poinEntries()->first()->poin);
            $this->assertSame((int) $member->id === $winnerId, (bool) $member->poinEntries()->first()->is_winner);
        }

        $winner = GrupMember::findOrFail($winnerId);
        $this->assertSame(1, (int) $winner->menang);
        $response->assertJsonPath('data.members.0.poin_didapat', $scores[0]['poin']);
        $this->assertSame(1, (int) collect($membersPayload)->firstWhere('id', $winnerId)['menang']);
    }

    public function test_group_point_entries_allow_missing_winner(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $scores = $grup->members->values()->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-group-point-entries.store', $grup), [
                'scores' => $scores,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        foreach ($grup->members as $member) {
            $entry = $member->fresh()->poinEntries()->first();
            $this->assertNotNull($entry);
            $this->assertFalse((bool) $entry->is_winner);
            $this->assertSame(0, (int) $member->fresh()->menang);
        }
    }

    public function test_group_point_entries_reject_incomplete_scores(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $partial = $grup->members->take(3)->map(fn (GrupMember $member) => [
            'id' => $member->id,
            'poin' => 1,
        ])->all();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-group-point-entries.store', $grup), [
                'scores' => $partial,
            ])
            ->assertStatus(422);
    }

    public function test_group_point_entries_treat_empty_poin_as_zero(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $members = $grup->members->values();
        $scores = [
            ['id' => $members[0]->id, 'poin' => 8],
            ['id' => $members[1]->id, 'poin' => ''],
            ['id' => $members[2]->id],
            ['id' => $members[3]->id, 'poin' => null],
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-group-point-entries.store', $grup), [
                'scores' => $scores,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(8, (int) $members[0]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[1]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[2]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[3]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[1]->fresh()->poinEntries()->first()->poin);
    }

    public function test_group_point_entries_can_be_updated_for_a_round(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $members = $grup->members->values();
        $originalScores = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();
        $originalWinnerId = (int) $members->first()->id;

        $service->addGroupPointEntries($grup, $originalScores, $originalWinnerId);

        $entriesByMember = $members->mapWithKeys(function (GrupMember $member) {
            return [$member->id => $member->fresh()->poinEntries()->first()->id];
        });

        $newWinnerId = (int) $members->get(1)->id;
        $updatedScores = $members->map(function (GrupMember $member, int $index) use ($entriesByMember) {
            return [
                'id' => $member->id,
                'entry_id' => $entriesByMember[$member->id],
                'poin' => [10, 4, -7, -7][$index],
            ];
        })->all();

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-group-point-entries.update', $grup), [
                'scores' => $updatedScores,
                'id_grup_member_pemenang' => $newWinnerId,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        foreach ($updatedScores as $score) {
            $member = GrupMember::findOrFail($score['id']);
            $entry = $member->poinEntries()->first();
            $this->assertSame(1, $member->poinEntries()->count());
            $this->assertSame($score['entry_id'], (int) $entry->id);
            $this->assertSame($score['poin'], (int) $entry->poin);
            $this->assertSame($score['poin'], (int) $member->poin_didapat);
            $this->assertSame((int) $member->id === $newWinnerId, (bool) $entry->is_winner);
        }

        $this->assertSame(0, (int) GrupMember::findOrFail($originalWinnerId)->menang);
        $this->assertSame(1, (int) GrupMember::findOrFail($newWinnerId)->menang);
    }

    public function test_group_adjustments_change_babak_total_without_touching_ronde_entries(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $members = $grup->members->values();
        $rondeScores = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();
        $service->addGroupPointEntries($grup, $rondeScores, (int) $members->first()->id);

        $adjustments = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [5, 0, -2, 0][$index]];
        })->all();

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-group-point-adjustments.update', $grup), [
                'scores' => $adjustments,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $winner = GrupMember::findOrFail($members->first()->id);
        $this->assertSame(8, (int) $winner->poin_didapat);
        $this->assertSame(5, (int) $winner->poin_penyesuaian);
        $this->assertSame(13, (int) $winner->poin_babak);
        $this->assertSame(13, $winner->total_poin);
        $this->assertSame(1, (int) $winner->menang);
        $this->assertSame(1, $winner->poinEntries()->count());

        $penalized = GrupMember::findOrFail($members->get(2)->id);
        $this->assertSame(-3, (int) $penalized->poin_didapat);
        $this->assertSame(-2, (int) $penalized->poin_penyesuaian);
        $this->assertSame(-5, (int) $penalized->poin_babak);
    }

    public function test_reshuffle_copies_babak_adjustment_and_keeps_ronde_points_in_akumulasi(): void
    {
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $member = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->first();
        $pesertaId = (int) $member->id_turnamen_peserta;
        $grup = $member->grup()->with('members')->first();

        $scores = $grup->members->values()->map(function (GrupMember $groupMember, int $index) use ($member) {
            return ['id' => $groupMember->id, 'poin' => (int) $groupMember->id === (int) $member->id ? 10 : -3];
        })->all();
        $service->addGroupPointEntries($grup, $scores);

        $adjustments = $grup->members->values()->map(function (GrupMember $groupMember) use ($member) {
            return ['id' => $groupMember->id, 'poin' => (int) $groupMember->id === (int) $member->id ? 5 : 0];
        })->all();
        $service->updateGroupAdjustments($grup, $adjustments);

        $service->reshuffleGroups($turnamen->fresh(), 'random');

        $nextMember = GrupMember::query()
            ->where('id_turnamen_peserta', $pesertaId)
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->first();

        $this->assertNotNull($nextMember);
        $this->assertSame(10, (int) $nextMember->poin_akumulasi);
        $this->assertSame(0, (int) $nextMember->poin_didapat);
        $this->assertSame(5, (int) $nextMember->poin_penyesuaian);
        $this->assertSame(15, $nextMember->total_poin);
    }

    public function test_reshuffle_carries_summed_ronde_points_into_akumulasi(): void
    {
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $member = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->first();
        $pesertaId = (int) $member->id_turnamen_peserta;

        $service->addMemberPointEntry($member, 10);
        $service->addMemberPointEntry($member->fresh(), -3);

        $service->reshuffleGroups($turnamen->fresh(), 'random');

        $nextMember = GrupMember::query()
            ->where('id_turnamen_peserta', $pesertaId)
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->first();

        $this->assertNotNull($nextMember);
        $this->assertSame(7, (int) $nextMember->poin_akumulasi);
        $this->assertSame(0, (int) $nextMember->poin_didapat);
        $this->assertSame(7, $nextMember->total_poin);
    }

    public function test_can_swap_group_members_before_babak_has_scores(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $groups = app(GroupMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $this->assertTrue($mahjong->canEditGroups($turnamen->fresh()));
        $this->assertTrue($groups->canEditGroups($turnamen->fresh()));

        $groupsById = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(2, $groupsById->count());

        $first = $groupsById[0]->members->first();
        $second = $groupsById[1]->members->first();
        $firstGroupId = (int) $first->id_grup;
        $secondGroupId = (int) $second->id_grup;

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.swap-group-members'), [
                'id_turnamen' => $turnamen->id,
                'first_member_id' => $first->id,
                'second_member_id' => $second->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($secondGroupId, (int) $first->fresh()->id_grup);
        $this->assertSame($firstGroupId, (int) $second->fresh()->id_grup);
    }

    public function test_cannot_swap_group_members_after_babak_has_scores(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $groups = app(GroupMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $groupsById = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('id')
            ->get();

        $first = $groupsById[0]->members->first();
        $second = $groupsById[1]->members->first();

        $mahjong->addMemberPointEntry($first, 5);

        $this->assertFalse($mahjong->canEditGroups($turnamen->fresh()));
        $this->assertFalse($groups->canEditGroups($turnamen->fresh()));

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.swap-group-members'), [
                'id_turnamen' => $turnamen->id,
                'first_member_id' => $first->id,
                'second_member_id' => $second->id,
            ])
            ->assertStatus(422);

        $this->assertSame((int) $groupsById[0]->id, (int) $first->fresh()->id_grup);
        $this->assertSame((int) $groupsById[1]->id, (int) $second->fresh()->id_grup);
    }

    public function test_can_swap_again_after_reshuffle_clears_babak_scores(): void
    {
        $mahjong = app(MahjongMatchmakingService::class);
        $groups = app(GroupMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $member = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->first();
        $mahjong->addMemberPointEntry($member, 8);
        $this->assertFalse($groups->canEditGroups($turnamen->fresh()));

        $mahjong->reshuffleGroups($turnamen->fresh(), 'random');

        $this->assertTrue($mahjong->canEditGroups($turnamen->fresh()));
        $this->assertTrue($groups->canEditGroups($turnamen->fresh()));

        $activeGroups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('id')
            ->get();

        $first = $activeGroups[0]->members->first();
        $second = $activeGroups[1]->members->first();
        $groups->swapGroupMembers($turnamen->fresh(), $first, $second);

        $this->assertSame((int) $activeGroups[1]->id, (int) $first->fresh()->id_grup);
        $this->assertSame((int) $activeGroups[0]->id, (int) $second->fresh()->id_grup);
    }

    public function test_advance_round_requests_manual_pick_when_cutline_is_tied(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $members = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->orderBy('id')
            ->get();

        $this->assertCount(8, $members);

        // Two clear leaders, then three tied for the remaining two slots.
        $points = [50, 40, 20, 20, 20, 5, 4, 3];
        foreach ($members as $index => $member) {
            $mahjong->updateMemberPoints($member, $points[$index]);
        }

        $response = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('needs_tiebreak', true)
            ->assertJsonPath('data.slots_remaining', 2);

        $contestedIds = collect($response->json('data.contested'))->pluck('id_peserta')->all();
        $this->assertCount(3, $contestedIds);

        $picks = array_slice($contestedIds, 0, 2);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 4,
                'tiebreak_peserta_ids' => $picks,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_final', true);

        $activePesertaIds = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->pluck('id_turnamen_peserta')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $expected = collect([$members[0]->id_turnamen_peserta, $members[1]->id_turnamen_peserta])
            ->merge($picks)
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expected, $activePesertaIds);
        $this->assertSame(1, Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count());
    }

    public function test_advance_preview_lists_qualifiers_with_scores_without_mutating(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $members = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->orderBy('id')
            ->get();

        $points = [50, 40, 30, 20, 10, 5, 4, 3];
        foreach ($members as $index => $member) {
            $mahjong->updateMemberPoints($member, $points[$index]);
        }

        $activeBefore = Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 4,
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('data.jumlah_lolos', 4)
            ->assertJsonPath('data.is_final', true);

        $qualifiers = $response->json('data.qualifiers');
        $this->assertCount(4, $qualifiers);
        $this->assertSame(50, (int) $qualifiers[0]['total_babak']);
        $this->assertSame(40, (int) $qualifiers[1]['total_babak']);
        $this->assertSame(30, (int) $qualifiers[2]['total_babak']);
        $this->assertSame(20, (int) $qualifiers[3]['total_babak']);
        $this->assertSame(1, (int) $qualifiers[0]['rank']);

        $this->assertSame(
            $activeBefore,
            Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count()
        );
    }

    public function test_advance_per_group_preview_and_commit_takes_top_n_each_group(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $groups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $groups);

        // Top 2 per group → 4 finalists. Give each group a clear ranking.
        foreach ($groups as $groupIndex => $grup) {
            $points = $groupIndex === 0 ? [40, 30, 10, 5] : [50, 25, 8, 2];
            foreach ($grup->members->values() as $memberIndex => $member) {
                $mahjong->updateMemberPoints($member, $points[$memberIndex]);
            }
        }

        $expectedIds = $groups->flatMap(function (Grup $grup) {
            return $grup->members->values()->take(2)->pluck('id_turnamen_peserta');
        })->map(fn ($id) => (int) $id)->sort()->values()->all();

        $preview = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
                'qualification_mode' => 'per_group',
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('data.qualification_mode', 'per_group')
            ->assertJsonPath('data.jumlah_lolos', 2)
            ->assertJsonPath('data.is_final', true);

        $previewIds = collect($preview->json('data.qualifiers'))
            ->pluck('id_peserta')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expectedIds, $previewIds);
        $this->assertSame(2, Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count());

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
                'qualification_mode' => 'per_group',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_final', true);

        $activePesertaIds = GrupMember::query()
            ->whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->pluck('id_turnamen_peserta')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expectedIds, $activePesertaIds);
        $this->assertSame(1, Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count());
    }

    public function test_advance_per_group_requests_tiebreak_per_group_sequentially(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $groups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $groups);

        // Each group: 1 clear leader, then 2 tied for the 2nd slot.
        foreach ($groups as $grup) {
            $members = $grup->members->values();
            $mahjong->updateMemberPoints($members[0], 40);
            $mahjong->updateMemberPoints($members[1], 20);
            $mahjong->updateMemberPoints($members[2], 20);
            $mahjong->updateMemberPoints($members[3], 5);
        }

        $first = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
                'qualification_mode' => 'per_group',
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('needs_tiebreak', true)
            ->assertJsonPath('data.slots_remaining', 1)
            ->assertJsonPath('data.tiebreak_grup_id', (int) $groups[0]->id);

        $firstContested = collect($first->json('data.contested'))->pluck('id_peserta')->map(fn ($id) => (int) $id)->all();
        $this->assertCount(2, $firstContested);
        $firstPick = $firstContested[0];

        $second = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
                'qualification_mode' => 'per_group',
                'tiebreak_peserta_ids' => [$firstPick],
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('needs_tiebreak', true)
            ->assertJsonPath('data.slots_remaining', 1)
            ->assertJsonPath('data.tiebreak_grup_id', (int) $groups[1]->id);

        $secondContested = collect($second->json('data.contested'))->pluck('id_peserta')->map(fn ($id) => (int) $id)->all();
        $this->assertCount(2, $secondContested);
        $secondPick = $secondContested[0];

        $preview = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
                'qualification_mode' => 'per_group',
                'tiebreak_peserta_ids' => [$firstPick, $secondPick],
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('data.is_final', true);

        $qualifierIds = collect($preview->json('data.qualifiers'))
            ->pluck('id_peserta')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $expected = collect([
            $groups[0]->members->values()[0]->id_turnamen_peserta,
            $firstPick,
            $groups[1]->members->values()[0]->id_turnamen_peserta,
            $secondPick,
        ])->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame($expected, $qualifierIds);
    }

    public function test_matchmaking_history_lists_inactive_babak_rondes_after_reshuffle(): void
    {
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $this->assertTrue($mahjong->getMatchmakingHistory($turnamen->fresh())->isEmpty());

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $scores = $grup->members->values()->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();

        $mahjong->addGroupPointEntries($grup, $scores, (int) $grup->members->first()->id);
        $mahjong->reshuffleGroups($turnamen->fresh(), 'random');

        $history = $mahjong->getMatchmakingHistory($turnamen->fresh());

        $this->assertCount(1, $history);
        $this->assertSame(1, (int) $history[0]['babak']);
        $this->assertGreaterThanOrEqual(1, $history[0]['rondes']->count());

        $firstRonde = $history[0]['rondes']->first();
        $this->assertSame(1, (int) $firstRonde['ronde']);
        $this->assertGreaterThanOrEqual(1, $firstRonde['groups']->count());

        $historicalMember = $firstRonde['groups']->first()->members->first();
        $this->assertGreaterThan(0, $historicalMember->poinEntries->count());

        $guestHtml = $this->get(route('guest.standings', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('live-leaderboard', $guestHtml);
        $this->assertStringContainsString('id="public-mahjong-history-card"', $guestHtml);
        $this->assertStringContainsString('Riwayat Babak', $guestHtml);
        $this->assertStringContainsString($historicalMember->display_name, $guestHtml);
        $this->assertLessThan(
            strpos($guestHtml, 'id="public-mahjong-history-card"'),
            strpos($guestHtml, 'id="live-leaderboard"')
        );

        $admin = $this->makeAdmin();
        $adminHtml = $this->actingAs($admin)
            ->get(route('admin.standings.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="klasemen-mahjong-history-card"', $adminHtml);
        $this->assertStringContainsString('Riwayat Babak', $adminHtml);
        $this->assertStringNotContainsString('data-score-scope="table"', $guestHtml);
        $this->assertStringNotContainsString('data-score-scope="table"', $adminHtml);

        $matchmakingHtml = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="mahjong-history-card"', $matchmakingHtml);
        $this->assertStringContainsString('data-score-scope="table"', $matchmakingHtml);
        $this->assertStringContainsString('Klik nomor ronde untuk mengubah skor.', $matchmakingHtml);
        $this->assertStringContainsString('btn-mahjong-edit-ronde', $matchmakingHtml);
    }

    public function test_matchmaking_history_point_entries_can_be_updated_after_reshuffle(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $members = $grup->members->values();
        $scores = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();
        $mahjong->addGroupPointEntries($grup, $scores, (int) $members->first()->id);

        $entriesByMember = $members->mapWithKeys(function (GrupMember $member) {
            return [$member->id => $member->fresh()->poinEntries()->first()->id];
        });

        $firstMember = $members->first();
        $pesertaId = (int) $firstMember->id_turnamen_peserta;

        $mahjong->reshuffleGroups($turnamen->fresh(), 'random');

        $historical = $firstMember->fresh('grup');
        $this->assertFalse((bool) $historical->grup->is_aktif);
        $this->assertSame(0, (int) $historical->poin_didapat);
        $this->assertSame(8, (int) $historical->poin_akumulasi);

        $later = GrupMember::query()
            ->where('id_turnamen_peserta', $pesertaId)
            ->whereHas('grup', function ($query) {
                $query->where('is_aktif', true);
            })
            ->first();

        $this->assertNotNull($later);
        $this->assertSame(8, (int) $later->poin_akumulasi);

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-group-point-entries.update', $grup), [
                'id_grup_member_pemenang' => (int) $members->get(1)->id,
                'scores' => $members->map(function (GrupMember $member, int $index) use ($entriesByMember) {
                    return [
                        'id' => $member->id,
                        'entry_id' => $entriesByMember[$member->id],
                        'poin' => [10, 4, -7, -7][$index],
                    ];
                })->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $firstPayload = collect($response->json('data.members'))->firstWhere('id', $firstMember->id);
        $this->assertSame(10, (int) $firstPayload['poin_babak']);
        $this->assertSame(10, (int) $firstPayload['total_poin']);

        $historical->refresh();
        $this->assertSame(0, (int) $historical->poin_didapat);
        $this->assertSame(10, (int) $historical->poin_akumulasi);
        $this->assertSame(10, (int) $historical->poinEntries()->first()->poin);
        $this->assertFalse((bool) $historical->poinEntries()->first()->is_winner);

        $later->refresh();
        $this->assertSame(10, (int) $later->poin_akumulasi);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-group-point-entries.store', $grup), [
                'scores' => $members->map(function (GrupMember $member, int $index) {
                    return ['id' => $member->id, 'poin' => [1, 0, 0, -1][$index]];
                })->all(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Grup tidak aktif.');
    }

    public function test_matchmaking_workspace_renders_mahjong_group_as_round_table(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $members = $grup->members->values();
        $firstScores = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();
        $secondScores = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [12, -4, -4, -4][$index]];
        })->all();

        $winnerId = (int) $members->first()->id;

        $emptyHtml = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mahjong-group-score-table', $emptyHtml);
        $this->assertStringContainsString('>Ronde<', $emptyHtml);
        $this->assertStringContainsString('>Subtotal<', $emptyHtml);
        $this->assertStringContainsString('Belum ada ronde.', $emptyHtml);
        $this->assertStringContainsString('group-member-swap-source', $emptyHtml);
        $this->assertStringContainsString('btn-mahjong-input-poin', $emptyHtml);
        $this->assertStringContainsString('0 (0)', $emptyHtml);

        $mahjong->addGroupPointEntries($grup, $firstScores, $winnerId);
        $mahjong->addGroupPointEntries($grup->fresh('members'), $secondScores, $winnerId);

        $html = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mahjong-group-score-table', $html);
        $this->assertStringContainsString('mahjong-round-row', $html);
        $this->assertStringContainsString('data-round="1"', $html);
        $this->assertStringContainsString('data-round="2"', $html);
        $this->assertStringContainsString('20 (2)', $html);

        foreach ($members as $member) {
            $this->assertStringContainsString($member->display_name, $html);
        }

        $this->assertStringContainsString('btn-mahjong-edit-ronde', $html);
        $this->assertStringContainsString('Bonus/Penalti', $html);
        $this->assertStringContainsString('btn-mahjong-edit-adjustment', $html);
        $this->assertStringNotContainsString('btn-delete-mahjong-poin', $html);
        $this->assertStringContainsString('bi-trophy-fill', $html);
        $this->assertStringContainsString('btn-mahjong-input-poin', $html);
    }

    public function test_mahjong_standings_preview_marks_projected_qualifiers_and_ranking_columns(): void
    {
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');
        $this->seedDistinctMahjongGroupScores($service, $turnamen);

        $standings = app(LeaderboardService::class)->getMahjongStandingsByBabak($turnamen->id);
        $section = $standings['sections']->firstWhere('babak', 1);

        $this->assertNotNull($section);
        $this->assertSame('preview', $section['advance_kind']);
        $this->assertTrue($section['is_active']);
        $this->assertSame(4, $section['advance_count']);
        $this->assertStringContainsString('Total babak', $section['ranking_note']);

        $rows = collect($section['rows']);
        $this->assertCount(4, $rows->where('advance_status', 'pratinjau'));
        $this->assertSame(
            $rows->sortBy('rank')->take(4)->pluck('id_peserta')->all(),
            $rows->where('advances', true)->sortBy('rank')->pluck('id_peserta')->all()
        );

        $guestHtml = $this->get(route('guest.standings', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cara peringkat', $guestHtml);
        $this->assertStringContainsString('Total babak → Menang', $guestHtml);
        $this->assertStringContainsString('Lolos*', $guestHtml);
        $this->assertStringContainsString('Akumulasi', $guestHtml);
        $this->assertStringNotContainsString('>Status<', $guestHtml);

        $admin = $this->makeAdmin();
        $adminHtml = $this->actingAs($admin)
            ->get(route('admin.standings.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cara peringkat', $adminHtml);
        $this->assertStringContainsString('Lolos*', $adminHtml);
        $this->assertStringContainsString('>W<', $adminHtml);
        $this->assertStringNotContainsString('>Status<', $adminHtml);
    }

    public function test_mahjong_standings_marks_confirmed_advancers_after_advance(): void
    {
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');
        $this->seedDistinctMahjongGroupScores($service, $turnamen);

        $before = app(LeaderboardService::class)->getMahjongStandingsByBabak($turnamen->id);
        $qualifierIds = collect($before['sections']->first()['rows'])
            ->where('advances', true)
            ->pluck('id_peserta')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $service->advanceRound($turnamen->fresh(), 4);

        $standings = app(LeaderboardService::class)->getMahjongStandingsByBabak($turnamen->id);
        $babak1 = $standings['sections']->firstWhere('babak', 1);
        $babak2 = $standings['sections']->firstWhere('babak', 2);

        $this->assertSame('confirmed', $babak1['advance_kind']);
        $this->assertSame(2, $babak1['next_babak']);
        $this->assertSame('final', $babak2['advance_kind']);
        $this->assertTrue($babak2['is_final']);

        $confirmedIds = collect($babak1['rows'])
            ->where('advance_status', 'lolos')
            ->pluck('id_peserta')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame($qualifierIds, $confirmedIds);

        $html = $this->get(route('guest.standings', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Lolos ke Babak 2', $html);
        $this->assertStringContainsString('table-success', $html);
        $this->assertStringContainsString('Final', $html);
        $this->assertStringContainsString('Juara', $html);
        $this->assertStringNotContainsString('>Status<', $html);
    }

    public function test_mahjong_standings_do_not_mark_lolos_when_entire_field_is_tied(): void
    {
        $service = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $service->generateGroups($turnamen, 'random');

        $section = app(LeaderboardService::class)
            ->getMahjongStandingsByBabak($turnamen->id)['sections']
            ->first();

        $this->assertSame('preview', $section['advance_kind']);
        $this->assertSame(4, $section['advance_count']);
        $this->assertTrue(collect($section['rows'])->every(fn ($row) => empty($row['advance_status'])));
        $this->assertStringContainsString('masih seri', $section['advance_note']);
    }

    public function test_score_approval_toggle_gates_reshuffle_and_end_babak(): void
    {
        $admin = $this->makeAdmin();
        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-score-approval'), [
                'id_turnamen' => $turnamen->id,
                'enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.mahjong_require_score_approval', true);

        $this->assertTrue($mahjong->isScoreApprovalRequired($turnamen->fresh()));

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.reshuffle-groups'), [
                'id_turnamen' => $turnamen->id,
                'mode' => 'random',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Setujui skor semua pemain dulu sebelum reshuffle atau akhiri babak (8 pemain belum disetujui).');

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 4,
                'preview' => true,
            ])
            ->assertStatus(422);

        $html = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mahjong-score-approval-toggle', $html);
        $this->assertStringContainsString('btn-mahjong-approve-score', $html);
        $this->assertStringContainsString('btn-mahjong-approve-group', $html);

        $groups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->get();

        foreach ($groups as $grup) {
            $this->actingAs($admin)
                ->patchJson(route('admin.matchmaking.mahjong-group-score-approval', $grup))
                ->assertOk()
                ->assertJsonPath('success', true);
        }

        $this->assertSame(0, GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->where('poin_disetujui', false)
            ->count());

        $member = GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->with('grup.members')
            ->first();

        $scores = $member->grup->members->values()->map(function (GrupMember $item, int $index) {
            return ['id' => $item->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-group-point-entries.store', $member->grup), [
                'scores' => $scores,
                'id_grup_member_pemenang' => (int) $member->id,
            ])
            ->assertOk();

        $this->assertFalse((bool) $member->fresh()->poin_disetujui);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.reshuffle-groups'), [
                'id_turnamen' => $turnamen->id,
                'mode' => 'random',
            ])
            ->assertStatus(422);

        $groups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->get();

        foreach ($groups as $grup) {
            $this->actingAs($admin)
                ->patchJson(route('admin.matchmaking.mahjong-group-score-approval', $grup))
                ->assertOk();
        }

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.reshuffle-groups'), [
                'id_turnamen' => $turnamen->id,
                'mode' => 'random',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-score-approval'), [
                'id_turnamen' => $turnamen->id,
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.mahjong_require_score_approval', false);
    }

    protected function seedDistinctMahjongGroupScores(MahjongMatchmakingService $service, Turnamen $turnamen): void
    {
        $groups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('id')
            ->get();

        $base = 40;
        foreach ($groups as $grup) {
            $members = $grup->members->values();
            $scores = $members->map(function (GrupMember $member) use (&$base) {
                $poin = $base;
                $base -= 5;

                return ['id' => $member->id, 'poin' => $poin];
            })->all();

            $service->addGroupPointEntries($grup, $scores, (int) $scores[0]['id']);
        }
    }

    protected function prepareMahjongTournament(int $playerCount): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'Mahjong Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $playerCount,
            'jenis' => 'mahjong',
            'status' => 'ongoing',
        ]);

        for ($i = 1; $i <= $playerCount; $i++) {
            $pemain = Pemain::create([
                'nama' => "Mahjong Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62819' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT) . $i,
                'rating' => 2.5 + ($i * 0.1),
            ]);

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        return $turnamen;
    }

    protected function makeAdmin(): User
    {
        return User::create([
            'name' => 'Mahjong Admin',
            'username' => 'mahjong-admin-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);
    }
}
