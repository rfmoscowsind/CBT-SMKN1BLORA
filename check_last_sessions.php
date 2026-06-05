<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sessions = DB::table('sesi_ujians')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

foreach ($sessions as $s) {
    echo "Session ID: {$s->id}, User ID: {$s->user_id}, Status: {$s->status}, Updated At: {$s->updated_at}\n";
    $stats = DB::table('sesi_ujian_soals as ss')
        ->leftJoin('jawaban_siswas as js', function($join) {
            $join->on('js.bank_soal_id', '=', 'ss.bank_soal_id')
                 ->on('js.sesi_ujian_id', '=', 'ss.sesi_ujian_id');
        })
        ->where('ss.sesi_ujian_id', $s->id)
        ->select('js.skor', 'js.opsi_jawaban_id', 'js.jawaban_essay')
        ->get();

    $benar = 0;
    $salah = 0;
    $kosong = 0;

    foreach ($stats as $item) {
        if (is_null($item->opsi_jawaban_id) && is_null($item->jawaban_essay)) {
            $kosong++;
        } else {
            if (($item->skor ?? 0) > 0) {
                $benar++;
            } else {
                $salah++;
            }
        }
    }
    echo "  Stats -> Benar: {$benar}, Salah: {$salah}, Kosong: {$kosong}, Total: " . count($stats) . "\n";
}
