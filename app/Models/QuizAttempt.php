<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_AUTO_SUBMITTED = 'Auto Submitted';

    protected $fillable = [
        'quiz_id',
        'user_id',
        'started_at',
        'submitted_at',
        'score',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAttemptAnswer::class, 'attempt_id');
    }

    public function result()
    {
        return $this->hasOne(QuizResult::class, 'attempt_id');
    }
}
