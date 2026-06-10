<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
        h2 { margin-bottom: 4px; }
        p { margin: 3px 0; }
        .meta { margin: 14px 0; padding: 10px; background: #f1f5f9; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th { background: #e2e8f0; }
    </style>
</head>
<body>
    <h2>Hasil Ujian CBT SMKN 1 Blora</h2>
    <p><strong>{{ $schedule->nama_mapel }}</strong> - {{ $schedule->judul }}</p>
    <p>Kelas: {{ $class->nama_kelas }} | Jurusan: {{ $class->nama_jurusan }}</p>
    <p>Jadwal: {{ $schedule->waktu_mulai }} - {{ $schedule->waktu_selesai }} WIB</p>
    <div class="meta">
        Target: {{ $stats['total_target'] }} |
        Sudah masuk: {{ $stats['sudah_masuk'] }} |
        Belum masuk: {{ $stats['belum_masuk'] }} |
        Rata-rata: {{ $stats['rata_rata_nilai'] }}
    </div>
    <table>
        <tr>
            <th>Rank</th>
            <th>NISN</th>
            <th>Nama</th>
            <th>Kehadiran</th>
            <th>Status</th>
            <th>PG</th>
            <th>ISIAN</th>
            <th>Total</th>
            <th>Waktu Submit</th>
        </tr>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row->ranking ?? '-' }}</td>
                <td>{{ $row->username }}</td>
                <td>{{ $row->name }}</td>
                <td>{{ $row->status_kehadiran }}</td>
                <td>{{ $row->status }}</td>
                <td>{{ $row->nilai_pg }}</td>
                <td>{{ $row->nilai_isian }}</td>
                <td>{{ $row->nilai_akhir }}</td>
                <td>{{ $row->waktu_submit ?? '-' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
