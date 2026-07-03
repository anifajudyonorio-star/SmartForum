<?php

namespace App\Http\Controllers;

use App\Models\QuizCategory;
use Illuminate\Http\Request;

class QuizCategoryController extends Controller
{
    public function index()
    {
        $categories = QuizCategory::all();
        return view('quiz_categories.index', compact('categories'));
    }

    public function create()
    {
        return view('quiz_categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|max:255',
        ]);

        QuizCategory::create([
            'category_name' => $request->category_name,
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully.');
    }
}