<?php

namespace Modules\CertificateBuilder\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\CertificateBuilder\app\Http\Requests\CertificateUpdateRequest;
use Modules\CertificateBuilder\app\Models\CertificateBuilder;
use Modules\CertificateBuilder\app\Models\CertificateBuilderItem;

class CertificateBuilderController extends Controller
{
    /**
     * FT-IDOR-6 fix (2026-05-27) — controller had no permission gates.
     * Routes here are auth:admin gated globally, but a read-only
     * sub-admin could reach the certificate template editor and
     * overwrite the global certificate (it's a single-row table —
     * updateOrCreate ['id' => 1]) used by every course. Gate the
     * whole class via constructor middleware on `course.certificate.management`
     * (registered in PermissionsTrait::$CertificatePermission).
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            checkAdminHasPermissionAndThrowException('course.certificate.management');
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $certificate = CertificateBuilder::first();
        $certificateItems = CertificateBuilderItem::all();
        return view('certificatebuilder::index', compact('certificate', 'certificateItems'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('certificatebuilder::create');
    }

    function updateItem(Request $request) {

       CertificateBuilderItem::updateOrCreate(
           ['element_id' => $request->element_id],
           [
            'x_position' => $request->x_position,
            'y_position' => $request->y_position
           ]
        );

        return response(['status' => 'success', 'message' => __('Updated successfully')]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CertificateUpdateRequest $request, $id)
    {
        $certificate = CertificateBuilder::updateOrCreate(
            ['id' => 1],
            $request->validated()
        );
        if($request->hasFile('background')){
            $file_name = file_upload($request->background, 'uploads/custom-images/', $certificate->image);
            $certificate->background = $file_name;
            $certificate->save();
        }
        if($request->hasFile('signature')){
            $file_name = file_upload($request->signature, 'uploads/custom-images/', $certificate->signature);
            $certificate->signature = $file_name;
            $certificate->save();
        }

        return redirect()->back()->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }
}
