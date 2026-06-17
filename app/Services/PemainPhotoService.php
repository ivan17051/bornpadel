<?php

namespace App\Services;

use App\Models\Pemain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PemainPhotoService
{
    const DISK = 'public';
    const PUBLIC_DIR = 'img/pemain';
    const LEGACY_STORAGE_DIR = 'pemain/fotos';
    const MAX_WIDTH = 1200;
    const WEBP_QUALITY = 85;

    public function storeAsWebp(UploadedFile $file): string
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('Konversi WebP tidak didukung di server ini.');
        }

        $image = $this->createImageResource($file);
        $image = $this->resizeIfNeeded($image);
        $filename = uniqid('pemain_', true) . '.webp';
        $relativePath = self::PUBLIC_DIR . '/' . $filename;
        $fullPath = public_path($relativePath);

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! imagewebp($image, $fullPath, self::WEBP_QUALITY)) {
            imagedestroy($image);
            throw new RuntimeException('Gagal menyimpan foto dalam format WebP.');
        }

        imagedestroy($image);

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

    protected function createImageResource(UploadedFile $file)
    {
        $mime = $file->getMimeType();
        $path = $file->getRealPath();

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($path);
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($path);
                break;
            default:
                throw new RuntimeException('Format gambar harus JPEG, PNG, atau WebP.');
        }

        if (! $image) {
            throw new RuntimeException('Gagal memproses gambar yang diunggah.');
        }

        return $image;
    }

    protected function resizeIfNeeded($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= self::MAX_WIDTH) {
            return $image;
        }

        $newWidth = self::MAX_WIDTH;
        $newHeight = (int) round($height * ($newWidth / $width));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }
}
