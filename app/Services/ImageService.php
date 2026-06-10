<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageService
{
    private const MAX_PIXELS = 6_000_000;

    public function webp(UploadedFile $file): string
    {
        $size = @getimagesize($file->getRealPath());
        if (! $size) {
            $this->fail('Gambar tidak valid atau format tidak didukung. Gunakan JPG, PNG, GIF, WebP, BMP, atau AVIF.');
        }

        [$width, $height] = $size;
        if ($width * $height > self::MAX_PIXELS) {
            $this->fail('Resolusi gambar terlalu besar. Maksimal 6 megapiksel.');
        }

        $src = imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $src) {
            $this->fail('Gambar tidak dapat dibaca. Coba unggah ulang sebagai JPG atau PNG.');
        }

        $name = Str::uuid().'.webp';
        ob_start();
        imagewebp($src, null, 82);
        $webp = ob_get_clean();
        imagedestroy($src);

        if (! is_string($webp) || $webp === '') {
            $this->fail('Gagal mengonversi gambar.');
        }

        $path = 'soal-images/'.$name;
        $disk = config('filesystems.default', 'local');

        if ($disk === 's3') {
            $stored = Storage::disk('s3')->put($path, $webp, [
                'visibility' => 'public',
                'ContentType' => 'image/webp',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            if (! $stored) {
                $this->fail('Gambar gagal diunggah ke media server.');
            }

            return self::displayUrl($path);
        }

        $stored = Storage::disk('public')->put($path, $webp, [
            'visibility' => 'public',
        ]);

        if (! $stored) {
            $this->fail('Gambar gagal disimpan.');
        }

        return '/storage/'.$path;
    }

    public static function displayUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, 'soal-images/')) {
            return '/media/'.$url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && str_contains($path, '/soal-images/')) {
            return '/media/soal-images/'.basename($path);
        }

        if (is_string($path) && str_starts_with($path, '/storage/soal-images/')) {
            return $path;
        }

        return $url;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['gambar' => [$message]]);
    }
}
