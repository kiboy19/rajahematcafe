<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostMediaController extends Controller
{
    /**
     * Unggah gambar inline untuk isi artikel (dipakai tombol image di Tiptap).
     * Hasil berupa URL publik yang langsung disisipkan editor.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('image')->store('posts/inline', 'public');

        return response()->json([
            'data' => [
                'url' => Storage::disk('public')->url($path),
                'path' => $path,
            ],
        ], 201);
    }
}
