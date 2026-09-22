<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\User;
use App\Notifications\PaymentProofSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PaymentProofService
{
    /**
     * Stores an uploaded proof, moves the order to payment_submitted and warns
     * the admins.
     *
     * The file is written before the transaction opens: a stored file with no
     * database row is recoverable clutter, whereas a row pointing at a file
     * that was never written would break the admin panel.
     */
    public function store(Order $order, UploadedFile $file, ?string $reference): PaymentProof
    {
        $disk = (string) config('commerce.payment_proof.disk');
        $path = $this->putFile($order, $file, $disk);

        $proof = DB::transaction(function () use ($order, $file, $disk, $path, $reference) {
            $proof = $order->paymentProofs()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => Storage::disk($disk)->mimeType($path) ?: $file->getClientMimeType(),
                'size_bytes' => Storage::disk($disk)->size($path),
                'reference' => $reference,
                'submitted_at' => now(),
            ]);

            $order->markPaymentSubmitted();

            return $proof;
        });

        $this->notifyAdmins($order, $proof);

        return $proof;
    }

    /**
     * Images are re-encoded and downscaled — a 12MP phone photo of a bank
     * receipt is unreadable clutter at full size. PDFs are stored as-is.
     */
    private function putFile(Order $order, UploadedFile $file, string $disk): string
    {
        $directory = trim((string) config('commerce.payment_proof.directory'), '/').'/'.$order->order_number;

        if (! Str::startsWith($file->getMimeType() ?: '', 'image/')) {
            return $file->store($directory, ['disk' => $disk]);
        }

        $image = (new ImageManager(new Driver))->read($file->getRealPath())
            ->scaleDown(width: (int) config('commerce.payment_proof.image_max_width'));

        $encoded = $image->toJpeg(quality: (int) config('commerce.payment_proof.image_quality'));

        $path = $directory.'/'.Str::uuid()->toString().'.jpg';

        Storage::disk($disk)->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Queued so a slow mail server can never delay the customer's upload
     * response (the notification implements ShouldQueue).
     */
    private function notifyAdmins(Order $order, PaymentProof $proof): void
    {
        $admins = User::query()->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new PaymentProofSubmitted($order, $proof));
    }
}
