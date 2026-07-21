<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'created_by',
        'title',
        'message',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(QuizCategory::class, 'category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
