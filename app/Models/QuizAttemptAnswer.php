<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_id',
        'correct_option_id',
        'question_text_snapshot',
        'question_type_snapshot',
        'question_marks_snapshot',
        'selected_option_text_snapshot',
        'correct_option_text_snapshot',
        'options_snapshot',
        'is_correct',
        'awarded_marks',
    ];

    protected $casts = [
        'attempt_id' => 'integer',
        'question_id' => 'integer',
        'selected_option_id' => 'integer',
        'correct_option_id' => 'integer',
        'question_marks_snapshot' => 'integer',
        'options_snapshot' => 'array',
        'is_correct' => 'boolean',
        'awarded_marks' => 'integer',
    ];

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class);
    }
}
