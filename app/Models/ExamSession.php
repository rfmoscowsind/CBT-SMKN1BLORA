<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    protected $fillable = ['user_id', 'exam_schedule_id', 'started_at', 'submitted_at', 'status', 'score'];
    protected $casts = ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'score' => 'decimal:2'];
    public function schedule() { return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function answers() { return $this->hasMany(StudentAnswer::class); }
}
