<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\GroupMatchmakingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class GroupMatchmakingLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_groups_are_editable_until_matches_are_generated_and_can_then_be_reset(): void
    {
        $turnamen = $this->createTournament('single');
        $this->createApprovedPairedEntries($turnamen, 12);
        $service = app(GroupMatchmakingService::class);

        $result = $service->generateRandomGroups($turnamen, 3, 3);

        $this->assertCount(2, $result['groups']);
        $this->assertSame(0, $turnamen->pertandingan()->count());
        $this->assertTrue($service->canEditGroups($turnamen->fresh()));

        $groups = $turnamen->grup()->with('members')->orderBy('id')->get();
        $first = $groups[0]->members->first();
        $second = $groups[1]->members->first();
        $service->swapGroupMembers($turnamen->fresh(), $first, $second);

        $this->assertSame($groups[1]->id, $first->fresh()->id_grup);
        $this->assertSame($groups[0]->id, $second->fresh()->id_grup);

        $oldGroupIds = $turnamen->grup()->orderBy('id')->pluck('id')->all();
        $service->generateRandomGroups($turnamen->fresh(), 3, 3);
        $this->assertNotSame($oldGroupIds, $turnamen->grup()->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, $turnamen->pertandingan()->count());

        $matches = $service->generateGroupMatches($turnamen->fresh());

        $this->assertSame(6, $matches['matches']);
        $this->assertNotNull($turnamen->fresh()->group_matches_generated_at);
        $this->assertFalse($service->canEditGroups($turnamen->fresh()));

        $service->resetGroupsAndMatches($turnamen->fresh());

        $this->assertSame(0, $turnamen->grup()->count());
        $this->assertSame(0, $turnamen->pertandingan()->count());
        $this->assertNull($turnamen->fresh()->group_matches_generated_at);
        $this->assertSame('ongoing', $turnamen->fresh()->status);
    }

    public function test_reset_is_rejected_after_a_match_is_completed(): void
    {
        $turnamen = $this->createTournament('single');
        $this->createApprovedPairedEntries($turnamen, 12);
        $service = app(GroupMatchmakingService::class);
        $service->generateRandomGroups($turnamen, 3, 3);
        $service->generateGroupMatches($turnamen->fresh());
        $turnamen->pertandingan()->first()->update(['status' => 'completed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reset hanya tersedia sebelum skor pertandingan dicatat.');

        $service->resetGroupsAndMatches($turnamen->fresh());
    }

    public function test_reset_preserves_double_pairs_and_registrations(): void
    {
        $turnamen = $this->createTournament('double');
        $this->createApprovedPairedEntries($turnamen, 6);

        $turnamen->update(['registration_paired_at' => now()]);
        $service = app(GroupMatchmakingService::class);
        $service->generateRandomGroups($turnamen->fresh(), 3, 3);
        $service->generateGroupMatches($turnamen->fresh());
        $service->resetGroupsAndMatches($turnamen->fresh());

        $this->assertSame(6, $turnamen->turnamenPeserta()->count());
        $this->assertSame(3, $turnamen->pasangan()->count());
        $this->assertNotNull($turnamen->fresh()->registration_paired_at);
    }

    public function test_assigned_panitia_can_swap_only_members_from_their_tournament(): void
    {
        $assigned = $this->createTournament('single');
        $other = $this->createTournament('single');
        $this->createApprovedPairedEntries($assigned, 12);
        $this->createApprovedPairedEntries($other, 12);
        $service = app(GroupMatchmakingService::class);
        $service->generateRandomGroups($assigned, 3, 3);
        $service->generateRandomGroups($other, 3, 3);

        $panitia = User::create([
            'name' => 'Lifecycle Panitia',
            'username' => 'lifecycle-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'panitia',
            'id_turnamen' => $assigned->id,
        ]);

        $assignedGroups = $assigned->grup()->with('members')->orderBy('id')->get();
        $this->actingAs($panitia)
            ->patchJson(route('admin.matchmaking.swap-group-members'), [
                'id_turnamen' => $assigned->id,
                'first_member_id' => $assignedGroups[0]->members->first()->id,
                'second_member_id' => $assignedGroups[1]->members->first()->id,
            ])
            ->assertOk();

        $otherGroups = $other->grup()->with('members')->orderBy('id')->get();
        $this->actingAs($panitia)
            ->patchJson(route('admin.matchmaking.swap-group-members'), [
                'id_turnamen' => $other->id,
                'first_member_id' => $otherGroups[0]->members->first()->id,
                'second_member_id' => $otherGroups[1]->members->first()->id,
            ])
            ->assertForbidden();
    }

    protected function createTournament(string $jenis): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Lifecycle Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 32,
            'jenis' => $jenis,
            'status' => 'ongoing',
            'registration_paired_at' => now(),
        ]);
    }

    protected function createApprovedPairedEntries(Turnamen $turnamen, int $playerCount)
    {
        $entries = collect(range(1, $playerCount))->map(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "Lifecycle Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0812' . str_pad((string) $index . substr(uniqid(), -4), 8, '0', STR_PAD_LEFT),
                'rating' => 2 + ($index / 10),
            ]);

            return TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        });

        foreach ($entries->chunk(2) as $pair) {
            $pair = $pair->values();
            TurnamenPasangan::create([
                'id_turnamen' => $turnamen->id,
                'id_peserta_1' => $pair[0]->id,
                'id_peserta_2' => $pair[1]->id,
                'paired_at' => now(),
            ]);
        }

        return $entries;
    }
}
