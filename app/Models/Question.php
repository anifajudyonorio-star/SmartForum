<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question',
        'question_type',
        'marks',
    ];

    protected $casts = [
        'marks' => 'integer',
    ];

    /**
     * A question belongs to one quiz.
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }
    public function options()
{
    return $this->hasMany(QuestionOption::class);
}
}