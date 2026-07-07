<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class PaymentReceiptService
{
    const PUBLIC_DIR = 'img/bukti-bayar';
    const WEBP_QUALITY = 80;

    protected $converter;

    public function __construct(ImageWebpConverter $converter)
    {
        $this->converter = $converter;
    }

    public function store(UploadedFile $file): string
    {
        // Images are compressed to WebP; PDF receipts are kept as-is.
        if ($this->converter->supports($file)) {
            $filename = uniqid('bayar_', true) . '.webp';
            $relativePath = self::PUBLIC_DIR . '/' . $filename;

            $this->converter->convert(
                $file,
                public_path($relativePath),
                ImageWebpConverter::DEFAULT_MAX_WIDTH,
                self::WEBP_QUALITY
            );

            return $relativePath;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        if ($extension !== 'pdf') {
            throw new RuntimeException('Bukti bayar harus berformat JPG, PNG, WebP, atau PDF.');
        }

        $filename = uniqid('bayar_', true) . '.pdf';
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
