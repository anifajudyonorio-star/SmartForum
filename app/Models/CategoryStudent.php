<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryStudent extends Model
{
    use HasFactory;

    protected $table = 'category_students';

    protected $fillable = [
        'category_id',
        'user_id',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(QuizCategory::class, 'category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
