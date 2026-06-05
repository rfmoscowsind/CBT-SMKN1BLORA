<?php

namespace App\Http\Controllers;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CbtController extends Controller
{
    public function loginForm() { return Auth::check() ? redirect()->route('dashboard') : view('auth.login'); }
    public function login(Request $request) { $credentials = $request->validate(['username' => ['required'], 'password' => ['required']]); $user = \App\Models\User::where('username', $credentials['username'])->first(); if (!$user || !Hash::check($credentials['password'], $user->password)) return back()->withErrors(['username' => 'Username atau password salah.'])->onlyInput('username'); Auth::login($user); $request->session()->regenerate(); return redirect()->route('dashboard'); }
    public function logout(Request $request) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
    public function dashboard() { $schedules = ExamSchedule::withCount(['questions', 'sessions'])->latest()->get(); return view('dashboard.index', ['schedules' => $schedules, 'questionCount' => Question::count(), 'sessionCount' => ExamSession::count()]); }
    public function questions() { $this->staff(); return view('questions.index', ['questions' => Question::latest()->get()]); }
    public function storeQuestion(Request $request) { $this->staff(); $data = $request->validate(['subject' => 'required|max:100', 'question_text' => 'required', 'correct_answer' => 'required|in:A,B,C,D', 'weight' => 'required|numeric|min:0', 'option_a' => 'required', 'option_b' => 'required', 'option_c' => 'required', 'option_d' => 'required']); Question::create(['subject' => $data['subject'], 'question_text' => $data['question_text'], 'correct_answer' => $data['correct_answer'], 'weight' => $data['weight'], 'type' => 'pg', 'options' => ['A' => $data['option_a'], 'B' => $data['option_b'], 'C' => $data['option_c'], 'D' => $data['option_d']]]); return back()->with('success', 'Soal berhasil ditambahkan.'); }
    public function deleteQuestion(Question $question) { $this->staff(); $question->delete(); return back()->with('success', 'Soal dihapus.'); }
    public function schedules() { $this->staff(); return view('schedules.index', ['schedules' => ExamSchedule::withCount('questions')->latest()->get(), 'questions' => Question::orderBy('subject')->get()]); }
    public function storeSchedule(Request $request) { $this->staff(); $data = $request->validate(['title' => 'required|max:150', 'subject' => 'required|max:100', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'duration_minutes' => 'required|integer|min:1', 'target_class' => 'nullable|max:50', 'token' => 'nullable|max:12', 'question_ids' => 'required|array|min:1']); $data['token'] = $data['token'] ?: Str::upper(Str::random(6)); $ids = $data['question_ids']; unset($data['question_ids']); $schedule = ExamSchedule::create($data); $schedule->questions()->sync($ids); return back()->with('success', 'Jadwal ujian berhasil dibuat.'); }
    public function startExam(Request $request, ExamSchedule $schedule) { abort_unless(Auth::user()->role === 'siswa', 403); $request->validate(['token' => 'nullable|string']); if ($schedule->token && Str::upper((string)$request->token) !== Str::upper($schedule->token)) return back()->withErrors(['token' => 'Token ujian tidak sesuai.']); if (now()->lt($schedule->starts_at) || now()->gt($schedule->ends_at)) return back()->withErrors(['token' => 'Ujian belum dimulai atau telah berakhir.']); $session = ExamSession::firstOrCreate(['user_id' => Auth::id(), 'exam_schedule_id' => $schedule->id], ['started_at' => now(), 'status' => 'aktif']); if ($session->status !== 'aktif') return redirect()->route('exams.result', $session); return redirect()->route('exams.show', ['session' => $session, 'nomor' => 1]); }

    public function exam(Request $request, ExamSession $session)
    {
        $this->own($session); if ($session->status !== 'aktif') return redirect()->route('exams.result', $session); $session->load('schedule.questions', 'answers'); $total = $session->schedule->questions->count(); abort_if($total === 0, 404, 'Soal belum tersedia.'); $number = max(1, min((int) $request->query('nomor', 1), $total)); $question = $session->schedule->questions[$number - 1]; $savedAnswer = $session->answers->firstWhere('question_id', $question->id)?->answer; return view('exams.show', compact('session', 'question', 'savedAnswer', 'number', 'total'));
    }

    public function saveExam(Request $request, ExamSession $session)
    {
        $this->own($session); if ($session->status !== 'aktif') return redirect()->route('exams.result', $session); $data = $request->validate(['question_id' => 'required|integer', 'answer' => 'nullable|string|max:20', 'next_number' => 'nullable|integer|min:1']); $question = $session->schedule->questions()->findOrFail($data['question_id']); StudentAnswer::updateOrCreate(['exam_session_id' => $session->id, 'question_id' => $question->id], ['answer' => $data['answer'] ?? null, 'score' => ($data['answer'] ?? null) === $question->correct_answer ? $question->weight : 0]); return redirect()->route('exams.show', ['session' => $session, 'nomor' => $data['next_number'] ?? 1])->with('success', 'Jawaban tersimpan.');
    }

    public function submitExam(Request $request, ExamSession $session)
    {
        $this->own($session); abort_if($session->status !== 'aktif', 410); if ($request->filled('question_id')) { $question = $session->schedule->questions()->findOrFail($request->integer('question_id')); StudentAnswer::updateOrCreate(['exam_session_id' => $session->id, 'question_id' => $question->id], ['answer' => $request->input('answer'), 'score' => $request->input('answer') === $question->correct_answer ? $question->weight : 0]); } $score = StudentAnswer::where('exam_session_id', $session->id)->sum('score'); $session->update(['status' => 'selesai', 'submitted_at' => now(), 'score' => $score]); return redirect()->route('exams.result', $session);
    }

    public function result(ExamSession $session) { $this->own($session); $session->load('schedule.questions', 'answers'); return view('exams.result', compact('session')); }
    private function staff(): void { abort_unless(in_array(Auth::user()->role, ['admin', 'guru']), 403); }
    private function own(ExamSession $session): void { abort_unless($session->user_id === Auth::id() || Auth::user()->role === 'admin', 403); }
}