<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Central helper that converts any uploaded raster image into a compressed
 * WebP file. Every image upload in the application should go through this
 * service so that stored images share a single, consistent format.
 */
class ImageWebpConverter
{
    const DEFAULT_MAX_WIDTH = 1200;
    const DEFAULT_QUALITY = 80;

    const CONVERTIBLE_MIMES = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
    ];

    /**
     * Whether the uploaded file is a raster image we can convert to WebP.
     */
    public function supports(UploadedFile $file): bool
    {
        $mime = (string) $file->getMimeType();

        return in_array($mime, self::CONVERTIBLE_MIMES, true);
    }

    /**
     * Convert an uploaded image to a compressed WebP file saved at $fullPath.
     */
    public function convert(
        UploadedFile $file,
        string $fullPath,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $quality = self::DEFAULT_QUALITY
    ): void {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('Konversi WebP tidak didukung di server ini.');
        }

        $image = $this->createImageResource($file);
        $image = $this->resizeIfNeeded($image, $maxWidth);

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! imagewebp($image, $fullPath, $quality)) {
            imagedestroy($image);
            throw new RuntimeException('Gagal menyimpan gambar dalam format WebP.');
        }

        imagedestroy($image);
    }

    /**
     * Convert an uploaded image to a compressed JPEG file saved at $fullPath.
     * Prefer JPEG for Open Graph / WhatsApp link previews.
     */
    public function convertToJpeg(
        UploadedFile $file,
        string $fullPath,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $quality = self::DEFAULT_QUALITY
    ): void {
        if (! function_exists('imagejpeg')) {
            throw new RuntimeException('Konversi JPEG tidak didukung di server ini.');
        }

        $image = $this->createImageResource($file);
        $image = $this->resizeIfNeeded($image, $maxWidth);

        // Flatten transparency onto white for JPEG.
        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! imagejpeg($canvas, $fullPath, $quality)) {
            imagedestroy($canvas);
            throw new RuntimeException('Gagal menyimpan gambar dalam format JPEG.');
        }

        imagedestroy($canvas);
    }

    protected function createImageResource(UploadedFile $file)
    {
        $mime = $file->getMimeType();
        $path = $file->getRealPath();

        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
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
            case 'image/gif':
                $image = imagecreatefromgif($path);
                break;
            case 'image/bmp':
                $image = function_exists('imagecreatefrombmp') ? imagecreatefrombmp($path) : false;
                break;
            default:
                throw new RuntimeException('Format gambar harus JPEG, PNG, WebP, GIF, atau BMP.');
        }

        if (! $image) {
            throw new RuntimeException('Gagal memproses gambar yang diunggah.');
        }

        return $image;
    }

    protected function resizeIfNeeded($image, int $maxWidth)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($maxWidth <= 0 || $width <= $maxWidth) {
            return $image;
        }

        $newWidth = $maxWidth;
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
