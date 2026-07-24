<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupParticipationSetting extends Model
{
    protected $fillable = [
        'group_id',
        'topic_points',
        'post_points',
        'reply_points',
        'gold_min',
        'silver_min',
        'bronze_min',
        'manual_marks_max',
    ];

    protected function casts(): array
    {
        return [
            'topic_points' => 'integer',
            'post_points' => 'integer',
            'reply_points' => 'integer',
            'gold_min' => 'integer',
            'silver_min' => 'integer',
            'bronze_min' => 'integer',
            'manual_marks_max' => 'integer',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public static function defaultsFor(Group $group): self
    {
        return self::firstOrCreate(
            ['group_id' => $group->id],
            [
                'topic_points' => 5,
                'post_points' => 3,
                'reply_points' => 2,
                'gold_min' => 50,
                'silver_min' => 30,
                'bronze_min' => 15,
                'manual_marks_max' => 20,
            ]
        );
    }
}
