<?php
namespace App\Http\Controllers;
use App\Services\IdCodec;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class OperationsApiController extends Controller  {
    public function __construct(private IdCodec $ids,private ReportService $reports) {
    }
    private function ok($d=null) {
        return response()->json(['success'=>true,'data'=>$d,'message'=>null,'error'=>null]);
    }
    private function permission(string $permission):void {
        abort_unless(Auth::guard('api')->user()?->can($permission),403);
        // FIXED: no fresh()
    }
    public function event(Request $r,string $hash) {
        $sid=$this->ids->decode($hash);
        abort_unless(DB::table('sesi_ujians')->where(['id'=>$sid,'user_id'=>Auth::guard('api')->id()])->exists(),403);
        DB::table('session_events')->insert(['sesi_ujian_id'=>$sid,'event_type'=>$r->string('event_type'),'event_data'=>json_encode($r->input('event_data',[])+['ip'=>$r->ip(),'user_agent'=>$r->userAgent()]),'created_at'=>now(),'updated_at'=>now()]);
        return $this->ok();
    }
    public function active(Request $r) {
        $this->permission('monitor-exams');
        $counts=DB::connection('pgsql_standby')->table('jawaban_siswas')->select('sesi_ujian_id',DB::raw('count(*) as terjawab'))->groupBy('sesi_ujian_id');
        $rows=DB::connection('pgsql_standby')->table('sesi_ujians as s')->join('users as u','u.id','=','s.user_id')->join('jadwal_ujians as j','j.id','=','s.jadwal_ujian_id')->leftJoinSub($counts,'a','a.sesi_ujian_id','=','s.id')->where('s.jadwal_ujian_id',$r->integer('jadwal_id'))->select('s.id','u.name','s.status','s.last_seen_at','s.nilai_akhir','s.ip_address','s.device_info','s.waktu_login','j.durasi_menit',DB::raw('coalesce(a.terjawab,0) as terjawab'))->get();
        $total=DB::connection('pgsql_standby')->table('sesi_ujian_soals')->whereIn('sesi_ujian_id',$rows->pluck('id'))->select('sesi_ujian_id',DB::raw('count(*) as jumlah'))->groupBy('sesi_ujian_id')->pluck('jumlah','sesi_ujian_id');
        return $this->ok($rows->map(function($x)use($total) {
            $deadline=now()->parse($x->waktu_login)->addMinutes($x->durasi_menit);
            return ['session_hash'=>$this->ids->encode($x->id),'name'=>$x->name,'status'=>$x->status,'online'=>$x->last_seen_at&&now()->diffInSeconds($x->last_seen_at)<=30,'last_seen_at'=>$x->last_seen_at,'remaining_seconds'=>max(0,now()->diffInSeconds($deadline,false)),'terjawab'=>(int)$x->terjawab,'total_soal'=>(int)($total[$x->id]??0),'ip_address'=>$x->ip_address,'device_info'=>json_decode($x->device_info,true),'nilai_akhir'=>$x->nilai_akhir];
        }
        ));
    }
    public function scores(Request $r) {
        abort_unless(Auth::guard('api')->user()->role==='SuperAdmin',403);
        $rows=DB::connection('pgsql_standby')->table('sesi_ujians as s')->join('users as u','u.id','=','s.user_id')->where('s.jadwal_ujian_id',$r->integer('jadwal_id'))->select('s.id','u.name','s.status','s.nilai_akhir')->get();
        return $this->ok($rows->map(fn($x)=>['session_hash'=>$this->ids->encode($x->id),'name'=>$x->name,'status'=>$x->status,'nilai_akhir'=>$x->nilai_akhir]));
    }
    public function pending() {
        $this->permission('grade-essays');
        return $this->ok(DB::table('jawaban_siswas as j')->join('bank_soals as b','b.id','=','j.bank_soal_id')->where('j.scoring_status','pending_manual')->select('j.id','j.jawaban_essay','b.pertanyaan','b.bobot_nilai')->get());
    }
    public function grade(Request $r,int $id) {
        $this->permission('grade-essays');
        $d=$r->validate(['skor_manual'=>'required|numeric|min:0','komentar'=>'nullable|string']);
        $answer=DB::table('jawaban_siswas')->find($id);
        abort_unless($answer,404);
        $max=DB::table('bank_soals')->where('id',$answer->bank_soal_id)->value('bobot_nilai');
        abort_if($d['skor_manual']>$max,422,'Skor melebihi bobot soal.');
        DB::table('jawaban_siswas')->where('id',$id)->update(['skor'=>$d['skor_manual'],'skor_manual'=>$d['skor_manual'],'komentar'=>$d['komentar']??null,'scoring_status'=>'manually_scored','dinilai_oleh_user_id'=>Auth::guard('api')->id(),'tanggal_dinilai'=>now(),'updated_at'=>now()]);
        $total=DB::table('jawaban_siswas')->where('sesi_ujian_id',$answer->sesi_ujian_id)->sum('skor');
        DB::table('sesi_ujians')->where('id',$answer->sesi_ujian_id)->update(['nilai_akhir'=>$total,'updated_at'=>now()]);
        return $this->ok(DB::table('jawaban_siswas')->find($id));
    }
    public function report(int $jadwal) {
        $this->permission('view-reports');
        $rows=$this->reports->rows($jadwal);
        return $this->ok(['statistik'=>$this->reports->stats($rows),'hasil_per_siswa'=>$rows]);
    }
}