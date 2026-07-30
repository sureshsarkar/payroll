<?php

namespace Modules\PageTemplateBuilder\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Language\app\Models\Language;
use Modules\Language\app\Traits\GenerateTranslationTrait;
use Modules\PageTemplateBuilder\app\Models\PageTemplateCategory;

class PageTemplateCategoryController extends Controller
{
    use GenerateTranslationTrait, RedirectHelperTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('page.management');
        $pages = PageTemplateCategory::all();

        return view('pagetemplatebuilder::category.index', compact('pages'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // FT-IDOR-37 fix — same shape as the PageTemplateBuilder
        // sibling. create + show were the only ungated methods.
        checkAdminHasPermissionAndThrowException('page.management');
        return view('pagetemplatebuilder::category.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        checkAdminHasPermissionAndThrowException('page.management');
        $validated = $request->validate([
            'name' => ['required','unique:page_template_categories,name'],
        ], [
            'name.required' => __('The Category field is required.'),
            'name.unique' => __('This category already exists.'),
        ]);

       
        $insertData['name'] = $request->name; 
        $insertData['status'] = $request->status;

        PageTemplateCategory::create($insertData);

        return redirect()->route('admin.page-template-category.index')->with('success', 'Category Added');

    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        // FT-IDOR-37 fix — see create().
        checkAdminHasPermissionAndThrowException('page.management');
        return view('pagetemplatebuilder::category.show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        checkAdminHasPermissionAndThrowException('page.management');

        // $code = request('code') ?? getSessionLanguage();

        // abort_unless(Language::where('code', $code)->exists(), 404);

        $page = PageTemplateCategory::findOrFail($id);
        // $languages = allLanguages();

        return view('pagetemplatebuilder::category.edit', compact('page'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('page.management');

          $validated = $request->validate([
            'name' => ['required','unique:page_template_categories,name,' . $id],
        ], [
            'name.required' => __('The category field is required.'),
            'name.unique' => __('This category already exists.'),
        ]);

        $page = PageTemplateCategory::findOrFail($id); 

        $updateData['name'] = $request->name;
        $updateData['status'] = $request->status;

        $page->update($updateData);

        return redirect()->route('admin.page-template-category.index')->with('success', 'Template Updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('page.management');

        $page = PageTemplateCategory::findOrFail($id);

        // ✅ Delete Image
        if (! empty($page->image) && file_exists(public_path($page->image))) {
            unlink(public_path($page->image));
        }

        // ✅ Delete File
        if (! empty($page->file) && file_exists(public_path($page->file))) {
            unlink(public_path($page->file));
        }

        $page->delete();

        return redirect()->route('admin.page-template-category.index')->with('success', 'Template Added');

    }

    public function statusUpdate($id)
    {
        checkAdminHasPermissionAndThrowException('page.management');

        $page = PageTemplateCategory::find($id);
        $status = $page->status == 1 ? 0 : 1;
        $page->update(['status' => $status]);

        $notification = __('Updated Successfully');

        return response()->json([
            'success' => true,
            'message' => $notification,
        ]);
    }
}
