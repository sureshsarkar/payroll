<?php

namespace Modules\LandingPageMessage\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\LandingPageMessage\app\Models\LandingPageMessage;

class LandingPageMessageController extends Controller
{
    /**
     * FT-IDOR-8 fix (2026-05-27) — all four public methods had their
     * permission gates COMMENTED OUT (or simply omitted on index +
     * create). routes/web.php binds this controller (not the Admin\
     * namespace sibling) so every method here was reachable by any
     * authenticated admin / sub-admin including read-only roles.
     *
     * The Admin\ namespace copy of this controller has gates on
     * show() + destroy() — they were clearly intended for this one
     * too and got commented out during dev without being re-enabled.
     *
     * Permission keys are taken from PermissionsTrait::$LandingPageMessagePermissions:
     *   'landing-page.message.view'   (covers index + create + show)
     *   'landing-page-message.delete' (covers destroy)
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('landing-page.message.view');

        $messages = LandingPageMessage::with('coachname')->orderBy('id', 'desc')->get();

        return view('landingpagemessage::index', ['messages' => $messages]);
    }

    public function create()
    {
        checkAdminHasPermissionAndThrowException('landing-page.message.view');
        return view('landingpagemessage::create');
    }

    public function show($id)
    {
        checkAdminHasPermissionAndThrowException('landing-page.message.view');

        $message = LandingPageMessage::findOrFail($id);

        return view('landingpagemessage::show', ['message' => $message]);
    }

    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('landing-page-message.delete');

        $message = LandingPageMessage::findOrFail($id);
        $message->delete();

        $notification = __('Deleted successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.landing-page.message')->with($notification);
    }
}
