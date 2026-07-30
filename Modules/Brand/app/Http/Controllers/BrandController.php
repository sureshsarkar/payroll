<?php

namespace Modules\Brand\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Brand\app\Models\Brand;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('brand.management');
        // 2026-06-02 (CRUD audit) — search by name.
        $search = trim((string) request('search'));
        $brands = Brand::when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->paginate(15)->withQueryString();
        return view('brand::index', compact('brands'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // FT-IDOR-9 fix (2026-05-27) — create() + edit() were the only
        // public methods on this controller without a gate, even
        // though index/store/update/destroy/statusUpdate all gate on
        // `brand.management`. Match the convention.
        checkAdminHasPermissionAndThrowException('brand.management');
        return view('brand::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        checkAdminHasPermissionAndThrowException('brand.management');
        $request->validate([
            'name' => ['required', 'max:255'],
            // FT-UPLOAD-3 (2026-05-28) — explicit mimes. Brand logos
            // render inline across the platform; an SVG with <script>
            // would XSS every visitor on every page that shows it.
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'url' => ['required', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);

        $fileName = file_upload($request->image);

        Brand::create([
            'name' => $request->name,
            'image' =>  $fileName,
            'url' =>  $request->url,
            'status' => $request->status
        ]);

        return redirect()->route('admin.brand.index')->with(['messege' => __('Created successfully'), 'alert-type' => 'success']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        // FT-IDOR-9 fix — see create().
        checkAdminHasPermissionAndThrowException('brand.management');
        $brand = Brand::findOrFail($id);
        return view('brand::edit', compact('brand'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {

        checkAdminHasPermissionAndThrowException('brand.management');
        $request->validate([
            'name' => ['required', 'max:255'],
            // FT-UPLOAD-3 (update path, 2026-05-28) — match store's
            // restrictions: explicit mimes + size cap.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', 'boolean'],
        ]);

        $brand = Brand::findOrFail($id);
        $brand->update([
            'name' => $request->name,
            'url' =>  $request->url,
            'status' => $request->status
        ]);
        if ($request->hasFile('image')) {
            $fileName = file_upload($request->image, 'uploads/custom-images/', $brand->image);
            $brand->update(['image' => $fileName]);
        }

        return redirect()->route('admin.brand.index')->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {

        checkAdminHasPermissionAndThrowException('brand.management');
        $brand = Brand::findOrFail($id);
        $brand->delete();
        return redirect()->route('admin.brand.index')->with(['messege' => __('Deleted successfully'), 'alert-type' => 'success']);
    }

    public function statusUpdate($id)
    {
        checkAdminHasPermissionAndThrowException('brand.management');
        // checkAdminHasPermissionAndThrowException('blog.category.update');
        $brand = Brand::find($id);
        $status = $brand->status == 1 ? 0 : 1;
        $brand->update(['status' => $status]);

        $notification = __('Updated Successfully');

        return response()->json([
            'success' => true,
            'message' => $notification,
        ]);
    }
}
