<?php

namespace Modules\Marquee\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Marquee\app\Models\Marquee;

class MarqueeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('brand.management');

        // 2026-06-02 (CRUD audit) — search by name.
        $search = trim((string) request('search'));
        $marquees = Marquee::when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->paginate(15)->withQueryString();
        return view('marquee::index', compact('marquees'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // FT-IDOR-15 fix (2026-05-28) — create/show/edit lacked the
        // `brand.management` gate that index/store/update/destroy/
        // statusUpdate all use. Same pattern as FT-IDOR-7 (PageBuilder)
        // and FT-IDOR-9 (Brand) — read-only forms still belong on the
        // gate so a sub-admin without brand.management can't reach
        // them.
        checkAdminHasPermissionAndThrowException('brand.management');
        return view('marquee::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        checkAdminHasPermissionAndThrowException('brand.management');

        $request->validate([
            'name' => ['required', 'max:255'],
            // 'image' => ['required', 'image', 'max:2048'],
            'type' => ['required'],
            // 'url' => ['required', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $fileName = file_upload($request->image);
        }else{
            $fileName = '';
        }

        Marquee::create([
            'name' => $request->name,
            'image' =>  $fileName,
            'type'  => $request->type,
            'status' => $request->status
        ]);

        return redirect()->route('admin.marquee.index')->with(['messege' => __('Created successfully'), 'alert-type' => 'success']);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        // FT-IDOR-15 fix — see create().
        checkAdminHasPermissionAndThrowException('brand.management');
        return view('marquee::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        // FT-IDOR-15 fix — see create().
        checkAdminHasPermissionAndThrowException('brand.management');
        $marquee = Marquee::findOrFail($id);
        return view('marquee::edit',compact('marquee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
        checkAdminHasPermissionAndThrowException('brand.management');
        $request->validate([
            'name' => ['required', 'max:255'],
            // 'image' => ['nullable', 'image'],
            'type' => ['required'],
            'status' => ['required', 'boolean'],
        ]);

        $marquee = Marquee::findOrFail($id);
        $marquee->update([
            'name' => $request->name,
            // 'url' =>  $request->url,
            'type' => $request->type,
            'status' => $request->status
        ]);
        if ($request->hasFile('image')) {
            $fileName = file_upload($request->image, 'uploads/custom-images/', $marquee->image);
            $marquee->update(['image' => $fileName]);
        }

        return redirect()->route('admin.brand.index')->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
        checkAdminHasPermissionAndThrowException('brand.management');
        $marquee = Marquee::findOrFail($id);
        $marquee->delete();
        return redirect()->route('admin.brand.index')->with(['messege' => __('Deleted successfully'), 'alert-type' => 'success']);
    }

    public function statusUpdate($id)
    {
        checkAdminHasPermissionAndThrowException('brand.management');
        // checkAdminHasPermissionAndThrowException('blog.category.update');
        $marquee = Marquee::findOrFail($id);
        $status = $marquee->status == 1 ? 0 : 1;
        $marquee->update(['status' => $status]);

        $notification = __('Updated Successfully');

        return response()->json([
            'success' => true,
            'message' => $notification,
        ]);
    }
}
