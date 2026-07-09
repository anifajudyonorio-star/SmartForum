<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        $lecturer = User::where('email', 'lecturer@smartforum.com')->first();

        if (! $lecturer) {
            return;
        }

        $category = QuizCategory::updateOrCreate(
            ['category_name' => 'General Knowledge'],
            [
                'description' => 'Introductory quizzes for new students.',
                'created_by' => $lecturer->id,
            ]
        );

        $quiz = Quiz::updateOrCreate(
            ['title' => 'SmartForum Welcome Quiz'],
            [
                'category_id' => $category->id,
                'description' => 'A short quiz to help students get familiar with the platform.',
                'duration' => 15,
                'participation_marks' => 2,
                'start_time' => now()->subDay(),
                'end_time' => now()->addMonths(3),
                'status' => 'Active',
                'created_by' => $lecturer->id,
            ]
        );

        $questions = [
            [
                'question' => 'What is the primary purpose of SmartForum?',
                'marks' => 2,
                'options' => [
                    ['text' => 'Academic group discussions', 'correct' => true],
                    ['text' => 'Online shopping', 'correct' => false],
                    ['text' => 'Video streaming', 'correct' => false],
                    ['text' => 'Social media posting', 'correct' => false],
                ],
            ],
            [
                'question' => 'Who can create discussion groups?',
                'marks' => 2,
                'options' => [
                    ['text' => 'Lecturers and admins', 'correct' => true],
                    ['text' => 'Students only', 'correct' => false],
                    ['text' => 'Guests', 'correct' => false],
                    ['text' => 'Anyone without login', 'correct' => false],
                ],
            ],
            [
                'question' => 'Students can join groups created by lecturers.',
                'marks' => 1,
                'options' => [
                    ['text' => 'True', 'correct' => true],
                    ['text' => 'False', 'correct' => false],
                ],
            ],
        ];

        foreach ($questions as $index => $data) {
            $question = Question::updateOrCreate(
                [
                    'quiz_id' => $quiz->id,
                    'question' => $data['question'],
                ],
                [
                    'question_type' => count($data['options']) === 2 ? 'True/False' : 'Multiple Choice',
                    'marks' => $data['marks'],
                ]
            );

            $question->options()->delete();

            foreach ($data['options'] as $option) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $option['text'],
                    'is_correct' => $option['correct'],
                ]);
            }
        }
    }
}
