<?php

namespace App\Http\Controllers;

use App\Services\ImageService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class QuestionBankManagementController extends Controller
{
    public function __construct(private ImageService $images)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorizeQuestions();

        return $this->ok([
            'mapels' => DB::table('mata_pelajarans')->orderBy('nama_mapel')->get(['id', 'kode_mapel', 'nama_mapel']),
            'packages' => $this->packageQuery()
                ->leftJoin('bank_soals as b', 'b.paket_soal_id', '=', 'p.id')
                ->groupBy('p.id', 'p.judul', 'p.status', 'p.mata_pelajaran_id', 'mp.nama_mapel', 'u.name', 'p.kode_paket', 'p.jumlah_pg', 'p.has_isian')
                ->orderByDesc('p.id')
                ->get([
                    'p.id',
                    'p.judul',
                    'p.status',
                    'p.mata_pelajaran_id',
                    'mp.nama_mapel',
                    'u.name as guru',
                    'p.kode_paket',
                    'p.jumlah_pg',
                    'p.has_isian',
                    DB::raw('count(b.id) as jumlah_soal'),
                ]),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $package = $this->ownedPackage($id);
        $questions = DB::table('bank_soals')
            ->where('paket_soal_id', $id)
            ->orderBy('urutan')
            ->get();

        $soalIds = $questions->pluck('id')->all();

        $options = collect();
        if (!empty($soalIds)) {
            $options = DB::table('opsi_jawabans')
                ->whereIn('bank_soal_id', $soalIds)
                ->orderBy('kode')
                ->get(['id', 'bank_soal_id', 'kode', 'teks_opsi', 'is_benar'])
                ->groupBy('bank_soal_id');
        }

        $package->soal = $questions->map(function ($question) use ($options) {
            $question->opsi = $options->get($question->id, collect());
            $question->gambar_url = ImageService::displayUrl($question->gambar_url);
            return $question;
        });

        return $this->ok(['package' => $package]);
    }

    public function storePackage(Request $request): JsonResponse
    {
        $this->authorizeQuestions();
        $validated = $this->validateJson($request, [
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajarans,id'],
            'kode_paket' => ['required', 'string', 'max:50'],
            'jumlah_pg' => ['required', 'integer', 'min:0'],
            'has_isian' => ['required', 'boolean'],
        ]);

        $mapelName = DB::table('mata_pelajarans')
            ->where('id', $validated['mata_pelajaran_id'])
            ->value('nama_mapel') ?? '';

        $judul = strtoupper($validated['kode_paket']) . ' - ' . $mapelName;

        $id = DB::transaction(function () use ($validated, $judul) {
            $packageId = DB::table('paket_soals')->insertGetId([
                'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
                'kode_paket' => $validated['kode_paket'],
                'jumlah_pg' => $validated['jumlah_pg'],
                'has_isian' => $validated['has_isian'],
                'judul' => $judul,
                'pembuat_user_id' => Auth::id(),
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $limitPg = (int) $validated['jumlah_pg'];
            for ($i = 1; $i <= $limitPg; $i++) {
                $questionId = DB::table('bank_soals')->insertGetId([
                    'paket_soal_id' => $packageId,
                    'urutan' => $i,
                    'tipe_soal' => 'PG',
                    'pertanyaan' => 'Pertanyaan soal nomor ' . $i,
                    'gambar_url' => null,
                    'bobot_nilai' => 1.0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach (['A', 'B', 'C', 'D', 'E'] as $code) {
                    DB::table('opsi_jawabans')->insert([
                        'bank_soal_id' => $questionId,
                        'kode' => $code,
                        'teks_opsi' => '',
                        'is_benar' => $code === 'A',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $packageId;
        });

        return $this->ok(['id' => $id], 201);
    }

    public function updatePackage(Request $request, int $id): JsonResponse
    {
        $this->ownedPackage($id);
        $this->ensureEditable($id);
        $validated = $this->validateJson($request, [
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajarans,id'],
            'kode_paket' => ['required', 'string', 'max:50'],
            'jumlah_pg' => ['required', 'integer', 'min:0'],
            'has_isian' => ['required', 'boolean'],
        ]);

        $mapelName = DB::table('mata_pelajarans')
            ->where('id', $validated['mata_pelajaran_id'])
            ->value('nama_mapel') ?? '';

        $judul = strtoupper($validated['kode_paket']) . ' - ' . $mapelName;

        DB::table('paket_soals')->where('id', $id)->update([
            'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
            'kode_paket' => $validated['kode_paket'],
            'jumlah_pg' => $validated['jumlah_pg'],
            'has_isian' => $validated['has_isian'],
            'judul' => $judul,
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id]);
    }

    public function destroyPackage(int $id): JsonResponse
    {
        $this->ownedPackage($id);
        $this->ensureEditable($id);
        abort_if(DB::table('master_ujians')->where('paket_soal_id', $id)->exists(), 422, 'Paket sudah digunakan oleh master ujian.');
        DB::table('paket_soals')->where('id', $id)->delete();

        return $this->ok();
    }

    public function storeQuestion(Request $request, int $packageId): JsonResponse
    {
        $this->ownedPackage($packageId);
        $this->ensureEditable($packageId);
        $data = $this->questionData($request);
        $id = DB::transaction(function () use ($request, $packageId, $data) {
            $id = DB::table('bank_soals')->insertGetId([
                'paket_soal_id' => $packageId,
                'urutan' => (int) DB::table('bank_soals')->where('paket_soal_id', $packageId)->max('urutan') + 1,
                'tipe_soal' => $data['tipe_soal'],
                'pertanyaan' => $data['pertanyaan'],
                'gambar_url' => $request->hasFile('gambar') ? $this->images->webp($request->file('gambar')) : null,
                'bobot_nilai' => $data['bobot_nilai'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->replaceOptions($id, $data);
            $this->markDraft($packageId);

            return $id;
        });

        return $this->ok(['id' => $id], 201);
    }

    public function updateQuestion(Request $request, int $packageId, int $id): JsonResponse
    {
        $this->ownedPackage($packageId);
        $this->ensureEditable($packageId);
        $question = DB::table('bank_soals')->where(['id' => $id, 'paket_soal_id' => $packageId])->first();
        abort_unless($question, 404);
        $data = $this->questionData($request);
        DB::transaction(function () use ($request, $packageId, $id, $question, $data) {
            DB::table('bank_soals')->where('id', $id)->update([
                'tipe_soal' => $data['tipe_soal'],
                'pertanyaan' => $data['pertanyaan'],
                'gambar_url' => $request->hasFile('gambar') ? $this->images->webp($request->file('gambar')) : $question->gambar_url,
                'bobot_nilai' => $data['bobot_nilai'],
                'updated_at' => now(),
            ]);
            $this->replaceOptions($id, $data);
            $this->markDraft($packageId);
        });

        return $this->ok(['id' => $id]);
    }

    public function destroyQuestion(int $packageId, int $id): JsonResponse
    {
        $this->ownedPackage($packageId);
        $this->ensureEditable($packageId);
        DB::table('bank_soals')->where(['id' => $id, 'paket_soal_id' => $packageId])->delete();
        $this->markDraft($packageId);

        return $this->ok();
    }

    public function ready(int $id): JsonResponse
    {
        $this->ownedPackage($id);
        $this->ensureEditable($id);
        $questions = DB::table('bank_soals')->where('paket_soal_id', $id)->get();
        abort_if($questions->isEmpty(), 422, 'Paket harus memiliki minimal satu soal.');

        $soalIds = $questions->pluck('id')->all();
        $options = DB::table('opsi_jawabans')
            ->whereIn('bank_soal_id', $soalIds)
            ->get(['id', 'bank_soal_id', 'teks_opsi', 'is_benar'])
            ->groupBy('bank_soal_id');

        foreach ($questions as $question) {
            abort_if(trim((string) $question->pertanyaan) === '', 422, 'Pertanyaan tidak boleh kosong.');
            abort_if(str_starts_with((string) $question->pertanyaan, 'Pertanyaan soal nomor'), 422, 'Masih ada soal placeholder.');
            abort_if((float) $question->bobot_nilai <= 0, 422, 'Bobot soal harus lebih dari 0.');

            if ($question->tipe_soal === 'PG') {
                $opts = $options->get($question->id, collect());
                abort_if($opts->count() < 2, 422, 'Soal PG minimal memiliki dua opsi.');
                abort_if($opts->contains(fn ($opt) => trim((string) $opt->teks_opsi) === ''), 422, 'Teks opsi tidak boleh kosong.');
                abort_unless($opts->where('is_benar', true)->count() === 1, 422, 'Soal PG harus memiliki tepat satu kunci.');
            }
        }

        DB::table('paket_soals')->where('id', $id)->update(['status' => 'ready', 'updated_at' => now()]);

        return $this->ok();
    }

    public function import(Request $request, int $id): JsonResponse
    {
        $this->ownedPackage($id);
        $this->ensureEditable($id);
        $this->validateJson($request, ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048']]);
        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray();
        abort_if(count($rows) > 2001, 422, 'Maksimal 2000 baris per import.');
        $imported = 0;
        $errors = [];
        foreach (array_slice($rows, 1) as $index => $row) {
            try {
                $type = strtoupper(trim((string) ($row[0] ?? '')));
                $question = trim((string) ($row[1] ?? ''));
                if (! in_array($type, ['PG', 'ISIAN'], true) || $question === '') {
                    throw new \RuntimeException('Tipe dan pertanyaan wajib valid.');
                }
                DB::transaction(function () use ($id, $row, $type, $question) {
                    $questionId = DB::table('bank_soals')->insertGetId([
                        'paket_soal_id' => $id,
                        'urutan' => (int) DB::table('bank_soals')->where('paket_soal_id', $id)->max('urutan') + 1,
                        'tipe_soal' => $type,
                        'pertanyaan' => $question,
                        'bobot_nilai' => $row[2] ?: 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($type === 'PG') {
                        foreach (['A', 'B', 'C', 'D', 'E'] as $offset => $code) {
                            if (! empty($row[3 + $offset])) {
                                DB::table('opsi_jawabans')->insert([
                                    'bank_soal_id' => $questionId,
                                    'kode' => $code,
                                    'teks_opsi' => $row[3 + $offset],
                                    'is_benar' => strtoupper((string) ($row[8] ?? '')) === $code,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                });
                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = ['baris' => $index + 2, 'pesan' => $exception->getMessage()];
            }
        }
        $this->markDraft($id);

        return $this->ok(['imported' => $imported, 'failed' => count($errors), 'errors' => $errors]);
    }

    private function questionData(Request $request): array
    {
        $data = $this->validateJson($request, [
            'tipe_soal' => ['required', 'in:PG,ISIAN'],
            'pertanyaan' => ['required', 'string'],
            'bobot_nilai' => ['required', 'numeric', 'min:0'],
            'gambar' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/x-ms-bmp,image/avif',
            ],
            'opsi' => ['nullable', 'array'],
            'opsi.*.kode' => ['required_with:opsi', 'in:A,B,C,D,E'],
            'opsi.*.teks_opsi' => ['required_with:opsi', 'string'],
            'opsi.*.is_benar' => ['nullable', 'boolean'],
        ]);
        $options = $data['opsi'] ?? [];
        if ($data['tipe_soal'] === 'PG') {
            if (count($options) < 2) {
                $this->fail('Soal PG minimal memiliki dua opsi.');
            }

            if (collect($options)->where('is_benar', true)->count() !== 1) {
                $this->fail('Pilih tepat satu kunci jawaban.');
            }
        }

        return $data;
    }

    private function replaceOptions(int $questionId, array $data): void
    {
        DB::table('opsi_jawabans')->where('bank_soal_id', $questionId)->delete();
        if ($data['tipe_soal'] === 'PG') {
            foreach ($data['opsi'] as $option) {
                DB::table('opsi_jawabans')->insert([
                    'bank_soal_id' => $questionId,
                    'kode' => $option['kode'],
                    'teks_opsi' => $option['teks_opsi'],
                    'is_benar' => $option['is_benar'] ?? false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function packageQuery()
    {
        $query = DB::table('paket_soals as p')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->join('users as u', 'u.id', '=', 'p.pembuat_user_id');
        if (! in_array(Auth::user()?->role, ['SuperAdmin', 'Admin'], true)) {
            $query->where('p.pembuat_user_id', Auth::id());
        }

        return $query;
    }

    private function ownedPackage(int $id): object
    {
        $this->authorizeQuestions();
        $package = $this->packageQuery()->where('p.id', $id)->first([
            'p.id',
            'p.judul',
            'p.status',
            'p.mata_pelajaran_id',
            'mp.nama_mapel',
            'u.name as guru',
            'p.kode_paket',
            'p.jumlah_pg',
            'p.has_isian',
        ]);
        abort_unless($package, 404);

        return $package;
    }

    private function ensureEditable(int $id): void
    {
        $hasStudentSession = DB::table('master_ujians as m')
                ->join('jadwal_ujians as j', 'j.master_ujian_id', '=', 'm.id')
                ->join('sesi_ujians as s', 's.jadwal_ujian_id', '=', 'j.id')
                ->where('m.paket_soal_id', $id)
                ->exists();

        if ($hasStudentSession) {
            $this->fail('Paket tidak dapat direvisi karena sudah memiliki sesi ujian siswa.');
        }
    }

    private function markDraft(int $id): void
    {
        DB::table('paket_soals')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
    }

    private function authorizeQuestions(): void
    {
        // FIX: Hapus ->fresh() — tidak perlu reload User dari DB setiap request.
        // Gunakan ->can() yang memanfaatkan Spatie cache.
        abort_unless(
            Auth::user()?->can('manage-questions') || in_array(Auth::user()?->role, ['SuperAdmin', 'Admin'], true),
            403
        );
    }

    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Data paket soal belum valid.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    private function fail(string $message, int $status = 422): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
        ], $status));
    }

    private function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
