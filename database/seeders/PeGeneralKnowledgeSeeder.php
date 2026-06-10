<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeGeneralKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $package = DB::table('paket_soals')
            ->where('judul', 'PE - Pengetahuan Umum')
            ->first();

        if (! $package) {
            throw new \RuntimeException('Paket "PE - Pengetahuan Umum" tidak ditemukan.');
        }

        $hasStudentSession = DB::table('master_ujians as m')
            ->join('jadwal_ujians as j', 'j.master_ujian_id', '=', 'm.id')
            ->join('sesi_ujians as s', 's.jadwal_ujian_id', '=', 'j.id')
            ->where('m.paket_soal_id', $package->id)
            ->exists();

        if ($hasStudentSession) {
            throw new \RuntimeException('Paket sudah memiliki sesi ujian siswa, soal tidak diganti.');
        }

        $questions = [
            [
                'question' => 'Dasar negara Republik Indonesia adalah ...',
                'options' => ['UUD 1945', 'Pancasila', 'Bhinneka Tunggal Ika', 'Garuda Pancasila', 'Sumpah Pemuda'],
                'answer' => 'B',
            ],
            [
                'question' => 'Semboyan Bhinneka Tunggal Ika memiliki arti ...',
                'options' => ['Bersatu kita teguh', 'Berbeda-beda tetapi tetap satu', 'Maju bersama bangsa', 'Keadilan bagi semua', 'Indonesia tanah airku'],
                'answer' => 'B',
            ],
            [
                'question' => 'Lembaga negara yang bertugas membuat undang-undang bersama presiden adalah ...',
                'options' => ['Mahkamah Agung', 'Dewan Perwakilan Rakyat', 'Komisi Yudisial', 'Badan Pemeriksa Keuangan', 'Mahkamah Konstitusi'],
                'answer' => 'B',
            ],
            [
                'question' => 'Mata uang resmi negara Indonesia adalah ...',
                'options' => ['Ringgit', 'Rupiah', 'Baht', 'Peso', 'Dolar'],
                'answer' => 'B',
            ],
            [
                'question' => 'Organisasi regional negara-negara Asia Tenggara adalah ...',
                'options' => ['OPEC', 'ASEAN', 'PBB', 'APEC', 'WHO'],
                'answer' => 'B',
            ],
            [
                'question' => 'Ibu kota negara Indonesia saat ini secara administratif adalah ...',
                'options' => ['Bandung', 'Surabaya', 'Jakarta', 'Yogyakarta', 'Semarang'],
                'answer' => 'C',
            ],
            [
                'question' => 'Salah satu contoh sikap disiplin di lingkungan sekolah adalah ...',
                'options' => ['Datang tepat waktu', 'Membuang sampah sembarangan', 'Menunda tugas', 'Mengabaikan tata tertib', 'Meninggalkan kelas tanpa izin'],
                'answer' => 'A',
            ],
            [
                'question' => 'Dalam keselamatan kerja, APD adalah singkatan dari ...',
                'options' => ['Alat Pengolah Data', 'Alat Pelindung Diri', 'Aplikasi Pelayanan Digital', 'Administrasi Peralatan Dasar', 'Arah Prosedur Darurat'],
                'answer' => 'B',
            ],
            [
                'question' => 'Contoh perilaku hemat energi di sekolah adalah ...',
                'options' => ['Menyalakan lampu saat ruangan terang', 'Membiarkan komputer menyala setelah digunakan', 'Mematikan lampu dan perangkat saat tidak dipakai', 'Membuka semua keran air', 'Menggunakan AC dengan pintu terbuka'],
                'answer' => 'C',
            ],
            [
                'question' => 'Sikap yang tepat saat menerima informasi dari internet adalah ...',
                'options' => ['Langsung membagikan semua informasi', 'Memeriksa kebenaran sumber informasi', 'Percaya pada judul yang menarik saja', 'Mengabaikan tanggal publikasi', 'Menyebarkan tanpa membaca isi'],
                'answer' => 'B',
            ],
            [
                'question' => 'Kemampuan bekerja sama, berkomunikasi, dan bertanggung jawab termasuk contoh ...',
                'options' => ['Hard skill', 'Soft skill', 'Modal usaha', 'Mesin produksi', 'Bahan baku'],
                'answer' => 'B',
            ],
            [
                'question' => 'Dalam dunia kerja, CV biasanya digunakan untuk ...',
                'options' => ['Mencatat pengeluaran harian', 'Melamar pekerjaan dengan menunjukkan riwayat diri', 'Membuat laporan produksi', 'Mengatur jadwal mesin', 'Menyimpan arsip nilai'],
                'answer' => 'B',
            ],
            [
                'question' => 'Kegiatan membeli barang dari luar negeri disebut ...',
                'options' => ['Ekspor', 'Impor', 'Distribusi', 'Produksi', 'Konsumsi'],
                'answer' => 'B',
            ],
            [
                'question' => 'Kegiatan menjual barang ke luar negeri disebut ...',
                'options' => ['Impor', 'Ekspor', 'Inflasi', 'Investasi', 'Konsumsi'],
                'answer' => 'B',
            ],
            [
                'question' => 'Wirausaha adalah orang yang ...',
                'options' => ['Hanya menunggu bantuan', 'Menciptakan peluang usaha dan berani mengambil risiko terukur', 'Tidak membutuhkan perencanaan', 'Selalu menghindari inovasi', 'Tidak bertanggung jawab pada usahanya'],
                'answer' => 'B',
            ],
            [
                'question' => 'Fungsi utama rambu keselamatan di tempat praktik adalah ...',
                'options' => ['Hiasan ruangan', 'Petunjuk agar pekerjaan lebih aman', 'Pengganti guru praktik', 'Tempat menempel jadwal', 'Alat promosi sekolah'],
                'answer' => 'B',
            ],
            [
                'question' => 'Gotong royong mencerminkan nilai Pancasila terutama sila ke ...',
                'options' => ['Satu', 'Dua', 'Tiga', 'Empat', 'Lima'],
                'answer' => 'C',
            ],
            [
                'question' => 'Sumber daya alam yang dapat diperbarui adalah ...',
                'options' => ['Minyak bumi', 'Batu bara', 'Gas alam', 'Tumbuhan', 'Emas'],
                'answer' => 'D',
            ],
            [
                'question' => 'Salah satu contoh etika berkomunikasi melalui pesan digital adalah ...',
                'options' => ['Menggunakan bahasa sopan dan jelas', 'Menulis dengan kata-kata kasar', 'Mengirim spam berulang', 'Membagikan data pribadi orang lain', 'Menyebarkan hoaks'],
                'answer' => 'A',
            ],
            [
                'question' => 'Jika terjadi kebakaran kecil di laboratorium atau bengkel, tindakan awal yang tepat adalah ...',
                'options' => ['Panik dan berlari tanpa arah', 'Mengabaikan api', 'Memberi tahu guru/petugas dan menggunakan APAR jika aman', 'Menyiram semua alat listrik dengan air', 'Merekam kejadian terlebih dahulu'],
                'answer' => 'C',
            ],
        ];

        DB::transaction(function () use ($package, $questions) {
            DB::table('bank_soals')
                ->where('paket_soal_id', $package->id)
                ->delete();

            foreach ($questions as $index => $item) {
                $questionId = DB::table('bank_soals')->insertGetId([
                    'paket_soal_id' => $package->id,
                    'urutan' => $index + 1,
                    'tipe_soal' => 'PG',
                    'pertanyaan' => $item['question'],
                    'gambar_url' => null,
                    'bobot_nilai' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach (['A', 'B', 'C', 'D', 'E'] as $offset => $code) {
                    DB::table('opsi_jawabans')->insert([
                        'bank_soal_id' => $questionId,
                        'kode' => $code,
                        'teks_opsi' => $item['options'][$offset],
                        'is_benar' => $item['answer'] === $code,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('paket_soals')->where('id', $package->id)->update([
                'jumlah_pg' => 20,
                'has_isian' => false,
                'status' => 'ready',
                'updated_at' => now(),
            ]);
        });
    }
}
