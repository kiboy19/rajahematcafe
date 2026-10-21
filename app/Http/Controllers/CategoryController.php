<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesUploadedImages;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use HandlesUploadedImages;

    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('menuItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $category = Category::query()->create([
            'name' => trim($request->validated('name')),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'image_path' => $request->hasFile('image') ? $this->storeImage($request->file('image'), 'categories') : null,
        ]);

        AuditLog::record('category.created', $category, after: [
            'name' => $category->name,
            'is_active' => $category->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $category, 'message' => 'Kategori berhasil ditambahkan.'], 201);
        }

        return redirect()->route('categories.index')->with('success', 'Kategori '.$category->name.' berhasil ditambahkan.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        $before = [
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'image_path' => $category->image_path,
        ];

        $category->update([
            'name' => trim($request->validated('name')),
            'sort_order' => (int) ($request->validated('sort_order') ?? $category->sort_order),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        $this->replaceImage($request->file('image'), $category, 'image_path', 'categories');

        if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
            $this->removeImage($category, 'image_path');
        }

        if ($category->isDirty()) {
            $category->save();
        }

        AuditLog::record('category.updated', $category, before: $before, after: [
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'image_path' => $category->image_path,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $category->fresh(), 'message' => 'Kategori diperbarui.']);
        }

        return redirect()->route('categories.index')->with('success', 'Kategori '.$category->name.' berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $category);

        $beforeStatus = $category->is_active;
        $category->update(['is_active' => ! $beforeStatus]);

        AuditLog::record('category.status_changed', $category, before: ['is_active' => $beforeStatus], after: ['is_active' => $category->is_active], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $category->fresh(), 'message' => 'Status kategori diperbarui.']);
        }

        return back()->with('success', 'Status kategori '.$category->name.' diubah menjadi '.($category->is_active ? 'Aktif' : 'Nonaktif').'.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->menuItems()->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Kategori masih memiliki menu yang terhubung.'], 422);
            }

            return back()->withErrors(['category' => 'Kategori masih memiliki menu yang terhubung. Nonaktifkan kategori sebagai gantinya.']);
        }

        $name = $category->name;
        $this->removeImage($category, 'image_path');
        $category->delete();
        AuditLog::record('category.deleted', null, before: ['name' => $name], reason: 'Kategori dihapus oleh staf berwenang', actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Kategori dihapus.']);
        }

        return redirect()->route('categories.index')->with('success', 'Kategori '.$name.' berhasil dihapus.');
    }
}
