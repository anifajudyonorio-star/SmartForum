<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupParticipationSetting;
use App\Models\ParticipationGrade;
use App\Models\User;
use Illuminate\Support\Collection;

class ParticipationService
{
    /**
     * @return array{0: Collection<int, object>, 1: int, 2: GroupParticipationSetting|null}
     */
    public function buildParticipants(User $viewer, ?Group $selectedGroup, Collection $availableGroups): array
    {
        $settings = $selectedGroup
            ? GroupParticipationSetting::defaultsFor($selectedGroup)
            : null;

        if ($selectedGroup) {
            $group = $selectedGroup;
            $topicIds = $group->topics()->pluck('id');

            $participants = $group->members()
                ->withCount([
                    'topics as topics_count' => fn ($q) => $q->where('Group_ID', $group->id),
                    'posts as top_level_posts_count' => fn ($q) => $q
                        ->whereIn('Topic_ID', $topicIds)
                        ->whereNull('Parent_Post_ID'),
                    'posts as replies_count' => fn ($q) => $q
                        ->whereIn('Topic_ID', $topicIds)
                        ->whereNotNull('Parent_Post_ID'),
                ])
                ->get();
        } else {
            $groupIds = $availableGroups->pluck('id');

            $query = User::query()->where(function ($q) {
                $q->whereNull('role')->orWhere('role', 'student');
            });

            if ($groupIds->isNotEmpty()) {
                $query->whereHas('groups', function ($q) use ($groupIds) {
                    $q->whereIn('groups.id', $groupIds);
                });
            } elseif (! $viewer->isAdmin()) {
                $query->whereRaw('1 = 0');
            }

            $participants = $query->withCount([
                'topics',
                'posts as top_level_posts_count' => fn ($q) => $q->whereNull('Parent_Post_ID'),
                'posts as replies_count' => fn ($q) => $q->whereNotNull('Parent_Post_ID'),
            ])->get();

            foreach ($participants as $participant) {
                $participant->topics_count = $participant->topics_count ?? 0;
            }
        }

        $grades = $selectedGroup
            ? ParticipationGrade::where('group_id', $selectedGroup->id)->get()->keyBy('user_id')
            : collect();

        $defaultSettings = $settings ?? new GroupParticipationSetting([
            'topic_points' => 5,
            'post_points' => 3,
            'reply_points' => 2,
            'gold_min' => 50,
            'silver_min' => 30,
            'bronze_min' => 15,
            'manual_marks_max' => 20,
        ]);

        foreach ($participants as $participant) {
            $topics = (int) ($participant->topics_count ?? 0);
            $posts = (int) ($participant->top_level_posts_count ?? 0);
            $replies = (int) ($participant->replies_count ?? 0);

            $participant->posts_count = $posts;
            $participant->topics_count = $topics;
            $participant->replies_count = $replies;

            $autoScore = ($topics * $defaultSettings->topic_points)
                + ($posts * $defaultSettings->post_points)
                + ($replies * $defaultSettings->reply_points);

            $grade = $grades->get($participant->id);
            $manualMarks = (int) ($grade?->manual_marks ?? 0);
            $participant->auto_score = $autoScore;
            $participant->manual_marks = $manualMarks;
            $participant->score = $autoScore + $manualMarks;
            $participant->grade_notes = $grade?->notes;
            $participant->rank = $this->rankLabel($participant->score, $defaultSettings);
        }

        $highestScore = max(1, (int) $participants->max('score'));

        return [$participants->sortByDesc('score'), $highestScore, $settings];
    }

    public function rankLabel(int $score, GroupParticipationSetting $settings): string
    {
        return match (true) {
            $score >= $settings->gold_min => '🥇 Gold',
            $score >= $settings->silver_min => '🥈 Silver',
            $score >= $settings->bronze_min => '🥉 Bronze',
            default => '⭐ Beginner',
        };
    }

    public function updateSettings(Group $group, array $data): GroupParticipationSetting
    {
        $settings = GroupParticipationSetting::defaultsFor($group);
        $settings->update([
            'topic_points' => $data['topic_points'],
            'post_points' => $data['post_points'],
            'reply_points' => $data['reply_points'],
            'gold_min' => $data['gold_min'],
            'silver_min' => $data['silver_min'],
            'bronze_min' => $data['bronze_min'],
            'manual_marks_max' => $data['manual_marks_max'],
        ]);

        return $settings->fresh();
    }

    public function updateManualGrade(Group $group, User $student, User $grader, int $manualMarks, ?string $notes): ParticipationGrade
    {
        $settings = GroupParticipationSetting::defaultsFor($group);
        $manualMarks = min(max(0, $manualMarks), $settings->manual_marks_max);

        return ParticipationGrade::updateOrCreate(
            [
                'group_id' => $group->id,
                'user_id' => $student->id,
            ],
            [
                'manual_marks' => $manualMarks,
                'notes' => $notes,
                'graded_by' => $grader->id,
            ]
        );
    }

    public function formatParticipant(User $participant, ?GroupParticipationSetting $settings): array
    {
        $settings ??= new GroupParticipationSetting([
            'topic_points' => 5,
            'post_points' => 3,
            'reply_points' => 2,
            'gold_min' => 50,
            'silver_min' => 30,
            'bronze_min' => 15,
            'manual_marks_max' => 20,
        ]);

        return [
            'user_id' => $participant->id,
            'name' => $participant->name,
            'topics_count' => (int) ($participant->topics_count ?? 0),
            'posts_count' => (int) ($participant->posts_count ?? 0),
            'replies_count' => (int) ($participant->replies_count ?? 0),
            'auto_score' => (int) ($participant->auto_score ?? 0),
            'manual_marks' => (int) ($participant->manual_marks ?? 0),
            'score' => (int) ($participant->score ?? 0),
            'rank' => $participant->rank ?? $this->rankLabel((int) ($participant->score ?? 0), $settings),
            'grade_notes' => $participant->grade_notes ?? null,
        ];
    }

    public function formatSettings(?GroupParticipationSetting $settings): array
    {
        $settings ??= new GroupParticipationSetting([
            'topic_points' => 5,
            'post_points' => 3,
            'reply_points' => 2,
            'gold_min' => 50,
            'silver_min' => 30,
            'bronze_min' => 15,
            'manual_marks_max' => 20,
        ]);

        return [
            'topic_points' => $settings->topic_points,
            'post_points' => $settings->post_points,
            'reply_points' => $settings->reply_points,
            'gold_min' => $settings->gold_min,
            'silver_min' => $settings->silver_min,
            'bronze_min' => $settings->bronze_min,
            'manual_marks_max' => $settings->manual_marks_max,
        ];
    }
}
