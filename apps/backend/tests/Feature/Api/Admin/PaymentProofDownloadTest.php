<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proofs live on a private disk and are never web-accessible. The only way to
 * one is this endpoint, behind the admin session.
 */
class PaymentProofDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();

        Storage::fake('local');
    }

    private function proof(array $overrides = []): PaymentProof
    {
        $order = Order::factory()->create([
            'payment_method_id' => PaymentMethod::factory()->create()->id,
        ]);

        $proof = PaymentProof::factory()->create(array_merge([
            'order_id' => $order->id,
            'disk' => 'local',
            'path' => 'payment-proofs/'.$order->order_number.'/comprobante.jpg',
            'original_name' => 'comprobante.jpg',
            'mime_type' => 'image/jpeg',
        ], $overrides));

        Storage::disk('local')->put($proof->path, 'contenido-del-comprobante');

        return $proof;
    }

    public function test_a_proof_is_not_reachable_without_a_session(): void
    {
        $proof = $this->proof();

        $this->getJson("/api/admin/payment-proofs/{$proof->id}")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_staff_can_stream_a_proof(): void
    {
        $proof = $this->proof();

        $response = $this->actingAs(User::factory()->staff()->create())
            ->get("/api/admin/payment-proofs/{$proof->id}");

        $response->assertOk();
        $response->assertHeader('content-type', 'image/jpeg');
        $this->assertSame('contenido-del-comprobante', $response->streamedContent());
    }

    public function test_the_file_is_served_inline_so_the_panel_can_show_it(): void
    {
        $proof = $this->proof();

        $response = $this->actingAs(User::factory()->owner()->create())
            ->get("/api/admin/payment-proofs/{$proof->id}");

        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('comprobante.jpg', $response->headers->get('content-disposition'));
    }

    public function test_a_pdf_keeps_its_own_content_type(): void
    {
        $proof = $this->proof([
            'path' => 'payment-proofs/recibo.pdf',
            'original_name' => 'recibo.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs(User::factory()->owner()->create())
            ->get("/api/admin/payment-proofs/{$proof->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_row_whose_file_is_gone_is_a_404_not_an_empty_stream(): void
    {
        $proof = $this->proof();

        Storage::disk('local')->delete($proof->path);

        $this->actingAs(User::factory()->owner()->create())
            ->getJson("/api/admin/payment-proofs/{$proof->id}")
            ->assertNotFound();
    }

    public function test_an_unknown_proof_is_a_404(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/payment-proofs/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_the_url_the_order_detail_hands_out_actually_works(): void
    {
        $proof = $this->proof();

        $detail = $this->actingAs(User::factory()->owner()->create())
            ->getJson("/api/admin/orders/{$proof->order->order_number}")
            ->assertOk();

        $url = $detail->json('data.payment_proofs.0.download_url');

        $this->assertNotNull($url);

        $this->actingAs(User::factory()->owner()->create())
            ->get($url)
            ->assertOk();
    }
}
