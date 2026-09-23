<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenGrupPendaftaran;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PesertaBulkDeleteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_bulk_delete_selected_registered_peserta(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->createOpenTurnamen();
        $keep = $this->seedPeserta($turnamen, 'Keep Player');
        $removeA = $this->seedPeserta($turnamen, 'Remove A');
        $removeB = $this->seedPeserta($turnamen, 'Remove B');

        $html = $this->actingAs($admin)
            ->get(route('admin.pemain.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Hapus Terpilih', $html);
        $this->assertStringContainsString('peserta-bulk-checkbox', $html);
        $this->assertStringContainsString((string) $removeA->id, $html);

        $this->actingAs($admin)
            ->postJson(route('admin.peserta.bulk-delete'), [
                'id_turnamen' => $turnamen->id,
                'peserta_ids' => [$removeA->id, $removeB->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertDatabaseMissing('turnamen_peserta', ['id' => $removeA->id]);
        $this->assertDatabaseMissing('turnamen_peserta', ['id' => $removeB->id]);
        $this->assertDatabaseHas('turnamen_peserta', ['id' => $keep->id]);
        $this->assertDatabaseHas('m_pemain', ['id' => $removeA->id_pemain1, 'nama' => 'Remove A']);
        $this->assertDatabaseHas('m_pemain', ['id' => $removeB->id_pemain1, 'nama' => 'Remove B']);
    }

    public function test_bulk_delete_is_blocked_when_tournament_is_ongoing(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->createOpenTurnamen();
        $peserta = $this->seedPeserta($turnamen, 'Locked Player');
        $turnamen->update(['status' => 'ongoing']);

        $this->actingAs($admin)
            ->postJson(route('admin.peserta.bulk-delete'), [
                'id_turnamen' => $turnamen->id,
                'peserta_ids' => [$peserta->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('turnamen_peserta', ['id' => $peserta->id]);
    }

    public function test_bulk_delete_is_blocked_when_peserta_is_in_a_group(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->createOpenTurnamen();
        $peserta = $this->seedPeserta($turnamen, 'Grouped Player');

        $grup = Grup::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $turnamen->defaultKategori()->id,
            'nama' => 'Grup A',
            'is_aktif' => true,
        ]);
        GrupMember::create([
            'id_grup' => $grup->id,
            'id_pemain' => $peserta->id_pemain1,
            'id_turnamen_peserta' => $peserta->id,
            'poin_didapat' => 0,
            'set_menang' => 0,
            'games_menang' => 0,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.peserta.bulk-delete'), [
                'id_turnamen' => $turnamen->id,
                'peserta_ids' => [$peserta->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('turnamen_peserta', ['id' => $peserta->id]);
    }

    public function test_bulk_delete_removes_mahjong_team_registration_membership(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = Turnamen::create([
            'nama' => 'Bulk Delete Team '.uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 40,
            'jenis' => 'mahjong_team',
            'players_per_group' => 4,
            'status' => 'open',
        ]);
        $turnamen->ensureDefaultKategori();

        $result = app(PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Delete Squad',
            $this->teamPayloads(),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false,
            'approved'
        );
        $group = $result['grup_pendaftaran'];
        $pesertaIds = $group->members()->pluck('id_peserta')->all();

        $this->actingAs($admin)
            ->postJson(route('admin.peserta.bulk-delete'), [
                'id_turnamen' => $turnamen->id,
                'peserta_ids' => $pesertaIds,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 4);

        $this->assertSame(0, TurnamenPeserta::query()->forTurnamen($turnamen->id)->count());
        $this->assertNull(TurnamenGrupPendaftaran::query()->find($group->id));
    }

    protected function createOpenTurnamen(): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'Bulk Delete '.uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'single',
            'status' => 'open',
        ]);
        $turnamen->ensureDefaultKategori();

        return $turnamen;
    }

    protected function seedPeserta(Turnamen $turnamen, string $nama): TurnamenPeserta
    {
        $pemain = Pemain::create([
            'nama' => $nama,
            'gender' => 'male',
            'no_hp' => '+62819'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'rating' => 3,
        ]);

        return TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $turnamen->defaultKategori()->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function teamPayloads(): array
    {
        $players = [];

        for ($i = 1; $i <= 4; $i++) {
            $players[] = [
                'nama' => "Bulk Team Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62817'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT).$i,
                'rating' => 2.5,
            ];
        }

        return $players;
    }

    protected function makeAdmin(): User
    {
        return User::create([
            'name' => 'Bulk Delete Admin',
            'username' => 'bulk-del-admin-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);
    }
}
