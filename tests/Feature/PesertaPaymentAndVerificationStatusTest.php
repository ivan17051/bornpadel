<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PesertaPaymentAndVerificationStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_registration_keeps_pending_verification_and_marks_payment_from_receipt(): void
    {
        $turnamen = $this->createOpenTournament();
        $service = app(PemainRegistrationService::class);

        $unpaidPemain = $service->register($turnamen, [
            'nama' => 'Unpaid Guest',
            'gender' => 'male',
            'no_hp' => '+6281310000001',
            'rating' => 3,
        ]);

        $unpaid = $unpaidPemain->pesertaForTurnamen($turnamen);
        $this->assertSame('pending', $unpaid->status);
        $this->assertSame('unpaid', $unpaid->payment_status);

        Storage::fake('public');
        $receipt = UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf');

        $paidPemain = $service->register($turnamen, [
            'nama' => 'Paid Guest',
            'gender' => 'female',
            'no_hp' => '+6281310000002',
            'rating' => 3,
        ], null, $receipt);

        $paid = $paidPemain->pesertaForTurnamen($turnamen);
        $this->assertSame('pending', $paid->status);
        $this->assertSame('paid', $paid->payment_status);
        app(\App\Services\PaymentReceiptService::class)->delete($paid->bukti_bayar);
    }

    public function test_admin_can_approve_without_changing_payment_and_can_toggle_payment(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->createOpenTournament();
        $pemain = Pemain::create([
            'nama' => 'Split Status Player',
            'gender' => 'male',
            'no_hp' => '+6281310000003',
            'rating' => 3,
        ]);
        $peserta = TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => 'pending',
            'payment_status' => 'paid',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.pemain.status', $pemain), [
                'id_turnamen' => $turnamen->id,
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $peserta->refresh();
        $this->assertSame('approved', $peserta->status);
        $this->assertSame('paid', $peserta->payment_status);

        $this->actingAs($admin)
            ->patchJson(route('admin.pemain.status', $pemain), [
                'id_turnamen' => $turnamen->id,
                'payment_status' => 'unpaid',
            ])
            ->assertOk();

        $peserta->refresh();
        $this->assertSame('approved', $peserta->status);
        $this->assertSame('unpaid', $peserta->payment_status);

        $html = $this->actingAs($admin)
            ->get(route('admin.pemain.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('Unpaid', $html);
        $this->assertStringContainsString('Tandai Sudah Bayar', $html);
    }

    public function test_legacy_paid_status_is_normalized_to_pending_verification(): void
    {
        $state = TurnamenPeserta::normalizeRegistrationState('paid');
        $this->assertSame('pending', $state['status']);
        $this->assertSame('paid', $state['payment_status']);

        $unpaid = TurnamenPeserta::normalizeRegistrationState('unpaid');
        $this->assertSame('pending', $unpaid['status']);
        $this->assertSame('unpaid', $unpaid['payment_status']);

        $approved = TurnamenPeserta::normalizeRegistrationState('approved', null, true);
        $this->assertSame('approved', $approved['status']);
        $this->assertSame('paid', $approved['payment_status']);
    }

    public function test_uploading_receipt_marks_paid_without_approving(): void
    {
        $turnamen = $this->createOpenTournament();
        $pemain = Pemain::create([
            'nama' => 'Receipt Player',
            'gender' => 'male',
            'no_hp' => '+6281310000004',
            'rating' => 3,
        ]);
        $peserta = TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        Storage::fake('public');
        $receipt = UploadedFile::fake()->create('bayar.pdf', 20, 'application/pdf');
        app(PemainRegistrationService::class)->updateBuktiBayar($peserta, $receipt);

        $peserta->refresh();
        $this->assertSame('pending', $peserta->status);
        $this->assertSame('paid', $peserta->payment_status);
        $this->assertNotEmpty($peserta->bukti_bayar);

        app(\App\Services\PaymentReceiptService::class)->delete($peserta->bukti_bayar);
    }

    protected function createOpenTournament(): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Payment Split Test '.uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'single',
            'status' => 'open',
        ]);
    }

    protected function makeAdmin(): User
    {
        return User::create([
            'name' => 'Payment Admin',
            'username' => 'pay-admin-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);
    }
}
