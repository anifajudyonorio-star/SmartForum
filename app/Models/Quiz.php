<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_SCHEDULED = 'Scheduled';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_CLOSED = 'Closed';

    public const PUBLISHED_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'category_id',
        'group_id',
        'title',
        'description',
        'duration',
        'participation_marks',
        'start_time',
        'end_time',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * A quiz belongs to one category.
     */
    public function category()
    {
        return $this->belongsTo(QuizCategory::class, 'category_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function results()
    {
        return $this->hasMany(QuizResult::class);
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeManageableBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->where(function (Builder $query) use ($user) {
                $query->whereNull('group_id')
                    ->where('created_by', $user->id);
            })->orWhereHas('group', function (Builder $query) use ($user) {
                $query->where('Status', 'Active')
                    ->whereHas('memberships', function (Builder $query) use ($user) {
                        $query->where('User_ID', $user->id)
                            ->where('Member_Status', GroupMember::STATUS_ACTIVE)
                            ->whereIn('Member_Role', [
                                GroupMember::ROLE_ADMIN,
                                GroupMember::ROLE_LECTURER,
                            ]);
                    });
            });
        });
    }

    public function scopeAccessibleToStudent(Builder $query, User $user): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SCHEDULED,
            self::STATUS_ACTIVE,
        ])
            ->whereNotNull('group_id')
            ->whereHas('group', function (Builder $query) use ($user) {
                $query->where('Status', 'Active')
                    ->whereHas('memberships', function (Builder $query) use ($user) {
                        $query->where('User_ID', $user->id)
                            ->where('Member_Status', GroupMember::STATUS_ACTIVE);
                    });
            });
    }

    public function lifecycleStatus(): string
    {
        if ($this->status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        if ($this->status === self::STATUS_CLOSED || ! $this->start_time || ! $this->end_time) {
            return self::STATUS_CLOSED;
        }

        if (now()->lt($this->start_time)) {
            return self::STATUS_SCHEDULED;
        }

        return now()->lt($this->end_time)
            ? self::STATUS_ACTIVE
            : self::STATUS_CLOSED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return in_array($this->status, self::PUBLISHED_STATUSES, true);
    }

    public function isAvailableToStudents(): bool
    {
        return $this->lifecycleStatus() === self::STATUS_ACTIVE
            && $this->publicationErrors() === [];
    }

    public function isVisibleToStudents(): bool
    {
        return in_array($this->lifecycleStatus(), [
            self::STATUS_SCHEDULED,
            self::STATUS_ACTIVE,
        ], true) && $this->publicationErrors() === [];
    }

    public function hasAssessmentActivity(): bool
    {
        return $this->attempts()->exists() || $this->results()->exists();
    }

    public function canEditQuestions(): bool
    {
        return $this->isDraft() && ! $this->hasAssessmentActivity();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft() && ! $this->hasAssessmentActivity();
    }

    public function authoredMarks(): int
    {
        if (array_key_exists('questions_sum_marks', $this->attributes)) {
            return (int) ($this->attributes['questions_sum_marks'] ?? 0);
        }

        return (int) $this->questions()->sum('marks');
    }

    public function authoredMaximumTotal(): int
    {
        return $this->authoredMarks() + (int) ($this->participation_marks ?? 0);
    }

    /**
     * @return array<string, string>
     */
    public function publicationErrors(): array
    {
        $errors = [];

        if (! $this->group_id || ! $this->group || ! $this->group->isActive()) {
            $errors['group_id'] = 'Assign the quiz to an active group before publishing.';
        }

        if (! $this->start_time || ! $this->end_time || ! $this->start_time->lt($this->end_time)) {
            $errors['schedule'] = 'The quiz schedule must have an end time after its start time.';
        } elseif (! now()->lt($this->end_time)) {
            $errors['schedule'] = 'The quiz end time must be in the future when it is published.';
        }

        if ((int) $this->duration <= 0) {
            $errors['duration'] = 'Quiz duration must be at least one minute.';
        }

        $questions = $this->relationLoaded('questions')
            ? $this->questions
            : $this->questions()->with('options')->get();

        $questions->loadMissing('options');

        if ($questions->isEmpty()) {
            $errors['questions'] = 'Add at least one fully valid gradable question before publishing.';

            return $errors;
        }

        foreach ($questions as $question) {
            $problem = $question->publicationProblem();

            if ($problem !== null) {
                $errors["question_{$question->id}"] = "Question #{$question->id}: {$problem}";
            }
        }

        return $errors;
    }
}
