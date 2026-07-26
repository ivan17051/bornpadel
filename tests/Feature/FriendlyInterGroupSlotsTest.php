<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\FriendlyMatchmakingService;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FriendlyInterGroupSlotsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_generate_groups_creates_one_slot_per_group_pair(): void
    {
        $turnamen = $this->prepareFriendlyTournament(8);
        $service = app(FriendlyMatchmakingService::class);

        $result = $service->generateGroups($turnamen, 'random');

        $this->assertSame(2, $result['group_count']);
        $this->assertSame(1, $result['match_slots']);
        $this->assertSame(1, Pertandingan::where('id_turnamen', $turnamen->id)->where('nama_ronde', 'Friendly')->count());

        $slot = Pertandingan::where('id_turnamen', $turnamen->id)->where('nama_ronde', 'Friendly')->first();
        $this->assertNotNull($slot->id_grup1);
        $this->assertNotNull($slot->id_grup2);
        $this->assertNull($slot->id_pemain1);
        $this->assertNull($slot->id_pemain2);
        $this->assertFalse($slot->isReadyForScoring());
    }

    public function test_three_groups_create_three_slots(): void
    {
        $turnamen = $this->prepareFriendlyTournament(12);
        $result = app(FriendlyMatchmakingService::class)->generateGroups($turnamen, 'random');

        $this->assertSame(3, $result['group_count']);
        $this->assertSame(3, $result['match_slots']);
    }

    public function test_groups_remain_editable_until_a_match_has_scores(): void
    {
        $turnamen = $this->prepareFriendlyTournament(8);
        $service = app(FriendlyMatchmakingService::class);
        $service->generateGroups($turnamen, 'random');

        $this->assertTrue($service->canEditGroups($turnamen->fresh()));

        $slot = Pertandingan::where('id_turnamen', $turnamen->id)->first();
        $grup1Members = $slot->grup1->members()->pluck('id_pemain')->map(fn ($id) => (int) $id)->take(2)->all();
        $grup2Members = $slot->grup2->members()->pluck('id_pemain')->map(fn ($id) => (int) $id)->take(2)->all();

        $service->assignPairs($slot, $grup1Members, $grup2Members);
        $this->assertFalse($service->canEditGroups($turnamen->fresh()));

        app(MatchScoringService::class)->recordScore($slot->fresh(), [
            ['skor_pemain1' => 6, 'skor_pemain2' => 1],
            ['skor_pemain1' => 6, 'skor_pemain2' => 2],
        ]);

        $this->assertFalse($service->canEditGroups($turnamen->fresh()));
        $this->assertFalse($service->canReset($turnamen->fresh()));
    }

    public function test_assign_pairs_via_endpoint(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareFriendlyTournament(8);
        $service = app(FriendlyMatchmakingService::class);
        $service->generateGroups($turnamen, 'random');

        $slot = Pertandingan::where('id_turnamen', $turnamen->id)->first();
        $side1 = $slot->grup1->members()->pluck('id_pemain')->map(fn ($id) => (int) $id)->take(2)->values()->all();
        $side2 = $slot->grup2->members()->pluck('id_pemain')->map(fn ($id) => (int) $id)->take(2)->values()->all();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.friendly-match.pairs', $slot), [
                'id_turnamen' => $turnamen->id,
                'side1_pemain_ids' => $side1,
                'side2_pemain_ids' => $side2,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $slot->refresh();
        $this->assertTrue($slot->hasFriendlyPairsAssigned());
        $this->assertContains((int) $slot->id_pemain1, $side1);
        $this->assertContains((int) $slot->id_pemain1_partner, $side1);
    }

    public function test_manual_extra_match_still_works(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareFriendlyTournament(8);
        $service = app(FriendlyMatchmakingService::class);
        $service->generateGroups($turnamen, 'random');

        $slot = Pertandingan::where('id_turnamen', $turnamen->id)->first();
        $side1 = $slot->grup1->members()->pluck('id_pemain')->map(fn ($id) => (int) $id)->take(2)->values()->all();
        $side2 = $slot->grup2->members()->pluck('id_pemain')->map(fn ($id) => (int) $id)->take(2)->values()->all();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.friendly-match.store'), [
                'id_turnamen' => $turnamen->id,
                'id_grup1' => $slot->id_grup1,
                'id_grup2' => $slot->id_grup2,
                'side1_pemain_ids' => $side1,
                'side2_pemain_ids' => $side2,
            ])
            ->assertOk();

        $this->assertSame(2, Pertandingan::where('id_turnamen', $turnamen->id)->where('nama_ronde', 'Friendly')->count());
    }

    protected function prepareFriendlyTournament(int $playerCount): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'Friendly Slot Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $playerCount,
            'jenis' => 'friendly',
            'status' => 'ongoing',
        ]);

        for ($i = 1; $i <= $playerCount; $i++) {
            $pemain = Pemain::create([
                'nama' => "Friendly Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62817' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT) . $i,
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
            'name' => 'Friendly Admin',
            'username' => 'friendly-admin-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);
    }
}
