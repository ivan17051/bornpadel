<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class TurnamenPhotoService
{
    const PUBLIC_DIR = 'img/turnamen';
    const MAX_WIDTH = 1200;
    const JPEG_QUALITY = 85;

    protected $converter;

    public function __construct(ImageWebpConverter $converter)
    {
        $this->converter = $converter;
    }

    public function storeAsJpeg(UploadedFile $file): string
    {
        $filename = uniqid('turnamen_', true) . '.jpg';
        $relativePath = self::PUBLIC_DIR . '/' . $filename;

        $this->converter->convertToJpeg(
            $file,
            public_path($relativePath),
            self::MAX_WIDTH,
            self::JPEG_QUALITY
        );

        return $relativePath;
    }

    public function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $normalized = $this->normalizePath($relativePath);
        $fullPath = public_path($normalized);

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function url(?string $relativePath): ?string
    {
        $resolved = $this->resolvePublicRelativePath($relativePath);

        if (! $resolved) {
            return null;
        }

        return $this->toPublicUrl($resolved);
    }

    /**
     * Absolute image URL for Open Graph / WhatsApp. Falls back to brand logo.
     */
    public function shareUrl(?string $relativePath = null): string
    {
        $url = $this->url($relativePath);

        if ($url) {
            return $this->toAbsoluteUrl($url);
        }

        return $this->toAbsoluteUrl($this->toPublicUrl('img/bornpadel.png'));
    }

    public function placeholderUrl(): string
    {
        return $this->toPublicUrl('img/bornpadel.png');
    }

    protected function resolvePublicRelativePath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $normalized = $this->normalizePath($relativePath);

        if (file_exists(public_path($normalized))) {
            return $normalized;
        }

        return null;
    }

    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', ltrim($path, '/'));
    }

    protected function toPublicUrl(string $publicRelativePath): string
    {
        return asset('public/' . $this->normalizePath($publicRelativePath));
    }

    protected function toAbsoluteUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return url($url);
    }
}
