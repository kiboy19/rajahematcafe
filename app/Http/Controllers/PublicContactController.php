<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Honeypot check
        if ($request->filled('website')) {
            return response()->json(['message' => 'Spam detected'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        ContactMessage::create([
            ...$validated,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Pesan terkirim'], 201);
    }
}
