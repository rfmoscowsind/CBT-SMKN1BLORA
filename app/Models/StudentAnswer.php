<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    protected $fillable = ['exam_session_id', 'question_id', 'answer', 'score'];
}
