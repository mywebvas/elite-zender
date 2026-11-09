<?php

use App\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->optimizer = new ImageOptimizer;
});

it('optimizes and converts an uploaded png image to webp', function (): void {
    $file = UploadedFile::fake()->image('receipt.png', 400, 300);

    $storedPath = $this->optimizer->storeAndOptimize($file, 'proofs/tenant-123', 'local');

    expect($storedPath)->toEndWith('.webp');
    Storage::disk('local')->assertExists($storedPath);

    // Verify it is a valid WebP binary
    $contents = Storage::disk('local')->get($storedPath);
    expect(substr($contents, 0, 4))->toBe('RIFF')
        ->and(substr($contents, 8, 4))->toBe('WEBP');
});

it('optimizes and converts an uploaded jpeg image to webp', function (): void {
    $file = UploadedFile::fake()->image('receipt.jpg', 800, 600);

    $storedPath = $this->optimizer->storeAndOptimize($file, 'proofs/tenant-123', 'local');

    expect($storedPath)->toEndWith('.webp');
    Storage::disk('local')->assertExists($storedPath);
});

it('passes through pdf documents untouched without image processing', function (): void {
    $file = UploadedFile::fake()->create('bank_statement.pdf', 100, 'application/pdf');

    $storedPath = $this->optimizer->storeAndOptimize($file, 'proofs/tenant-123', 'local');

    expect($storedPath)->toEndWith('.pdf');
    Storage::disk('local')->assertExists($storedPath);
});

it('downscales excessively wide images to maximum 1920px width', function (): void {
    $file = UploadedFile::fake()->image('huge_photo.jpg', 2400, 1600);

    $storedPath = $this->optimizer->storeAndOptimize($file, 'proofs/tenant-123', 'local');

    expect($storedPath)->toEndWith('.webp');
    Storage::disk('local')->assertExists($storedPath);

    $contents = Storage::disk('local')->get($storedPath);
    $gdImage = imagecreatefromstring($contents);
    expect($gdImage)->not->toBeFalse();
    if ($gdImage !== false) {
        expect(imagesx($gdImage))->toBeLessThanOrEqual(1920);
        imagedestroy($gdImage);
    }
});
