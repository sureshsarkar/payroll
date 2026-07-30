<?php

namespace Modules\LandingPageMessage\app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\LandingPageMessage\app\Models\LandingPageMessage;

class LandingPageMessageController extends Controller
{
    public function index()
    {
        // FT-IDOR-8 fix (2026-05-27) — index() + create() were
        // ungated even though peer methods in this same class
        // already gated. Apply the same `view` permission for read
        // and gallery surfaces. NOTE: the show/destroy methods below
        // reference `landingpage.message.view|delete` (without the
        // hyphen between "landing" and "page") — those keys are NOT
        // registered in PermissionsTrait, so this controller was
        // dead-locked for everyone except Super Admin (who passes
        // every permission check by default). The keys are corrected
        // to the registered slugs `landing-page.message.view` and
        // `landing-page-message.delete`.
        checkAdminHasPermissionAndThrowException('landing-page.message.view');

        $messages = LandingPageMessage::orderBy('id', 'desc')->get();

        return view('admin/landingpagemessage::index', ['messages' => $messages]);
    }

    public function create()
    {
        checkAdminHasPermissionAndThrowException('landing-page.message.view');
        return view('landingpagemessage::create');
    }

    public function show($id)
    {
        // FT-IDOR-8 — corrected permission slug (was unregistered).
        checkAdminHasPermissionAndThrowException('landing-page.message.view');

        $message = LandingPageMessage::findOrFail($id);

        return view('landingpagemessage::show', ['message' => $message]);
    }

    public function destroy($id)
    {
        // FT-IDOR-8 — corrected permission slug (was unregistered).
        checkAdminHasPermissionAndThrowException('landing-page-message.delete');
        $message = LandingPageMessage::findOrFail($id);
        $message->delete();

        $notification = __('Deleted successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.landing-page.message')->with($notification);
    }
}
