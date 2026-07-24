<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuestionApiController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Question::class);

        $user = Auth::user();

        $quizzes = Quiz::manageableBy($user)
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        $questions = Question::query()
            ->with(['options', 'quiz:id,title'])
            ->whereHas('quiz', fn ($query) => $query->manageableBy($user))
            ->orderBy('id')
            ->get();

        return response()->json([
            'quizzes' => $quizzes->map(fn (Quiz $quiz) => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'status' => $quiz->status,
                'lifecycle_status' => $quiz->lifecycleStatus(),
                'questions_count' => (int) $quiz->questions_count,
                'can_edit_questions' => $quiz->canEditQuestions(),
            ])->values(),
            'questions' => $questions->map(fn (Question $question) => $this->serializeQuestion($question))->values(),
            'count' => $questions->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuestion($request);

        $quiz = Quiz::findOrFail($validated['quiz_id']);
        $this->authorize('create', [Question::class, $quiz]);

        if (! $quiz->canEditQuestions()) {
            throw ValidationException::withMessages([
                'quiz' => 'Questions can only be authored while the quiz is in Draft with no attempts or results.',
            ]);
        }

        $question = DB::transaction(function () use ($validated) {
            $question = Question::create([
                'quiz_id' => $validated['quiz_id'],
                'question' => $validated['question'],
                'question_type' => $validated['question_type'],
                'marks' => $validated['marks'],
            ]);

            foreach ($validated['options'] as $index => $optionText) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $validated['correct_option'] === $index,
                ]);
            }

            return $question->load(['options', 'quiz:id,title']);
        });

        return response()->json([
            'message' => 'Question added successfully.',
            'question' => $this->serializeQuestion($question),
        ], 201);
    }

    public function update(Request $request, Question $question)
    {
        $this->authorize('update', $question);

        if (! $question->quiz->canEditQuestions()) {
            throw ValidationException::withMessages([
                'question' => 'Published quiz questions are immutable.',
            ]);
        }

        $validated = $this->validateQuestion($request);
        $quiz = Quiz::findOrFail($validated['quiz_id']);
        $this->authorize('create', [Question::class, $quiz]);

        if (! $quiz->canEditQuestions()) {
            throw ValidationException::withMessages([
                'quiz' => 'Questions can only be moved to a Draft quiz with no attempts or results.',
            ]);
        }

        $question = DB::transaction(function () use ($question, $validated) {
            $question->update([
                'quiz_id' => $validated['quiz_id'],
                'question' => $validated['question'],
                'question_type' => $validated['question_type'],
                'marks' => $validated['marks'],
            ]);

            $question->options()->delete();
            foreach ($validated['options'] as $index => $optionText) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $validated['correct_option'] === $index,
                ]);
            }

            return $question->fresh()->load(['options', 'quiz:id,title']);
        });

        return response()->json([
            'message' => 'Question updated successfully.',
            'question' => $this->serializeQuestion($question),
        ]);
    }

    public function destroy(Question $question)
    {
        $this->authorize('delete', $question);

        if (! $question->quiz->canEditQuestions()) {
            throw ValidationException::withMessages([
                'question' => 'Published quiz questions are immutable.',
            ]);
        }

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully.',
        ]);
    }

    private function serializeQuestion(Question $question): array
    {
        $options = $question->options->values();
        $correctIndex = $options->search(fn (QuestionOption $option) => (bool) $option->is_correct);
        $letters = ['A', 'B', 'C', 'D'];

        return [
            'id' => $question->id,
            'quiz_id' => $question->quiz_id,
            'quiz_title' => $question->quiz?->title ?? 'Quiz',
            'question' => $question->question,
            'question_type' => $question->question_type,
            'marks' => (int) $question->marks,
            'correct_option' => $correctIndex === false ? 0 : (int) $correctIndex,
            'correct_answer' => $correctIndex === false ? 'A' : ($letters[$correctIndex] ?? 'A'),
            'options' => $options->map(fn (QuestionOption $option) => [
                'id' => $option->id,
                'text' => $option->option_text,
                'is_correct' => (bool) $option->is_correct,
            ])->values(),
        ];
    }

    /**
     * @return array{
     *     quiz_id: int,
     *     question: string,
     *     question_type: string,
     *     marks: int,
     *     options: array<int, string>,
     *     correct_option: int
     * }
     */
    private function validateQuestion(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'quiz_id' => ['required', 'integer', 'exists:quizzes,id'],
            'question' => ['required', 'string', 'max:2000'],
            'question_type' => ['required', 'in:'.implode(',', Question::GRADABLE_TYPES)],
            'marks' => ['required', 'integer', 'min:1'],
            'options' => ['required', 'array'],
            'options.*' => ['required', 'string', 'max:500'],
            'correct_option' => ['required', 'integer', 'min:0'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $type = $request->input('question_type');
            $submittedOptions = $request->input('options', []);
            $options = is_array($submittedOptions)
                ? array_map(fn ($option) => trim((string) $option), $submittedOptions)
                : [];
            $expectedCount = $type === Question::TYPE_TRUE_FALSE ? 2 : 4;
            $correctIndex = $request->integer('correct_option');

            if (trim((string) $request->input('question')) === '') {
                $validator->errors()->add('question', 'Question text cannot be blank.');
            }

            if (count($options) !== $expectedCount) {
                $validator->errors()->add(
                    'options',
                    $type === Question::TYPE_TRUE_FALSE
                        ? 'True/False questions require exactly two options.'
                        : 'Multiple Choice questions require exactly four options.',
                );
            }

            if (collect($options)->contains(fn (string $option) => $option === '')) {
                $validator->errors()->add('options', 'Answer options cannot be blank.');
            }

            if (! array_key_exists($correctIndex, $options)) {
                $validator->errors()->add('correct_option', 'The selected correct option does not exist.');
            }
        });

        $validated = $validator->validate();

        return [
            'quiz_id' => (int) $validated['quiz_id'],
            'question' => trim($validated['question']),
            'question_type' => $validated['question_type'],
            'marks' => (int) $validated['marks'],
            'options' => array_map(fn ($option) => trim($option), $validated['options']),
            'correct_option' => (int) $validated['correct_option'],
        ];
    }
}
