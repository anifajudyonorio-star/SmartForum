<?php

namespace App\Http\Controllers;

use App\Models\CategoryStudent;
use App\Models\GroupMember;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Http\Request;

class CategoryEnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', QuizCategory::class);

        $categories = QuizCategory::manageableBy(auth()->user())
            ->orderBy('category_name')
            ->get();

        $selectedCategory = null;
        if ($request->filled('category_id')) {
            $selectedCategory = $categories->firstWhere('id', (int) $request->integer('category_id'));
        }
        $selectedCategory ??= $categories->first();

        $enrolledStudents = collect();
        if ($selectedCategory) {
            $this->authorize('assign', $selectedCategory);
            $enrolledStudents = $selectedCategory->students()
                ->orderBy('Fname')
                ->orderBy('Lname')
                ->get();
        }

        $eligibleStudents = $this->eligibleStudents();

        return view('category_enrollments.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'enrolledStudents' => $enrolledStudents,
            'eligibleStudents' => $eligibleStudents,
            'lookupResult' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:quiz_categories,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $category = QuizCategory::findOrFail($validated['category_id']);
        $this->authorize('assign', $category);

        $student = User::findOrFail($validated['user_id']);
        if (! $student->isStudent()) {
            return back()->withErrors(['user_id' => 'Only students can be enrolled in a quiz title.']);
        }

        if (! $this->canEnrollStudent($student)) {
            return back()->withErrors([
                'user_id' => 'You can only enroll students from groups you actively teach.',
            ]);
        }

        $existing = CategoryStudent::where('user_id', $student->id)->first();
        if ($existing) {
            if ((int) $existing->category_id === (int) $category->id) {
                return redirect()
                    ->route('category-enrollments.index', ['category_id' => $category->id])
                    ->with('success', $student->name.' is already enrolled in this quiz title.');
            }

            return back()->withErrors([
                'user_id' => $student->name.' is already enrolled in another quiz title.',
            ]);
        }

        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);

        return redirect()
            ->route('category-enrollments.index', ['category_id' => $category->id])
            ->with('success', $student->name.' enrolled successfully.');
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:quiz_categories,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $category = QuizCategory::findOrFail($validated['category_id']);
        $this->authorize('assign', $category);

        CategoryStudent::where('category_id', $category->id)
            ->where('user_id', $validated['user_id'])
            ->delete();

        return redirect()
            ->route('category-enrollments.index', ['category_id' => $category->id])
            ->with('success', 'Student unenrolled successfully.');
    }

    public function lookup(Request $request)
    {
        $this->authorize('viewAny', QuizCategory::class);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'category_id' => ['nullable', 'integer', 'exists:quiz_categories,id'],
        ]);

        $student = User::where('email', $validated['email'])->first();
        $lookupResult = $student === null
            ? 'No user found with that email.'
            : (
                ($category = $student->enrolledCategory())
                    ? $student->name.' is enrolled in "'.$category->category_name.'".'
                    : $student->name.' is not enrolled in any quiz title.'
            );

        $categories = QuizCategory::manageableBy(auth()->user())
            ->orderBy('category_name')
            ->get();
        $selectedCategory = $categories->firstWhere('id', (int) ($validated['category_id'] ?? 0))
            ?? $categories->first();
        $enrolledStudents = $selectedCategory
            ? $selectedCategory->students()->orderBy('Fname')->orderBy('Lname')->get()
            : collect();

        return view('category_enrollments.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'enrolledStudents' => $enrolledStudents,
            'eligibleStudents' => $this->eligibleStudents(),
            'lookupResult' => $lookupResult,
        ]);
    }

    private function eligibleStudents()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return User::query()
                ->where(function ($query) {
                    $query->whereNull('role')->orWhere('role', 'student');
                })
                ->orderBy('Fname')
                ->orderBy('Lname')
                ->get();
        }

        $groupIds = $user->teachableGroups()->pluck('groups.id');

        return User::query()
            ->where(function ($query) {
                $query->whereNull('role')->orWhere('role', 'student');
            })
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('groups.id', $groupIds)
                    ->where('group_members.Member_Status', GroupMember::STATUS_ACTIVE);
            })
            ->orderBy('Fname')
            ->orderBy('Lname')
            ->get();
    }

    private function canEnrollStudent(User $student): bool
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        $groupIds = $user->teachableGroups()->pluck('groups.id');

        return $student->groups()
            ->whereIn('groups.id', $groupIds)
            ->where('group_members.Member_Status', GroupMember::STATUS_ACTIVE)
            ->exists();
    }
}
