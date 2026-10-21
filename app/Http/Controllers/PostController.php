<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Controllers\Concerns\HandlesUploadedImages;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\AuditLog;
use App\Models\Post;
use App\Services\ContentSanitizer;
use App\Services\SlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    use HandlesUploadedImages;

    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->with('author:id,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($nested) => $nested->where('title', 'like', $search)->orWhere('excerpt', 'like', $search));
            })
            ->latest('updated_at')
            ->paginate(15);

        return response()->json($posts);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json(['data' => $post->load('author:id,name')]);
    }

    public function store(StorePostRequest $request, ContentSanitizer $sanitizer, SlugGenerator $slugGenerator): RedirectResponse|JsonResponse
    {
        $post = DB::transaction(function () use ($request, $sanitizer, $slugGenerator): Post {
            $validated = $request->validated();
            $content = $sanitizer->clean($validated['content']);
            $status = PostStatus::from($validated['status']);

            $post = Post::query()->create([
                'author_id' => $request->user()->id,
                'title' => trim($validated['title']),
                'slug' => $slugGenerator->forPost($validated['title']),
                'category' => $validated['category'],
                'excerpt' => $validated['excerpt'] ?? $sanitizer->toPlainText($content, 300),
                'content' => $content,
                'status' => $status,
                'published_at' => $status === PostStatus::Published
                    ? ($validated['published_at'] ?? now())
                    : null,
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);

            $this->replaceImage($request->file('cover_image'), $post, 'cover_image_path', 'posts');
            $post->save();

            AuditLog::record('post.created', $post, after: $post->toArray(), actor: $request->user(), ipAddress: $request->ip());

            return $post;
        });

        return $this->respond($request, $post, 'Artikel berhasil dibuat.');
    }

    public function update(UpdatePostRequest $request, Post $post, ContentSanitizer $sanitizer, SlugGenerator $slugGenerator): RedirectResponse|JsonResponse
    {
        $before = $post->toArray();
        $validated = $request->validated();
        $status = PostStatus::from($validated['status']);

        $post->fill([
            'title' => trim($validated['title']),
            'slug' => $slugGenerator->forPost($validated['title'], $post->id),
            'category' => $validated['category'],
            'excerpt' => $validated['excerpt'] ?? $sanitizer->toPlainText($validated['content'], 300),
            'content' => $sanitizer->clean($validated['content']),
            'status' => $status,
            'published_at' => $status === PostStatus::Published
                ? ($validated['published_at'] ?? $post->published_at ?? now())
                : null,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ]);

        $this->replaceImage($request->file('cover_image'), $post, 'cover_image_path', 'posts');

        if ($request->boolean('remove_cover_image') && ! $request->hasFile('cover_image')) {
            $this->removeImage($post, 'cover_image_path');
        }

        $post->save();
        AuditLog::record('post.updated', $post, before: $before, after: $post->fresh()->toArray(), actor: $request->user(), ipAddress: $request->ip());

        return $this->respond($request, $post->fresh(), 'Artikel berhasil diperbarui.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $post);
        $before = $post->toArray();
        $this->removeImage($post, 'cover_image_path');
        $post->delete();
        AuditLog::record('post.deleted', $post, before: $before, actor: $request->user(), ipAddress: $request->ip());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Artikel berhasil dihapus.']);
        }

        return back()->with('success', 'Artikel berhasil dihapus.');
    }

    public function publish(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $post);
        $post->update(['status' => PostStatus::Published, 'published_at' => $post->published_at ?? now()]);

        return $this->respond($request, $post->fresh(), 'Artikel diterbitkan.');
    }

    public function draft(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $post);
        $post->update(['status' => PostStatus::Draft, 'published_at' => null]);

        return $this->respond($request, $post->fresh(), 'Artikel dikembalikan menjadi draf.');
    }

    private function respond(Request $request, Post $post, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => $post, 'message' => $message]);
        }

        return redirect()->route('posts.index')->with('success', $message);
    }
}
