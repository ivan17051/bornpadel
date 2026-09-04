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

    public function test_group_point_entries_require_winner(): void
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

        $scores = $grup->members->values()->map(function (GrupMember $member) {
            return ['id' => $member->id, 'poin' => 0];
        })->all();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-group-point-entries.store', $grup), [
                'scores' => $scores,
            ])
            ->assertStatus(422);
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
