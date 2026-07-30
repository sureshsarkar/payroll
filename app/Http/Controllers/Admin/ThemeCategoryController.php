<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Theme category taxonomy CRUD (Super Admin only).
 * Categories drive the filter pills on the coach onboarding gallery.
 */
class ThemeCategoryController extends Controller
{
    public function index()
    {
        checkAdminHasPermissionAndThrowException('theme.view');
        $categories = ThemeCategory::withCount('themes')->orderBy('sort_order')->get();
        return view('admin.theme-studio.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        checkAdminHasPermissionAndThrowException('theme.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:60'],
        ]);
        ThemeCategory::create([
            'name'       => $data['name'],
            'slug'       => Str::slug($data['name']),
            'icon'       => $data['icon'] ?? 'fa-solid fa-tag',
            'sort_order' => (int) ThemeCategory::max('sort_order') + 1,
        ]);
        return back()->with('success', __('Category created.'));
    }

    public function update(Request $request, int $id)
    {
        checkAdminHasPermissionAndThrowException('theme.update');
        $category = ThemeCategory::findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $category->update($data);
        return back()->with('success', __('Category updated.'));
    }

    public function destroy(int $id)
    {
        checkAdminHasPermissionAndThrowException('theme.delete');
        $category = ThemeCategory::findOrFail($id);
        $category->delete();
        return back()->with('success', __('Category deleted.'));
    }
}
