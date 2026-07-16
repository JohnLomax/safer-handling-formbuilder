<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingMatrixEntry;
use Illuminate\Http\JsonResponse;

class TrainingMatrixController extends Controller
{
    public function index(): JsonResponse
    {
        $matrix = TrainingMatrixEntry::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TrainingMatrixEntry $entry) => $entry->toMatrixRow())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'matrix' => $matrix,
        ]);
    }
}
