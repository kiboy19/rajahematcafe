<?php

namespace App\Http\Controllers;

use App\Services\RecordPageViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAnalyticsController extends Controller
{
    public function store(Request $request, RecordPageViewService $recorder): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $request->merge($validated);
        $recorder->record($request);

        return response()->json(['message' => 'Kunjungan dicatat.'], 202);
    }
}
