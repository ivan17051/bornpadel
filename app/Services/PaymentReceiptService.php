<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class PaymentReceiptService
{
    const PUBLIC_DIR = 'img/bukti-bayar';

    public function store(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (! in_array($extension, $allowed, true)) {
            throw new RuntimeException('Bukti bayar harus berformat JPG, PNG, WebP, atau PDF.');
        }

        $filename = uniqid('bayar_', true) . '.' . $extension;
        $relativePath = self::PUBLIC_DIR . '/' . $filename;
        $fullPath = public_path($relativePath);
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! $file->move($directory, $filename)) {
            throw new RuntimeException('Gagal menyimpan bukti bayar.');
        }

        return $relativePath;
    }

    public function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
        $fullPath = public_path($normalized);

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function url(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));

        if (! file_exists(public_path($normalized))) {
            return null;
        }

        return asset('public/' . $normalized);
    }
}
