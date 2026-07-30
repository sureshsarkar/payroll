<?php

namespace Modules\PageTemplateBuilder\app\Http\Controllers;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Language\app\Models\Language;
use Modules\Language\app\Traits\GenerateTranslationTrait;
use Modules\PageTemplateBuilder\app\Models\PageTemplateBuilder;
use Modules\PageTemplateBuilder\app\Models\PageTemplateCategory;

class PageTemplateBuilderController extends Controller
{
    use GenerateTranslationTrait, RedirectHelperTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('page.management');
        $pages = PageTemplateBuilder::all();

        return view('pagetemplatebuilder::index', compact('pages'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // FT-IDOR-37 fix (2026-05-28) — create + show were the only
        // methods on this controller without the `page.management`
        // gate that index/store/edit/update/destroy/statusUpdate all
        // use. Same shape as FT-IDOR-7 (PageBuilder), FT-IDOR-9
        // (Brand), and FT-IDOR-15 (Marquee/SocialLink).
        checkAdminHasPermissionAndThrowException('page.management');
        $category= PageTemplateCategory::active()->get();
        return view('pagetemplatebuilder::create', compact('category'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        checkAdminHasPermissionAndThrowException('page.management');
        $validated = $request->validate([
            'category' => ['required'],
        ], [
            'category.required' => __('The Category field is required.'),

        ]);

        if ($request->hasFile('image')) {
            $myimage = file_upload($request->image, 'uploads/page-template-builder/', null);

        }

        if ($request->hasFile('file')) {
            $myfile = file_template_upload($request->file, 'uploads/page-template-builder/', null);
        }
        $insertData['category'] = $request->category;
        $insertData['template_name'] = $request->template_name;
        $insertData['image'] = $myimage ?? null;
        $insertData['file'] = $myfile ?? null;
        $insertData['status'] = $request->status;

        PageTemplateBuilder::create($insertData);

        return redirect()->route('admin.page-template-builder.index')->with('success','Template Added'); 

    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        // FT-IDOR-37 fix — see create().
        checkAdminHasPermissionAndThrowException('page.management');
        return view('pagetemplatebuilder::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        checkAdminHasPermissionAndThrowException('page.management');

        // $code = request('code') ?? getSessionLanguage();

        // abort_unless(Language::where('code', $code)->exists(), 404);
        $category= PageTemplateCategory::active()->get();

        $page = PageTemplateBuilder::findOrFail($id);
        // $languages = allLanguages();

        return view('pagetemplatebuilder::edit', compact('page','category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('page.management');

        $validated = $request->validate([
            'category' => ['required'],
        ], [
            'category.required' => __('The Category field is required.'),

        ]);

        $page = PageTemplateBuilder::findOrFail($id);

         if ($request->hasFile('image')) {
            $myimage = file_upload($request->image, 'uploads/page-template-builder/', $page->image??null); 
        }

        if ($request->hasFile('file')) {
            $myfile = file_template_upload($request->file, 'uploads/page-template-builder/', $page->file??null);
        }


        $updateData['category'] = $request->category;
        $updateData['template_name'] = $request->template_name;
        $updateData['image'] = $myimage ?? $page->image??null;
        $updateData['file'] = $myfile ??  $page->file??null;
        $updateData['status'] = $request->status;

        $page->update($updateData);


       return redirect()->route('admin.page-template-builder.index')->with('success','Template Updated'); 
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('page.management');

        $page = PageTemplateBuilder::findOrFail($id);

          // ✅ Delete Image
        if (!empty($page->image) && file_exists(public_path($page->image))) {
            unlink(public_path($page->image));
        }

        // ✅ Delete File
        if (!empty($page->file) && file_exists(public_path($page->file))) {
            unlink(public_path($page->file));
        } 
        
        $page->delete();

        return redirect()->route('admin.page-template-builder.index')->with('success','Template Added'); 

    }

    public function statusUpdate($id)
    {
        checkAdminHasPermissionAndThrowException('page.management');

        $page = PageTemplateBuilder::find($id);
        $status = $page->status == 1 ? 0 : 1;
        $page->update(['status' => $status]);

        $notification = __('Updated Successfully');

        return response()->json([
            'success' => true,
            'message' => $notification,
        ]);
    }
}
