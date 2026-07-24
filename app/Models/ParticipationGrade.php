<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipationGrade extends Model
{
    protected $fillable = [
        'group_id',
        'user_id',
        'manual_marks',
        'notes',
        'graded_by',
    ];

    protected function casts(): array
    {
        return [
            'manual_marks' => 'integer',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
