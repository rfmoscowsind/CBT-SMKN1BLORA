@extends('layouts.app')
@section('standalone', true)
@section('title','Ujian Selesai - CBT SMKN 1 Blora')
@section('content')
<style>
body{background:#f1f5f9}.result-wrap{min-height:100vh;display:grid;place-items:center;padding:18px}.result-card{max-width:550px;width:100%;background:#fff;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,.05);overflow:hidden}.card-accent{height:8px;background:linear-gradient(90deg,#1e3a8a,#3b82f6)}.success-icon{width:120px;height:120px;border-radius:50%;display:grid;place-items:center;margin:auto;background:#dcfce7;color:#16a34a;font-size:4rem}.score-circle{width:140px;height:140px;border-radius:50%;display:grid;place-items:center;margin:auto;background:#eff6ff;border:6px solid #1e3a8a;color:#1e3a8a;font-size:2.4rem;font-weight:800}

@media (max-width: 576px) {
    .result-wrap { padding: 12px; }
    .success-icon { width: 90px; height: 90px; font-size: 3rem; }
    .score-circle { width: 110px; height: 110px; font-size: 1.8rem; border-width: 4px; }
    .result-card h3 { font-size: 1.4rem !important; }
    .result-card h4 { font-size: 1.25rem !important; }
    .result-card h5 { font-size: 1.1rem !important; }
    .result-card .p-md-5 { padding: 1.5rem !important; }
    .result-card .btn { padding-top: 0.6rem !important; padding-bottom: 0.6rem !important; font-size: 0.85rem !important; }
}
</style>
<div class="result-wrap"><div class="result-card"><div class="card-accent"></div><div class="p-4 p-md-5 text-center"><img src="https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png" width="78" alt="Logo"><h5 class="fw-bold mt-3 mb-1">{{ $s->judul }}</h5><p class="text-secondary small mb-4 pb-3 border-bottom">CBT SMKN 1 Blora</p>@if($canShow)<h4 class="fw-bold mb-4">Ujian Telah Diselesaikan!</h4><div class="score-circle mb-4">{{ number_format($s->nilai_akhir,2) }}</div><p class="text-muted">Nilai akhir berhasil dihitung dan tersimpan.</p>@else<div class="success-icon mb-4"><i class="fa-solid fa-check"></i></div><h3 class="fw-bold">Terima Kasih!</h3><p class="text-secondary">Jawaban Anda telah berhasil tersimpan dengan aman di server utama.</p><div class="alert alert-info text-start small"><i class="fa-solid fa-circle-info me-1"></i> <strong>Nilai belum dirilis.</strong> Hasil akhir akan diumumkan kemudian.</div>@endif<a class="btn w-100 mt-3 py-3" href="/dashboard"><i class="fa-solid fa-house"></i> Kembali ke Dashboard Utama</a></div></div></div>
@endsection
