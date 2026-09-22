<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    /**
     * Streams a proof through the API rather than handing out a link to the
     * file itself.
     *
     * Proofs are bank receipts, and they live on a private disk on purpose
     * (config/commerce.php). A temporary signed URL would work, but it is a
     * bearer credential in a query string: it keeps working for anyone who has
     * it, outlives the session that asked for it, and ends up in browser
     * history and proxy logs. Streaming keeps the admin session as the only
     * key, and behaves the same whatever disk the deployment uses.
     */
    public function show(PaymentProof $paymentProof): StreamedResponse
    {
        $disk = Storage::disk($paymentProof->disk);

        // The row can outlive the file — a restore, a botched deploy, a manual
        // cleanup. Better a 404 than a stream of nothing.
        abort_unless($disk->exists($paymentProof->path), 404);

        // Inline, so the panel can show the receipt in place instead of making
        // the admin download a file to look at it.
        return $disk->response($paymentProof->path, $paymentProof->original_name, [
            'Content-Type' => $paymentProof->mime_type,
        ]);
    }
}
