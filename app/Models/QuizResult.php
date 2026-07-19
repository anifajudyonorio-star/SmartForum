<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'attempt_id',
        'user_id',
        'score',
        'maximum_score',
        'participation_marks',
        'total_score',
        'maximum_total_score',
        'grading_snapshot',
        'graded_at',
    ];

    protected $casts = [
        'quiz_id' => 'integer',
        'attempt_id' => 'integer',
        'user_id' => 'integer',
        'score' => 'integer',
        'maximum_score' => 'integer',
        'participation_marks' => 'integer',
        'total_score' => 'integer',
        'maximum_total_score' => 'integer',
        'grading_snapshot' => 'array',
        'graded_at' => 'datetime',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class);
    }

    public function finalPercentage(): ?float
    {
        $maximum = (int) $this->maximum_total_score;

        if ($maximum <= 0) {
            return null;
        }

        return round(((int) $this->total_score / $maximum) * 100, 2);
    }

    public function submissionStatus(): string
    {
        $autoSubmitted = (bool) data_get($this->grading_snapshot, 'auto_submitted', false)
            || $this->attempt?->status === QuizAttempt::STATUS_AUTO_SUBMITTED;

        return $autoSubmitted ? 'Timed Out / Auto Submitted' : 'Submitted';
    }
}
