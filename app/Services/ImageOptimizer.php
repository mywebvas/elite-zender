<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Intelligent image optimization service.
 *
 * Automatically converts user-uploaded photos, receipts, and proofs to modern WebP
 * format at 75-80% quality while auto-orienting EXIF and downscaling excessively large
 * camera images (max 1920px). Non-image files (such as PDFs) pass through untouched.
 */
final class ImageOptimizer
{
    private const MAX_DIMENSION = 1920;

    private const WEBP_QUALITY = 78;

    /**
     * Store and optimize an uploaded file.
     *
     * @param  UploadedFile  $file  The uploaded file instance
     * @param  string  $directory  Target storage directory (e.g. 'payment-proofs/tenant-uuid')
     * @param  string  $disk  The filesystem disk to store the file on
     * @return string The relative storage path of the stored/optimized file
     */
    public function storeAndOptimize(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        $mime = (string) $file->getMimeType();

        // Non-images (e.g. application/pdf) pass through directly without modification
        if (! str_starts_with($mime, 'image/') || ! function_exists('imagecreatefromstring')) {
            return (string) $file->store($directory, $disk);
        }

        try {
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                return (string) $file->store($directory, $disk);
            }

            $image = @imagecreatefromstring($contents);
            if ($image === false) {
                return (string) $file->store($directory, $disk);
            }

            // Correct smartphone camera rotation using EXIF Orientation tag
            if (function_exists('exif_read_data') && ($mime === 'image/jpeg' || $mime === 'image/jpg')) {
                $exif = @exif_read_data($file->getRealPath());
                $orientation = $exif['Orientation'] ?? null;

                if ($orientation !== null) {
                    $rotated = match ((int) $orientation) {
                        3 => imagerotate($image, 180, 0),
                        6 => imagerotate($image, -90, 0),
                        8 => imagerotate($image, 90, 0),
                        default => null,
                    };

                    if ($rotated !== false && $rotated !== null) {
                        imagedestroy($image);
                        $image = $rotated;
                    }
                }
            }

            // Downscale excessively large images (e.g. 48MP phone photos) to save storage & bandwidth
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                if ($width >= $height) {
                    $newWidth = self::MAX_DIMENSION;
                    $newHeight = (int) max(1, round(($height / $width) * self::MAX_DIMENSION));
                } else {
                    $newHeight = self::MAX_DIMENSION;
                    $newWidth = (int) max(1, round(($width / $height) * self::MAX_DIMENSION));
                }

                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            // Convert and encode to WebP format if supported
            if (function_exists('imagewebp')) {
                ob_start();
                imagewebp($image, null, self::WEBP_QUALITY);
                $webpData = ob_get_clean();
                imagedestroy($image);

                if ($webpData !== false && $webpData !== '') {
                    $filename = Str::random(40).'.webp';
                    $targetPath = trim($directory, '/').'/'.$filename;
                    Storage::disk($disk)->put($targetPath, $webpData);

                    return $targetPath;
                }
            } else {
                imagedestroy($image);
            }
        } catch (Throwable) {
            // Gracefully catch any processing error and fall back to native storage
        }

        return (string) $file->store($directory, $disk);
    }
}
