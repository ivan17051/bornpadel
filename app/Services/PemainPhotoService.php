<?php

namespace App\Services;

use App\Models\Pemain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PemainPhotoService
{
    const DISK = 'public';
    const PUBLIC_DIR = 'img/pemain';
    const LEGACY_STORAGE_DIR = 'pemain/fotos';
    const MAX_WIDTH = 1200;
    const WEBP_QUALITY = 85;

    protected $converter;

    public function __construct(ImageWebpConverter $converter)
    {
        $this->converter = $converter;
    }

    public function storeAsWebp(UploadedFile $file): string
    {
        $filename = uniqid('pemain_', true) . '.webp';
        $relativePath = self::PUBLIC_DIR . '/' . $filename;

        $this->converter->convert(
            $file,
            public_path($relativePath),
            self::MAX_WIDTH,
            self::WEBP_QUALITY
        );

        return $relativePath;
    }

    public function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $normalized = $this->normalizePath($relativePath);

        if (strpos($normalized, self::PUBLIC_DIR . '/') === 0) {
            $fullPath = public_path($normalized);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return;
        }

        if (Storage::disk(self::DISK)->exists($normalized)) {
            Storage::disk(self::DISK)->delete($normalized);
        }
    }

    public function url(?string $relativePath): string
    {
        $resolved = $this->resolvePublicRelativePath($relativePath);

        if ($resolved) {
            return $this->toPublicUrl($resolved);
        }

        return $this->placeholderUrl();
    }

    public function placeholderUrl(): string
    {
        return $this->toPublicUrl('img/pemain-placeholder.svg');
    }

    public function migrateStoredPhotos(): int
    {
        $migrated = 0;

        foreach (Pemain::whereNotNull('foto')->get() as $pemain) {
            $newPath = $this->migratePathToPublic($pemain->foto);

            if ($newPath && $newPath !== $pemain->foto) {
                $pemain->update(['foto' => $newPath]);
                $migrated++;
            }
        }

        return $migrated;
    }

    protected function resolvePublicRelativePath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $normalized = $this->normalizePath($relativePath);

        if ($this->existsAtPublic($normalized)) {
            return $normalized;
        }

        if (strpos($normalized, self::LEGACY_STORAGE_DIR . '/') === 0) {
            $legacyPublic = 'storage/' . $normalized;
            if ($this->existsAtPublic($legacyPublic)) {
                return $legacyPublic;
            }

            $migrated = $this->copyToPublicDir($normalized);
            if ($migrated) {
                return $migrated;
            }
        }

        return null;
    }

    protected function migratePathToPublic(string $relativePath): ?string
    {
        $normalized = $this->normalizePath($relativePath);

        if (strpos($normalized, self::PUBLIC_DIR . '/') === 0) {
            return $normalized;
        }

        return $this->copyToPublicDir($normalized);
    }

    protected function copyToPublicDir(string $normalized): ?string
    {
        $basename = basename($normalized);
        $destination = self::PUBLIC_DIR . '/' . $basename;
        $destinationFull = public_path($destination);

        if (file_exists($destinationFull)) {
            return $destination;
        }

        $sources = [
            public_path('storage/' . $normalized),
            storage_path('app/public/' . $normalized),
            public_path($normalized),
        ];

        foreach ($sources as $source) {
            if (! file_exists($source)) {
                continue;
            }

            $directory = dirname($destinationFull);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            if (copy($source, $destinationFull)) {
                return $destination;
            }
        }

        return null;
    }

    protected function existsAtPublic(string $relativePath): bool
    {
        return file_exists(public_path($relativePath));
    }

    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', ltrim($path, '/'));
    }

    protected function toPublicUrl(string $publicRelativePath): string
    {
        return asset('public/' . $this->normalizePath($publicRelativePath));
    }
}
