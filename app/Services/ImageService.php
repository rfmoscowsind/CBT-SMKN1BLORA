<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageService
{
    private const MAX_PIXELS = 6_000_000;

    public function webp(UploadedFile $file): string
    {
        $size = @getimagesize($file->getRealPath());
        abort_unless($size, 422, 'Gambar tidak valid.');

        [$width, $height] = $size;
        abort_if($width * $height > self::MAX_PIXELS, 422, 'Resolusi gambar terlalu besar.');

        $dir = storage_path('app/public/soal-images');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $src = imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($src, 422, 'Gambar tidak valid.');

        $name = Str::uuid().'.webp';
        imagewebp($src, $dir.'/'.$name, 82);
        imagedestroy($src);

        return '/storage/soal-images/'.$name;
    }
}
