<?php

namespace App\Http\Controllers;

use App\Models\QuizResult;

class PerformanceReportController extends Controller
{
    public function index()
    {
        $results = QuizResult::with(['quiz', 'student'])
            ->latest()
            ->get();

        $averageScore = round($results->avg('total_score'), 2);

        $highestScore = $results->max('total_score');

        $lowestScore = $results->min('total_score');

        $totalAttempts = $results->count();

        return view('reports.index', compact(
            'results',
            'averageScore',
            'highestScore',
            'lowestScore',
            'totalAttempts'
        ));
    }
}