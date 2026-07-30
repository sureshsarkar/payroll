<?php

namespace Modules\NewsLetter\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Services\MailSenderService;
use App\Http\Controllers\Controller;
use Modules\NewsLetter\app\Models\NewsLetter;
use Modules\NewsLetter\app\Jobs\SendMailToNewsletterJob;

class NewsLetterController extends Controller
{
    public function index()
    {
        checkAdminHasPermissionAndThrowException('newsletter.view');
        // 2026-06-02 (CRUD audit) — paginate unbounded subscriber list.
        $newsletters = NewsLetter::orderBy('id', 'desc')->where('status', 'verified')->paginate(20);

        return view('newsletter::index', ['newsletters' => $newsletters]);
    }

    public function create()
    {
        checkAdminHasPermissionAndThrowException('newsletter.mail');

        return view('newsletter::create');
    }

    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('newsletter.delete');
        // 2026-06-02 (CRUD audit) — findOrFail (was find): a stale/invalid id
        // returned null and 500'd on ->delete(); now a clean 404.
        $newsletter = NewsLetter::findOrFail($id);
        $newsletter->delete();

        $notification = __('Deleted successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function store(Request $request)
    {
        checkAdminHasPermissionAndThrowException('newsletter.mail');
        $request->validate([
            'subject' => 'required',
            'description' => 'required',
        ], [
            'subject.required' => __('Subject is required'),
            'description.required' => __('Description is required'),
        ]);

        (new MailSenderService)->SendMailToNewsletterFromTrait($request->subject, $request->description);

        $notification = __('Mail send successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
}
