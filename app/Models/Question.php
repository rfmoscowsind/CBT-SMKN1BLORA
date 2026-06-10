<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['subject', 'question_text', 'type', 'options', 'correct_answer', 'weight'];
    protected $casts = ['options' => 'array', 'weight' => 'decimal:2'];
}
