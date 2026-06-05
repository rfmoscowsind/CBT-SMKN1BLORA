<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Konversi audit_logs ke PostgreSQL range partitioning by created_at (bulan).
 * Dilakukan secara non-destructive: buat tabel baru, copy data, rename.
 *
 * CATATAN: Jalankan saat traffic rendah. Estimasi waktu: < 1 detik untuk data kosong.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Hanya jalankan jika tabel belum dipartisi
        $isPartitioned = DB::select("
            SELECT 1 FROM pg_partitioned_table pt
            JOIN pg_class c ON c.oid = pt.partrelid
            WHERE c.relname = 'audit_logs'
        ");
        if (!empty($isPartitioned)) {
            return; // Sudah dipartisi
        }

        DB::unprepared("
            -- 1. Rename tabel lama
            ALTER TABLE audit_logs RENAME TO audit_logs_old;

            -- 2. Buat tabel partisi baru
            CREATE TABLE audit_logs (
                id              bigserial,
                sesi_ujian_id   bigint REFERENCES sesi_ujians(id) ON DELETE CASCADE,
                user_id         bigint REFERENCES users(id) ON DELETE SET NULL,
                action          varchar(255) NOT NULL,
                bank_soal_id    bigint REFERENCES bank_soals(id) ON DELETE NO ACTION,
                payload         json,
                ip_address      varchar(45),
                created_at      timestamp(0) without time zone,
                updated_at      timestamp(0) without time zone
            ) PARTITION BY RANGE (created_at);

            -- 3. Buat partisi per bulan (6 bulan ke depan)
            CREATE TABLE audit_logs_2026_01 PARTITION OF audit_logs FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
            CREATE TABLE audit_logs_2026_02 PARTITION OF audit_logs FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
            CREATE TABLE audit_logs_2026_03 PARTITION OF audit_logs FOR VALUES FROM ('2026-03-01') TO ('2026-04-01');
            CREATE TABLE audit_logs_2026_04 PARTITION OF audit_logs FOR VALUES FROM ('2026-04-01') TO ('2026-05-01');
            CREATE TABLE audit_logs_2026_05 PARTITION OF audit_logs FOR VALUES FROM ('2026-05-01') TO ('2026-06-01');
            CREATE TABLE audit_logs_2026_06 PARTITION OF audit_logs FOR VALUES FROM ('2026-06-01') TO ('2026-07-01');
            CREATE TABLE audit_logs_2026_07 PARTITION OF audit_logs FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');
            CREATE TABLE audit_logs_2026_08 PARTITION OF audit_logs FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');
            CREATE TABLE audit_logs_2026_12 PARTITION OF audit_logs FOR VALUES FROM ('2026-09-01') TO ('2027-01-01');

            -- 4. Copy data lama
            INSERT INTO audit_logs SELECT * FROM audit_logs_old;

            -- 5. Hapus tabel lama
            DROP TABLE audit_logs_old;

            -- 6. Index di semua partisi (auto-inherited di PG 11+)
            CREATE INDEX idx_audit_user_id   ON audit_logs (user_id);
            CREATE INDEX idx_audit_sesi_id   ON audit_logs (sesi_ujian_id);
            CREATE INDEX idx_audit_created   ON audit_logs (created_at);

            -- 7. Primary key sequence
            CREATE SEQUENCE IF NOT EXISTS audit_logs_id_seq OWNED BY audit_logs.id;
            ALTER TABLE audit_logs ALTER COLUMN id SET DEFAULT nextval('audit_logs_id_seq');
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Revert: konversi balik ke tabel biasa
        DB::unprepared("
            ALTER TABLE audit_logs RENAME TO audit_logs_partitioned;
            CREATE TABLE audit_logs AS SELECT * FROM audit_logs_partitioned;
            DROP TABLE audit_logs_partitioned CASCADE;
        ");
    }
};
