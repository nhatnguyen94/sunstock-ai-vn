<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\NewsCategoryServiceInterface;
use App\Models\NewsCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsCategoryController extends Controller
{
    public function __construct(
        protected NewsCategoryServiceInterface $newsCategoryService
    ) {}

    public function index(): View
    {
        $categories = $this->newsCategoryService->listCategories();

        return view('backend.news-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('backend.news-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:news_categories,name',
            'slug' => 'nullable|string|max:120|alpha_dash|unique:news_categories,slug',
        ], [
            'name.unique' => 'Tên danh mục này đã tồn tại.',
            'slug.alpha_dash' => 'Slug chỉ được chứa chữ, số, gạch ngang và gạch dưới.',
            'slug.unique' => 'Slug này đã tồn tại.',
        ]);

        $this->newsCategoryService->createCategory($validated);

        return redirect()->route('admin.news-categories.index')
            ->with('success', 'Danh mục đã được tạo thành công!');
    }

    // Route parameter is `news_category` (Laravel's default singular for the
    // multi-word resource name "news-categories") — must match exactly for
    // implicit route-model-binding to inject it.
    public function edit(NewsCategory $news_category): View
    {
        return view('backend.news-categories.edit', ['category' => $news_category]);
    }

    public function update(Request $request, NewsCategory $news_category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:news_categories,name,' . $news_category->id,
            'slug' => 'nullable|string|max:120|alpha_dash|unique:news_categories,slug,' . $news_category->id,
        ], [
            'name.unique' => 'Tên danh mục này đã tồn tại.',
            'slug.alpha_dash' => 'Slug chỉ được chứa chữ, số, gạch ngang và gạch dưới.',
            'slug.unique' => 'Slug này đã tồn tại.',
        ]);

        $this->newsCategoryService->updateCategory($news_category, $validated);

        return redirect()->route('admin.news-categories.index')
            ->with('success', 'Danh mục đã được cập nhật thành công!');
    }

    public function destroy(NewsCategory $news_category): RedirectResponse
    {
        if ($this->newsCategoryService->isInUse($news_category)) {
            return redirect()->route('admin.news-categories.index')
                ->with('error', 'Không thể xoá danh mục "' . $news_category->name . '" — đang có bài viết hoặc đang được nguồn RSS sử dụng.');
        }

        $this->newsCategoryService->deleteCategory($news_category);

        return redirect()->route('admin.news-categories.index')
            ->with('success', 'Danh mục đã được xoá thành công!');
    }
}
