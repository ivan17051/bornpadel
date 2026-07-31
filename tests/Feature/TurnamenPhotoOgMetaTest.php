<?php

namespace Tests\Feature;

use App\Models\Turnamen;
use App\Models\User;
use App\Services\TurnamenPhotoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TurnamenPhotoOgMetaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_upload_tournament_photo_as_jpeg(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.turnamen.store'), [
                'nama' => 'OG Preview Cup',
                'tanggal' => now()->addDays(7)->toDateString(),
                'harga' => 150000,
                'maks_peserta' => 16,
                'syarat' => null,
                'jenis' => 'single',
                'status' => 'open',
                'foto' => UploadedFile::fake()->image('poster.png', 800, 450),
            ]);

        $response->assertRedirect(route('admin.turnamen.index'));

        $turnamen = Turnamen::where('nama', 'OG Preview Cup')->first();
        $this->assertNotNull($turnamen);
        $this->assertNotNull($turnamen->foto);
        $this->assertStringEndsWith('.jpg', $turnamen->foto);
        $this->assertFileExists(public_path($turnamen->foto));

        @unlink(public_path($turnamen->foto));
    }

    public function test_guest_register_page_includes_open_graph_image(): void
    {
        $turnamen = Turnamen::create([
            'nama' => 'Shareable Open Tournament',
            'tanggal' => now()->addDays(3)->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 12,
            'jenis' => 'friendly',
            'status' => 'open',
            'foto' => null,
        ]);

        $shareUrl = app(TurnamenPhotoService::class)->shareUrl(null);

        $this->get(route('guest.register', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee('content="' . e($shareUrl) . '"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('Shareable Open Tournament', false);
    }

    public function test_admin_can_remove_tournament_photo(): void
    {
        $admin = $this->makeAdmin();
        $relativePath = 'img/turnamen/test_remove_' . uniqid('', true) . '.jpg';
        $fullPath = public_path($relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        imagejpeg(imagecreatetruecolor(320, 180), $fullPath, 85);

        $turnamen = Turnamen::create([
            'nama' => 'Remove Photo Cup',
            'tanggal' => now()->addDays(5)->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 8,
            'jenis' => 'single',
            'status' => 'open',
            'foto' => $relativePath,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.turnamen.update', $turnamen), [
                'nama' => $turnamen->nama,
                'tanggal' => $turnamen->tanggal->toDateString(),
                'harga' => $turnamen->harga,
                'maks_peserta' => $turnamen->maks_peserta,
                'syarat' => null,
                'jenis' => $turnamen->jenis,
                'status' => $turnamen->status,
                'remove_foto' => '1',
            ])
            ->assertRedirect(route('admin.turnamen.index'));

        $turnamen->refresh();
        $this->assertNull($turnamen->foto);
        $this->assertFileDoesNotExist($fullPath);
    }

    protected function makeAdmin(): User
    {
        $suffix = uniqid('', true);

        return User::create([
            'name' => 'Admin Photo',
            'username' => 'admin-photo-' . $suffix,
            'email' => 'admin.photo.' . $suffix . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }
}
