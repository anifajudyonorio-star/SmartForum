<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'description',
        'created_by',
    ];

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'category_id');
    }

    public function enrollments()
    {
        return $this->hasMany(CategoryStudent::class, 'category_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'category_students', 'category_id', 'user_id')
            ->withTimestamps();
    }

    public function announcements()
    {
        return $this->hasMany(QuizAnnouncement::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeManageableBy(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query
            : $query->where('created_by', $user->id);
    }
}
