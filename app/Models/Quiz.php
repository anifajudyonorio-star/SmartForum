<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
    'category_id',
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
}