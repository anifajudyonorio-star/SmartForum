<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Question::class);

        $quizzes = Quiz::manageableBy(auth()->user())
            ->with(['questions.options'])
            ->whereHas('questions')
            ->orderBy('title')
            ->get();

        return view('questions.index', compact('quizzes'));
    }

    public function create()
    {
        $this->authorize('viewAny', Question::class);

        $quizzes = $this->authorableQuizzes();

        return view('questions.create', compact('quizzes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuestion($request);

        $quiz = Quiz::findOrFail((int) $validated['quiz_id']);
        $this->authorize('create', [Question::class, $quiz]);

        if (! $quiz->canEditQuestions()) {
            throw ValidationException::withMessages([
                'quiz' => 'Questions can only be authored while the quiz is in Draft with no attempts or results.',
            ]);
        }

        DB::transaction(function () use ($validated) {
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
        });

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question added successfully.');
    }

    public function edit(Question $question)
    {
        $this->authorize('update', $question);

        if (! $question->quiz->canEditQuestions()) {
            return redirect()->route('questions.index')->withErrors([
                'question' => 'Published quiz questions are immutable.',
            ]);
        }

        $quizzes = $this->authorableQuizzes();
        $question->load('options');

        return view('questions.edit', compact('question', 'quizzes'));
    }

    public function update(Request $request, Question $question)
    {
        $this->authorize('update', $question);

        if ($request->filled('quiz_id')
            && $request->integer('quiz_id') !== (int) $question->quiz_id) {
            $targetQuiz = Quiz::findOrFail($request->integer('quiz_id'));
            $this->authorize('create', [Question::class, $targetQuiz]);
        }

        if (! $question->quiz->canEditQuestions()) {
            throw ValidationException::withMessages([
                'question' => 'Published quiz questions are immutable.',
            ]);
        }

        $validated = $this->validateQuestion($request);

        $quiz = Quiz::findOrFail((int) $validated['quiz_id']);
        $this->authorize('create', [Question::class, $quiz]);

        if (! $quiz->canEditQuestions()) {
            throw ValidationException::withMessages([
                'quiz' => 'Questions can only be moved to a Draft quiz with no attempts or results.',
            ]);
        }

        DB::transaction(function () use ($question, $validated) {
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
        });

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(Question $question)
    {
        $this->authorize('delete', $question);

        if (! $question->quiz->canEditQuestions()) {
            return back()->withErrors([
                'question' => 'Published quiz questions cannot be deleted.',
            ]);
        }

        $question->delete();

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question deleted successfully.');
    }

    private function authorableQuizzes()
    {
        return Quiz::manageableBy(auth()->user())
            ->where('status', Quiz::STATUS_DRAFT)
            ->whereDoesntHave('attempts')
            ->whereDoesntHave('results')
            ->orderBy('title')
            ->get();
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
            $question = trim((string) $request->input('question'));
            $type = $request->input('question_type');
            $submittedOptions = $request->input('options', []);
            $options = is_array($submittedOptions)
                ? array_map(fn ($option) => trim((string) $option), $submittedOptions)
                : [];
            $expectedCount = $type === Question::TYPE_TRUE_FALSE ? 2 : 4;
            $correctIndex = $request->integer('correct_option');

            if ($question === '') {
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

            if ($type === Question::TYPE_TRUE_FALSE) {
                $normalized = collect($options)
                    ->map(fn (string $option) => strtolower($option))
                    ->sort()
                    ->values()
                    ->all();

                if ($normalized !== ['false', 'true']) {
                    $validator->errors()->add(
                        'options',
                        'True/False options must be exactly True and False.',
                    );
                }
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
