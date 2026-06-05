<?php
namespace App\Services;
use Illuminate\Http\UploadedFile;use Illuminate\Support\Str;
class ImageService {public function webp(UploadedFile $file):string{$dir=storage_path('app/public/soal-images');if(!is_dir($dir))mkdir($dir,0775,true);$src=imagecreatefromstring(file_get_contents($file->getRealPath()));abort_unless($src,422,'Gambar tidak valid.');$name=Str::uuid().'.webp';imagewebp($src,$dir.'/'.$name,82);imagedestroy($src);return '/storage/soal-images/'.$name;}}