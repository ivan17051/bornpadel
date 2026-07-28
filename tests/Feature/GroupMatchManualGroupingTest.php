<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\FriendlyMatchmakingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupMatchManualGroupingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_jenis_label_is_group_match(): void
    {
        $turnamen = $this->prepareTournament(8);
        $this->assertSame('Group Match', $turnamen->jenis_label);
    }

    public function test_skeleton_then_manual_assign_then_randomize_remaining(): void
    {
        $turnamen = $this->prepareTournament(8);
        $service = app(FriendlyMatchmakingService::class);

        $skeleton = $service->createSkeletonGroups($turnamen);
        $this->assertSame(2, $skeleton['group_count']);
        $this->assertSame(0, $turnamen->fresh()->grup()->withCount('members')->get()->sum('members_count'));
        $this->assertSame(0, Pertandingan::where('id_turnamen', $turnamen->id)->count());

        $unassigned = $service->getUnassignedApprovedEntries($turnamen);
        $this->assertCount(8, $unassigned);

        $grupA = $turnamen->fresh()->grup()->orderBy('id')->first();
        $service->assignMemberToGroup($turnamen, $grupA->id, $unassigned[0]->id);
        $service->assignMemberToGroup($turnamen, $grupA->id, $unassigned[1]->id);

        $this->assertCount(6, $service->getUnassignedApprovedEntries($turnamen->fresh()));
        $this->assertTrue($service->canRandomizeUnassigned($turnamen->fresh()));

        $result = $service->randomizeUnassigned($turnamen->fresh(), 'random');
        $this->assertSame(6, $result['assigned_count']);
        $this->assertSame(1, $result['match_slots']);
        $this->assertTrue($service->areGroupsComplete($turnamen->fresh()));
        $this->assertCount(0, $service->getUnassignedApprovedEntries($turnamen->fresh()));
    }

    public function test_rename_group_via_endpoint(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareTournament(8);
        $service = app(FriendlyMatchmakingService::class);
        $service->createSkeletonGroups($turnamen);
        $grup = $turnamen->fresh()->grup()->first();

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.grup.rename', $grup), [
                'id_turnamen' => $turnamen->id,
                'nama' => 'Alpha Squad',
            ])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Alpha Squad');

        $this->assertSame('Alpha Squad', $grup->fresh()->nama);
    }

    public function test_bulk_assign_via_modal_endpoint(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareTournament(8);
        $service = app(FriendlyMatchmakingService::class);
        $service->createSkeletonGroups($turnamen);

        $grup = $turnamen->fresh()->grup()->orderBy('id')->first();
        $unassigned = $service->getUnassignedApprovedEntries($turnamen)->take(3)->pluck('id')->all();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.friendly.assign'), [
                'id_turnamen' => $turnamen->id,
                'id_grup' => $grup->id,
                'id_peserta' => $unassigned,
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 3);

        $this->assertSame(3, $grup->fresh()->members()->count());
        $this->assertCount(5, $service->getUnassignedApprovedEntries($turnamen->fresh()));
    }

    public function test_randomize_skipped_when_everyone_already_grouped(): void
    {
        $turnamen = $this->prepareTournament(8);
        $service = app(FriendlyMatchmakingService::class);
        $service->generateGroups($turnamen, 'random');

        $this->assertFalse($service->canRandomizeUnassigned($turnamen->fresh()));
        $this->assertFalse($service->canGenerateGroups($turnamen->fresh()));
    }

    protected function prepareTournament(int $playerCount): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'Group Match Manual ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $playerCount,
            'jenis' => 'friendly',
            'status' => 'ongoing',
        ]);

        for ($i = 1; $i <= $playerCount; $i++) {
            $pemain = Pemain::create([
                'nama' => "GM Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62818' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT) . $i,
                'rating' => 2.0 + ($i * 0.2),
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
            'name' => 'GM Admin',
            'username' => 'gm-admin-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);
    }
}
