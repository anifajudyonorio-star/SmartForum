<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    public const TYPE_MULTIPLE_CHOICE = 'Multiple Choice';

    public const TYPE_TRUE_FALSE = 'True/False';

    public const GRADABLE_TYPES = [
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_TRUE_FALSE,
    ];

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

    public function publicationProblem(): ?string
    {
        if (! in_array($this->question_type, self::GRADABLE_TYPES, true)) {
            return $this->question_type === 'Short Answer'
                ? 'Short Answer is not supported until manual grading is available; replace this question.'
                : 'the question type is not supported.';
        }

        if (trim((string) $this->question) === '') {
            return 'question text cannot be blank.';
        }

        if ((int) $this->marks <= 0) {
            return 'marks must be a positive integer.';
        }

        $options = $this->relationLoaded('options')
            ? $this->options
            : $this->options()->get();
        $expectedCount = $this->question_type === self::TYPE_TRUE_FALSE ? 2 : 4;

        if ($options->count() !== $expectedCount) {
            return $this->question_type === self::TYPE_TRUE_FALSE
                ? 'True/False questions must have exactly two options.'
                : 'Multiple Choice questions must have exactly four options.';
        }

        if ($options->contains(fn (QuestionOption $option) => trim((string) $option->option_text) === '')) {
            return 'answer options cannot be blank.';
        }

        if ($options->where('is_correct', true)->count() !== 1) {
            return 'exactly one answer option must be marked correct.';
        }

        if ($this->question_type === self::TYPE_TRUE_FALSE) {
            $normalized = $options
                ->map(fn (QuestionOption $option) => strtolower(trim($option->option_text)))
                ->sort()
                ->values()
                ->all();

            if ($normalized !== ['false', 'true']) {
                return 'True/False options must be exactly True and False.';
            }
        }

        return null;
    }
}
