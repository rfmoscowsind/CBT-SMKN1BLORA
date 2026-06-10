<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleBatchService
{
    /**
     * Expand grup batch menjadi daftar jadwal yang akan dibuat.
     * Setiap grup bisa punya banyak rombel; setiap rombel menjadi satu jadwal.
     *
     * @param  array $header   Validated header batch
     * @param  array $groups   Validated array of group definitions
     * @return array{items: array, errors: array}
     */
    public function expand(array $header, array $groups): array
    {
        $errors = [];
        $items  = [];

        $batchToken  = $header['gunakan_token'] ? Str::upper(trim($header['token'])) : null;
        $defaultStart = $header['default_waktu_mulai'];
        $defaultEnd   = $header['default_waktu_selesai'];
        $defaultDurasi = (int) $header['default_durasi_menit'];
        $defaultAcakSoal = (bool) $header['default_acak_soal'];
        $defaultAcakOpsi = (bool) $header['default_acak_opsi'];
        $defaultTampilNilai = (bool) $header['default_tampilkan_nilai_akhir'];
        $defaultVisibilitas = $header['default_hasil_visibilitas'];
        $defaultRilis       = $header['default_tanggal_rilis_hasil'] ?? null;

        // Preload seluruh kelas_aktifs yang relevan sekaligus (1 query)
        $allClasses = DB::table('kelas_aktifs as k')
            ->join('jurusans as j', 'j.id', '=', 'k.jurusan_id')
            ->join('rombels as r', 'r.id', '=', 'k.rombel_id')
            ->get(['k.id', 'k.nama_kelas', 'k.tingkat', 'k.jurusan_id', 'k.rombel_id', 'j.kode_jurusan', 'r.nama_rombel']);

        // Preload paket soal (1 query)
        $paketIds = array_unique(array_column($groups, 'paket_soal_id'));
        $pakets   = DB::table('paket_soals as p')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->whereIn('p.id', $paketIds)
            ->get(['p.id', 'p.judul', 'p.status', 'mp.nama_mapel'])
            ->keyBy('id');

        $tingkat = DB::table('tingkats')
            ->where('id', (int) $header['tingkat'])
            ->value('nama_tingkat');

        if ($tingkat === null) {
            return ['items' => [], 'errors' => ['Tingkat yang dipilih tidak ditemukan.']];
        }

        $tingkat = (int) $tingkat;

        // Kumpulkan semua kelas_aktif_id yang akan terlibat agar bisa batch-check bentrok
        $seen = []; // key: "{kelas_aktif_id}|{start}|{end}" untuk dedup dalam batch

        foreach ($groups as $gi => $group) {
            $groupLabel = 'Grup ' . ($gi + 1);

            $paketId = (int) $group['paket_soal_id'];
            $paket   = $pakets->get($paketId);

            if (! $paket) {
                $errors[] = "{$groupLabel}: Paket soal ID {$paketId} tidak ditemukan.";
                continue;
            }

            if ($paket->status !== 'ready') {
                $errors[] = "{$groupLabel}: Paket soal '{$paket->judul}' belum berstatus ready.";
                continue;
            }

            // Tentukan waktu dan opsi: override atau pakai default
            $useOverride = ! empty($group['override']);
            $mulai   = $useOverride && ! empty($group['waktu_mulai'])   ? $group['waktu_mulai']   : $defaultStart;
            $selesai = $useOverride && ! empty($group['waktu_selesai']) ? $group['waktu_selesai'] : $defaultEnd;
            $durasi  = $useOverride && isset($group['durasi_menit'])    ? (int) $group['durasi_menit'] : $defaultDurasi;

            $acakSoal   = $useOverride && isset($group['acak_soal'])              ? (bool) $group['acak_soal']              : $defaultAcakSoal;
            $acakOpsi   = $useOverride && isset($group['acak_opsi'])              ? (bool) $group['acak_opsi']              : $defaultAcakOpsi;
            $tampilNilai = $useOverride && isset($group['tampilkan_nilai_akhir']) ? (bool) $group['tampilkan_nilai_akhir']  : $defaultTampilNilai;
            $visibilitas = $useOverride && ! empty($group['hasil_visibilitas'])   ? $group['hasil_visibilitas']             : $defaultVisibilitas;
            $rilis       = $useOverride && isset($group['tanggal_rilis_hasil'])   ? $group['tanggal_rilis_hasil']           : $defaultRilis;

            // Validasi durasi vs rentang waktu
            $startCarbon = Carbon::parse($mulai, 'Asia/Jakarta');
            $endCarbon   = Carbon::parse($selesai, 'Asia/Jakarta');
            $rentangMenit = $startCarbon->diffInMinutes($endCarbon);
            if ($durasi > $rentangMenit) {
                $errors[] = "{$groupLabel}: Durasi {$durasi} menit melebihi rentang waktu {$rentangMenit} menit ({$mulai}–{$selesai}).";
                continue;
            }

            // Validasi scheduled harus punya rilis
            if ($visibilitas === 'scheduled' && empty($rilis)) {
                $errors[] = "{$groupLabel}: Visibilitas hasil 'scheduled' wajib memiliki tanggal rilis.";
                continue;
            }

            $jurusanId = (int) $group['jurusan_id'];
            $rombelIds = array_map('intval', (array) ($group['rombel_ids'] ?? []));

            if (empty($rombelIds)) {
                $errors[] = "{$groupLabel}: Minimal satu rombel harus dipilih.";
                continue;
            }

            foreach ($rombelIds as $rombelId) {
                // Cari kelas yang cocok
                $kelas = $allClasses->first(function ($k) use ($tingkat, $jurusanId, $rombelId) {
                    return (int) $k->tingkat === $tingkat
                        && (int) $k->jurusan_id === $jurusanId
                        && (int) $k->rombel_id === $rombelId;
                });

                if (! $kelas) {
                    $j = DB::table('jurusans')->find($jurusanId);
                    $r = DB::table('rombels')->find($rombelId);
                    $errors[] = "{$groupLabel}: Kelas untuk tingkat {$tingkat}, jurusan '{$j->kode_jurusan}', rombel '{$r->nama_rombel}' tidak ditemukan.";
                    continue;
                }

                // Dedup dalam batch yang sama
                $deduKey = "{$kelas->id}|{$mulai}|{$selesai}";
                if (isset($seen[$deduKey])) {
                    $errors[] = "{$groupLabel}: Kelas {$kelas->nama_kelas} muncul duplikat dalam batch pada waktu yang sama.";
                    continue;
                }
                $seen[$deduKey] = true;

                $items[] = [
                    'kelas_aktif_id'        => (int) $kelas->id,
                    'nama_kelas'            => $kelas->nama_kelas,
                    'paket_soal_id'         => $paketId,
                    'paket_judul'           => $paket->judul,
                    'nama_mapel'            => $paket->nama_mapel,
                    'waktu_mulai'           => $mulai,
                    'waktu_selesai'         => $selesai,
                    'durasi_menit'          => $durasi,
                    'acak_soal'             => $acakSoal,
                    'acak_opsi'             => $acakOpsi,
                    'tampilkan_nilai_akhir' => $tampilNilai,
                    'hasil_visibilitas'     => $visibilitas,
                    'tanggal_rilis_hasil'   => $rilis,
                    'token'                 => $batchToken,
                    'gunakan_token'         => ! empty($batchToken),
                    'override'              => $useOverride,
                ];
            }
        }

        return ['items' => $items, 'errors' => $errors];
    }

    /**
     * Cek apakah ada jadwal yang bentrok untuk kelas tertentu.
     *
     * @param  array $items  Hasil expand (array of item)
     * @return array         Array error bentrok
     */
    public function checkConflicts(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $errors     = [];
        $classIds   = array_unique(array_column($items, 'kelas_aktif_id'));

        // Ambil semua jadwal aktif yang ada untuk kelas-kelas tersebut (1 query)
        $existing = DB::table('jadwal_ujians as j')
            ->join('jadwal_ujian_kelas as jk', 'jk.jadwal_ujian_id', '=', 'j.id')
            ->whereIn('jk.kelas_aktif_id', $classIds)
            ->whereNull('j.diarsipkan_at')
            ->get(['j.id', 'j.waktu_mulai', 'j.waktu_selesai', 'jk.kelas_aktif_id']);

        foreach ($items as $item) {
            $newStart = Carbon::parse($item['waktu_mulai'], 'Asia/Jakarta')->utc();
            $newEnd   = Carbon::parse($item['waktu_selesai'], 'Asia/Jakarta')->utc();

            foreach ($existing as $ex) {
                if ((int) $ex->kelas_aktif_id !== (int) $item['kelas_aktif_id']) {
                    continue;
                }

                $exStart = Carbon::parse($ex->waktu_mulai);
                $exEnd   = Carbon::parse($ex->waktu_selesai);

                // Overlap: mulai < selesai_lain DAN selesai > mulai_lain
                if ($newStart->lt($exEnd) && $newEnd->gt($exStart)) {
                    $errors[] = "Kelas {$item['nama_kelas']}: Jadwal bentrok dengan jadwal ID {$ex->id} ("
                        . $exStart->timezone('Asia/Jakarta')->format('d/m/Y H:i')
                        . '–'
                        . $exEnd->timezone('Asia/Jakarta')->format('H:i')
                        . ').'
                    ;
                }
            }
        }

        return $errors;
    }

    /**
     * Simpan batch: auto create/reuse master ujian, lalu insert jadwal per kelas.
     *
     * @param  array $items  Hasil expand yang sudah divalidasi
     * @return array         Daftar jadwal yang dibuat [{id, kelas, paket, token, waktu_mulai, waktu_selesai}]
     */
    public function store(array $items): array
    {
        $created = [];

        DB::transaction(function () use ($items, &$created) {
            foreach ($items as $item) {
                // Auto create/reuse master ujian berdasarkan kombinasi unik
                $masterId = $this->findOrCreateMaster(
                    $item['paket_soal_id'],
                    $item['paket_judul'],
                    (bool) $item['acak_soal'],
                    (bool) $item['acak_opsi'],
                    (bool) $item['tampilkan_nilai_akhir'],
                    $item['hasil_visibilitas'],
                    $item['tanggal_rilis_hasil'] ?? null
                );

                $startUtc = Carbon::parse($item['waktu_mulai'], 'Asia/Jakarta')->utc();
                $endUtc   = Carbon::parse($item['waktu_selesai'], 'Asia/Jakarta')->utc();

                $jadwalId = DB::table('jadwal_ujians')->insertGetId([
                    'master_ujian_id' => $masterId,
                    'waktu_mulai'     => $startUtc,
                    'waktu_selesai'   => $endUtc,
                    'durasi_menit'    => (int) $item['durasi_menit'],
                    'gunakan_token'   => (bool) $item['gunakan_token'],
                    'token'           => $item['token'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                DB::table('jadwal_ujian_kelas')->insert([
                    'jadwal_ujian_id' => $jadwalId,
                    'kelas_aktif_id'  => (int) $item['kelas_aktif_id'],
                ]);

                $created[] = [
                    'id'           => $jadwalId,
                    'kelas'        => $item['nama_kelas'],
                    'paket'        => $item['paket_judul'],
                    'mapel'        => $item['nama_mapel'],
                    'waktu_mulai'  => Carbon::parse($item['waktu_mulai'])->format('Y-m-d H:i'),
                    'waktu_selesai'=> Carbon::parse($item['waktu_selesai'])->format('H:i'),
                    'token'        => $item['token'],
                    'master_id'    => $masterId,
                ];
            }
        });

        return $created;
    }

    /**
     * Cari master ujian yang cocok, atau buat baru jika tidak ada.
     */
    private function findOrCreateMaster(
        int    $paketSoalId,
        string $judul,
        bool   $acakSoal,
        bool   $acakOpsi,
        bool   $tampilkanNilai,
        string $hasilVisibilitas,
        ?string $tanggalRilisHasil
    ): int {
        $rilisUtc = $tanggalRilisHasil
            ? Carbon::parse($tanggalRilisHasil, 'Asia/Jakarta')->utc()->toDateTimeString()
            : null;

        // Cari master yang identik
        $existing = DB::table('master_ujians')
            ->where('paket_soal_id',         $paketSoalId)
            ->where('acak_soal',              $acakSoal)
            ->where('acak_opsi',              $acakOpsi)
            ->where('tampilkan_nilai_akhir',  $tampilkanNilai)
            ->where('hasil_visibilitas',      $hasilVisibilitas)
            ->where(function ($q) use ($rilisUtc) {
                if ($rilisUtc === null) {
                    $q->whereNull('tanggal_rilis_hasil');
                } else {
                    $q->where('tanggal_rilis_hasil', $rilisUtc);
                }
            })
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('master_ujians')->insertGetId([
            'judul'                 => $judul,
            'paket_soal_id'         => $paketSoalId,
            'acak_soal'             => $acakSoal,
            'acak_opsi'             => $acakOpsi,
            'tampilkan_nilai_akhir' => $tampilkanNilai,
            'hasil_visibilitas'     => $hasilVisibilitas,
            'tanggal_rilis_hasil'   => $rilisUtc,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }
}
