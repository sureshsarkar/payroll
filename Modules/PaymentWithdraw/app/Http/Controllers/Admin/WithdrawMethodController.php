<?php

namespace Modules\PaymentWithdraw\app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\MailSenderTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Modules\GlobalSetting\app\Models\EmailTemplate;
use Modules\PaymentWithdraw\app\Emails\WithdrawApprovalMail;
use Modules\PaymentWithdraw\app\Jobs\WithdrawApprovalJob;
use Modules\PaymentWithdraw\app\Models\WithdrawMethod;
use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

class WithdrawMethodController extends Controller
{

    use MailSenderTrait;

    public function index(Request $request)
    {
        // FT-IDOR-23 fix (2026-05-28) — was completely ungated.
        // Every method in this controller manages withdrawal methods
        // (the bank channels coaches use to cash out their wallet
        // balance) or processes withdrawal REQUESTS (which transfer
        // real money out of the platform). The `withdraw.management`
        // permission slug exists in PermissionsTrait::$withdrawPermission
        // but no method checked it — any authenticated admin (e.g.
        // a Content Editor role with only blog.* permissions) could
        // approve / reject / delete withdrawals.
        checkAdminHasPermissionAndThrowException('withdraw.management');

        $query = WithdrawMethod::query();

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            $q->where('name', 'like', '%' . $request->keyword . '%')
                ->orWhere('description', 'like', '%' . $request->keyword . '%')
                ->orWhere('min_amount', 'like', '%' . $request->keyword . '%')
                ->orWhere('max_amount', 'like', '%' . $request->keyword . '%');
        });
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        $query->when($request->filled('user'), function ($q) use ($request) {
            $q->where('user_id', $request->user);
        });

        $query->when($request->filled('order_by'), function ($q) use ($request) {
            $q->orderBy('id', $request->order_by == 1 ? 'asc' : 'desc');
        });

        if ($request->filled('par-page')) {
            $methods = $request->get('par-page') == 'all' ? $query->get() : $query->paginate($request->get('par-page'))->withQueryString();
        } else {
            $methods = $query->paginate()->withQueryString();
        }

        return view('paymentwithdraw::admin.method.index', compact('methods'));
    }

    public function create()
    {
        // FT-IDOR-23 fix — see index().
        checkAdminHasPermissionAndThrowException('withdraw.management');
        return view('paymentwithdraw::admin.method.create');
    }

    public function store(Request $request)
    {
        // FT-IDOR-23 fix — see index().
        checkAdminHasPermissionAndThrowException('withdraw.management');

        // FT-VAL-2 (extension) — bound amounts to 0..9,999,999 so a
        // misconfigured row can't be saved with min/max = -1 or with
        // min > max (causes the user-facing form to reject every
        // withdrawal silently).
        $rules = [
            'name'           => 'required|string|max:190',
            'minimum_amount' => 'required|numeric|min:0|max:9999999',
            'maximum_amount' => 'required|numeric|min:0|max:9999999|gte:minimum_amount',
            'description'    => 'required|string|max:2000',
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'minimum_amount.required' => __('Min amount is required'),
            'maximum_amount.required' => __('Max amount is required'),
            'description.required' => __('Description is required'),
        ];
        $request->validate($rules, $customMessages);

        $method = new WithdrawMethod();
        $method->name = $request->name;
        $method->min_amount = $request->minimum_amount;
        $method->max_amount = $request->maximum_amount;
        $method->description = $request->description;
        $method->status = $request->status;
        $method->save();

        $notification = __('Create Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.withdraw-method.index')->with($notification);
    }

    public function edit($id)
    {
        // FT-IDOR-23 fix — see index().
        checkAdminHasPermissionAndThrowException('withdraw.management');
        $method = WithdrawMethod::find($id);

        return view('paymentwithdraw::admin.method.edit', compact('method'));
    }

    public function update(Request $request, $id)
    {
        // FT-IDOR-23 fix — see index().
        checkAdminHasPermissionAndThrowException('withdraw.management');

        // FT-VAL-2 (extension) — same bounds as store().
        $rules = [
            'name'           => 'required|string|max:190',
            'minimum_amount' => 'required|numeric|min:0|max:9999999',
            'maximum_amount' => 'required|numeric|min:0|max:9999999|gte:minimum_amount',
            'description'    => 'required|string|max:2000',
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'minimum_amount.required' => __('Min amount is required'),
            'maximum_amount.required' => __('Max amount is required'),
            'description.required' => __('Description is required'),
        ];

        $this->validate($request, $rules, $customMessages);

        $method = WithdrawMethod::find($id);
        $method->name = $request->name;
        $method->min_amount = $request->minimum_amount;
        $method->max_amount = $request->maximum_amount;
        $method->description = $request->description;
        $method->status = $request->status;
        $method->save();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.withdraw-method.index')->with($notification);
    }

    public function destroy($id)
    {
        // FT-IDOR-23 fix — see index().
        checkAdminHasPermissionAndThrowException('withdraw.management');

        $method = WithdrawMethod::find($id);
        $method->delete();

        $notification = __('Delete Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.withdraw-method.index')->with($notification);
    }

    public function withdraw_list(Request $request)
    {
        // FT-IDOR-23 fix — see index().
        checkAdminHasPermissionAndThrowException('withdraw.management');

        $query = WithdrawRequest::query();
        $query->with('user');

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            $q->where('method', 'like', '%' . $request->keyword . '%')
                ->orWhere('withdraw_amount', 'like', '%' . $request->keyword . '%')
                ->orWhere('account_info', 'like', '%' . $request->keyword . '%');
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        $query->when($request->filled('user'), function ($q) use ($request) {
            $q->where('user_id', $request->user);
        });

        $query->orderBy('id', $request->order_by == 1 ? 'asc' : 'desc');

        if ($request->filled('par-page')) {
            $withdraws = $request->get('par-page') == 'all' ? $query->get() : $query->paginate($request->get('par-page'))->withQueryString();
        } else {
            $withdraws = $query->paginate()->withQueryString();
        }

        $title = __('Withdraw request');
        $users = User::select('name', 'id')->get();

        return view('paymentwithdraw::admin.index', compact('withdraws', 'title', 'users'));
    }

    public function show_withdraw($id)
    {
        // FT-IDOR-23 fix — see index().
        checkAdminHasPermissionAndThrowException('withdraw.management');

        $withdraw = WithdrawRequest::find($id);

        return view('paymentwithdraw::admin.show', compact('withdraw'));
    }

    public function destroy_withdraw($id)
    {
        // FT-IDOR-23 fix — see index(). Withdrawal-row deletes
        // can hide audit trails of money movement; gate this tight.
        checkAdminHasPermissionAndThrowException('withdraw.management');

        $withdraw = WithdrawRequest::findOrFail($id);
        $withdraw->delete();

        $notification = __('Delete Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.withdraw-list')->with($notification);
    }

    public function update_withdraw(Request $request, $id)
    {
        // FT-IDOR-23 fix — see index(). update_withdraw IS the money
        // movement path: approving a withdrawal decrements the
        // recipient's wallet_balance under a transactional lock.
        // Allowing arbitrary admins to call this lets a hypothetical
        // Content Editor approve a fake withdrawal and drain coach
        // wallets to a colluding bank account. The locking + status
        // transition logic is already correct; this commit only adds
        // the missing authorization check.
        checkAdminHasPermissionAndThrowException('withdraw.management');

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ], ['status.required' => __('Select a status to update withdraw request')]);

        // Atomic transition + wallet decrement. Pessimistic lock prevents two
        // admins approving the same row concurrently or re-approving a row
        // whose status was manually flipped back to pending.
        [$withdraw, $user, $approvedNow] = \DB::transaction(function () use ($request, $id) {
            $withdraw = WithdrawRequest::lockForUpdate()->findOrFail($id);
            $previousStatus = $withdraw->status;

            $approvedNow = $previousStatus !== 'approved' && $request->status === 'approved';
            $unapprovedNow = $previousStatus === 'approved' && $request->status !== 'approved';

            $withdraw->status = $request->status;
            $withdraw->approved_date = date('Y-m-d');
            $withdraw->save();

            $user = User::lockForUpdate()->findOrFail($withdraw->user_id);

            if ($approvedNow) {
                if ($user->wallet_balance < $withdraw->withdraw_amount) {
                    throw new \RuntimeException(__('Insufficient wallet balance'));
                }
                $user->decrement('wallet_balance', $withdraw->withdraw_amount);
            } elseif ($unapprovedNow) {
                $user->increment('wallet_balance', $withdraw->withdraw_amount);
            }

            return [$withdraw, $user->refresh(), $approvedNow];
        });

        // send mail
        $this->sendMail($user, $request->status);

        try {
            if ($request->status === 'approved') {
                $user->notify(new \App\Notifications\WithdrawalApprovedToCoach($withdraw));
            } elseif ($request->status === 'rejected') {
                $user->notify(new \App\Notifications\WithdrawalRejectedToCoach($withdraw));
            }
        } catch (\Throwable $e) {
            \Log::warning('Notify coach of withdrawal status change failed: ' . $e->getMessage());
        }

        $notification = __('Withdraw request approval successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.withdraw-list')->with($notification);
    }

    function sendMail($user, $status = 'pending')
    {
        // set mail conf
        $this->setMailConfig();

        $templateName = $status == 'approved' ? 'approved_withdraw' : 'rejected_withdraw';
        $template = EmailTemplate::where('name', $templateName)->first();
        $message = $template->message;
        $message = str_replace('{{user_name}}', $user->name, $message);
        $subject = $template->subject;
        if ($this->isQueable()) {
            dispatch(new WithdrawApprovalJob($user, $status));
        } else {
            try {
                Mail::to($user->email)->send(new WithdrawApprovalMail($subject, $message));
            } catch (Exception $ex) {
                logger($ex);
            }
        }
    }
}
