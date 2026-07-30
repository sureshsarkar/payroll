<?php

namespace Modules\ContactMessage\app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\ContactMessage\app\Models\ContactMessage;

class ContactMessageController extends Controller
{
    public function index()
    {
        // 2026-06-02 (CRUD audit) — gate the listing. It exposes visitor PII
        // (name / email / message); show() and destroy() already require this
        // permission, but index() was missing it, so a sub-admin WITHOUT
        // contect.message.view could enumerate every contact submission.
        // Also paginate — this is unbounded user-generated data.
        checkAdminHasPermissionAndThrowException('contect.message.view');

        $messages = ContactMessage::orderBy('id', 'desc')->paginate(20);

        return view('contactmessage::index', ['messages' => $messages]);
    }

    public function show($id)
    {
        checkAdminHasPermissionAndThrowException('contect.message.view');

        $message = ContactMessage::findOrFail($id);

        return view('contactmessage::show', ['message' => $message]);
    }

    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('contect.message.delete');
        $message = ContactMessage::findOrFail($id);
        $message->delete();

        $notification = __('Deleted successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.contact-messages')->with($notification);
    }
}
