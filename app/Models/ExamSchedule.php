<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $fillable = ['title', 'subject', 'starts_at', 'ends_at', 'duration_minutes', 'target_class', 'token', 'show_score'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'show_score' => 'boolean'];
    public function questions() { return $this->belongsToMany(Question::class); }
    public function sessions() { return $this->hasMany(ExamSession::class); }
}
