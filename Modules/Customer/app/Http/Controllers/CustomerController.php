<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Models\UserEducation;
use App\Models\UserExperience;
use App\Services\MailSenderService;
use App\Traits\GetGlobalInformationTrait;
use App\Traits\RedirectHelperTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Customer\app\Models\BannedHistory;
use Modules\Location\app\Models\City;
use Modules\Location\app\Models\State;
use Modules\Order\app\Models\Order;
use Modules\Subscription\app\Models\Subscription;
use Modules\Subscription\app\Models\SubscriptionHistory;

class CustomerController extends Controller
{
    use GetGlobalInformationTrait, RedirectHelperTrait;

    public function createCustomer()
    {
        // FT-IDOR-3 fix (2026-05-27) — un-commented this check.
        // The form-render endpoint requires the same permission as
        // its target store endpoint; otherwise a sub-admin without
        // customer.create can still browse the form (and may post
        // to /store directly to test whether the gate exists).
        checkAdminHasPermissionAndThrowException('customer.create');
        return view('customer::create-customer');
    }

    public function storeCustomer(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.store');
        $rules = [
            'name'  => 'required|string|max:190',
            // FT-VAL-1 fix (2026-05-27) — added `email|max:190`. Without
            // the type rule the admin-side create form accepted any
            // string and saved it as a User. Self-service signup
            // already requires the email rule; admin path must match.
            'email' => 'required|email|max:190|unique:users,email',
            // FT-AUTH-7 fix (2026-05-27) — bumped min:4 → min:8.
            // P1 audit fixed the admin model + register; this customer/
            // instructor module store was missed in P1.
            'password' => 'required|min:8',
            'status' => 'required',
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.email'    => __('Please enter a valid email address'),
            'status.required' => __('Status is required'),
            'email.unique' => __('Email already exist'),
            'password.required' => __('Password is required'),
            'password.min' => __('Password must be at least 8 characters'),
        ];
        $this->validate($request, $rules, $customMessages);

        // create user and send email
        $this->createUserAndSendLoginCredentialMail($request);

        return $this->redirectWithMessage(RedirectType::CREATE->value, 'admin.all-customers');
    }

    public function createInstructor()
    {
        checkAdminHasPermissionAndThrowException('customer.create');

        return view('customer::create-instructor');
    }

    public function storeInstructor(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.store');
        $rules = [
            'username' => 'required|string|max:190',
            'name'     => 'required|string|max:190',
            // FT-VAL-1 fix (2026-05-27) — see storeCustomer above.
            'email'    => 'required|email|max:190|unique:users,email',
            // FT-AUTH-7 fix (2026-05-27) — bumped min:4 → min:8.
            // P1 audit fixed the admin model + register; this customer/
            // instructor module store was missed in P1.
            'password' => 'required|min:8',
            'status' => 'required',
        ];
        $customMessages = [
            'username.required' => __('Username is required'),
            'name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.email'    => __('Please enter a valid email address'),
            'status.required' => __('Status is required'),
            'email.unique' => __('Email already exist'),
            'password.required' => __('Password is required'),
            'password.min' => __('Password must be at least 8 characters'),
        ];
        $this->validate($request, $rules, $customMessages);

        // create user and send email
        $this->createUserAndSendLoginCredentialMail($request, 'instructor');

        return $this->redirectWithMessage(RedirectType::CREATE->value, 'admin.all-instructors');
    }

    /**
     * Create a user and send login credential email.
     *
     * @param  \Illuminate\Http\Request  $request  Incoming request with user details.
     * @param  string  $user_type  User type (default: 'student').
     */
    private function createUserAndSendLoginCredentialMail($request, string $user_type = 'student'): void
    {
        // Create the user

       $username = ($user_type !='student')?$request->username:Str::slug($request->name);
        $user = User::create([
            'role' => $user_type,
            'username' => $username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => $request->status,
            'email_verified_at' => now(),
        ]);

        $app_name = cache()->get('setting')?->app_name ?? config('app.name');
        $login_url = route('login');
        $contact_url = route('contact.index');
        $subject = "Welcome to {$app_name} - Your Login Information";

        $message = <<<EOD
        <p><strong>Welcome to {$app_name}!</strong></p><p>We are excited to have you onboard as a {$user->role}.</p><p>Below are your login credentials to access your account:</p><p><strong>Website URL:</strong> <a href="{$login_url}" target="_blank">Click here to log in</a></p><p><strong>Email:</strong> {$user->email}</p><p><strong>Password:</strong> {$request->password}</p>
        <p>If you have any questions or need assistance, feel free to <a href="{$contact_url}" target="_blank">contact us</a>.</p>
        EOD;

        // Send the email
        (new MailSenderService)->sendMailToUserFromTrait($subject, $message, 'single_user', $user);
    }

    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.view');

        $query = User::query();

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            // 2026-06-25 — wrap the OR group so the keyword terms can't escape the
            // role/status AND constraints applied later (SQL AND binds tighter than
            // OR). Pre-fix, searching e.g. instructors could surface students/other
            // roles because only the last OR term was bound to the role filter.
            $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->keyword.'%')
                    ->orWhere('email', 'like', '%'.$request->keyword.'%')
                    ->orWhere('phone', 'like', '%'.$request->keyword.'%')
                    ->orWhere('address', 'like', '%'.$request->keyword.'%');
            });
        });

        $query->when($request->filled('verified'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                if ($request->verified == 1) {
                    $query->whereNotNull('email_verified_at');
                } elseif ($request->verified == 0) {
                    $query->whereNull('email_verified_at');
                }
            });
        });

        $query->when($request->filled('banned'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                if ($request->banned == 1) {
                    $query->where('is_banned', 'yes');
                } elseif ($request->banned == 0) {
                    $query->where('is_banned', 'no');
                }
            });
        });
        $query->where('role', 'student');
        $orderBy = $request->filled('order_by') && $request->order_by == 1 ? 'asc' : 'desc';

        if ($request->filled('par-page')) {
            $users = $request->get('par-page') == 'all' ? $query->orderBy('id', $orderBy)->get() : $query->orderBy('id', $orderBy)->paginate($request->get('par-page'))->withQueryString();
        } else {
            $users = $query->orderBy('id', $orderBy)->paginate()->withQueryString();
        }

        return view('customer::all_customer')->with([
            'users' => $users,
        ]);
    }

    public function allInstructors(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.view');

        $query = User::query();

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            // 2026-06-25 — wrap the OR group so the keyword terms can't escape the
            // role/status AND constraints applied later (SQL AND binds tighter than
            // OR). Pre-fix, searching e.g. instructors could surface students/other
            // roles because only the last OR term was bound to the role filter.
            $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->keyword.'%')
                    ->orWhere('email', 'like', '%'.$request->keyword.'%')
                    ->orWhere('phone', 'like', '%'.$request->keyword.'%')
                    ->orWhere('address', 'like', '%'.$request->keyword.'%');
            });
        });

        $query->when($request->filled('verified'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                if ($request->verified == 1) {
                    $query->whereNotNull('email_verified_at');
                } elseif ($request->verified == 0) {
                    $query->whereNull('email_verified_at');
                }
            });
        });

        $query->when($request->filled('banned'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                if ($request->banned == 1) {
                    $query->where('is_banned', 'yes');
                } elseif ($request->banned == 0) {
                    $query->where('is_banned', 'no');
                }
            });
        });
        $query->where('role', 'instructor');
        $orderBy = $request->filled('order_by') && $request->order_by == 1 ? 'asc' : 'desc';

        if ($request->filled('par-page')) {
            $users = $request->get('par-page') == 'all' ? $query->orderBy('id', $orderBy)->get() : $query->orderBy('id', $orderBy)->paginate($request->get('par-page'))->withQueryString();
        } else {
            $users = $query->orderBy('id', $orderBy)->paginate()->withQueryString();
        }

        return view('customer::all_instructor')->with([
            'users' => $users,
        ]);
    }

    /**
     * 2026-06-25 — CSV export of the instructor list, honouring the same
     * search/verified/banned filters as the listing. Streamed + chunked so it
     * stays memory-safe at enterprise scale (1000s of coaches).
     */
    public function exportInstructors(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.view');

        $query = User::query()->where('role', 'instructor');

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->keyword.'%')
                    ->orWhere('email', 'like', '%'.$request->keyword.'%')
                    ->orWhere('phone', 'like', '%'.$request->keyword.'%')
                    ->orWhere('address', 'like', '%'.$request->keyword.'%');
            });
        });
        $query->when($request->filled('verified'), function ($q) use ($request) {
            $request->verified == 1 ? $q->whereNotNull('email_verified_at') : $q->whereNull('email_verified_at');
        });
        $query->when($request->filled('banned'), function ($q) use ($request) {
            $q->where('is_banned', $request->banned == 1 ? 'yes' : 'no');
        });

        $filename = 'instructors-'.date('Y-m-d').'.csv';

        // 2026-06-25 (Phase 6 — audit coverage) — a CSV export of the instructor
        // list is a data-export action that must leave an audit trail (§11),
        // matching the existing attendance-export precedent. Logged before the
        // stream is flushed; actor/IP/UA are captured by ActivityLogger.
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::EXPORTED, 'user', null, null, null,
            'Exported instructor list CSV ('.(clone $query)->count().' rows)'
                .($request->filled('keyword') ? ' · search="'.$request->keyword.'"' : '')
        );

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Status', 'Joined at']);
            $query->orderBy('id', 'desc')->chunk(500, function ($users) use ($out) {
                foreach ($users as $u) {
                    $status = $u->email_verified_at
                        ? ($u->is_banned === 'yes' ? 'Banned' : 'Active')
                        : 'Not verified';
                    fputcsv($out, [
                        $u->id,
                        html_decode($u->name),
                        html_decode($u->email),
                        $u->phone,
                        $status,
                        optional($u->created_at)->format('Y-m-d H:i'),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function active_customer(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.view');

        $query = User::query();
        $query->where(['status' => 'active', 'is_banned' => 'no'])->where('email_verified_at', '!=', null);

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            // 2026-06-25 — wrap the OR group so the keyword terms can't escape the
            // role/status AND constraints applied later (SQL AND binds tighter than
            // OR). Pre-fix, searching e.g. instructors could surface students/other
            // roles because only the last OR term was bound to the role filter.
            $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->keyword.'%')
                    ->orWhere('email', 'like', '%'.$request->keyword.'%')
                    ->orWhere('phone', 'like', '%'.$request->keyword.'%')
                    ->orWhere('address', 'like', '%'.$request->keyword.'%');
            });
        });

        $orderBy = $request->filled('order_by') && $request->order_by == 1 ? 'asc' : 'desc';

        if ($request->filled('par-page')) {
            $users = $request->get('par-page') == 'all' ? $query->orderBy('id', $orderBy)->get() : $query->orderBy('id', $orderBy)->paginate($request->get('par-page'))->withQueryString();
        } else {
            $users = $query->orderBy('id', $orderBy)->paginate()->withQueryString();
        }

        return view('customer::active_customer')->with([
            'users' => $users,
        ]);
    }

    public function non_verified_customers(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.view');

        $query = User::query();
        $query->where('email_verified_at', null);

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            // 2026-06-25 — wrap the OR group so the keyword terms can't escape the
            // role/status AND constraints applied later (SQL AND binds tighter than
            // OR). Pre-fix, searching e.g. instructors could surface students/other
            // roles because only the last OR term was bound to the role filter.
            $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->keyword.'%')
                    ->orWhere('email', 'like', '%'.$request->keyword.'%')
                    ->orWhere('phone', 'like', '%'.$request->keyword.'%')
                    ->orWhere('address', 'like', '%'.$request->keyword.'%');
            });
        });
        $query->when($request->filled('banned'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                if ($request->banned == 1) {
                    $query->where('is_banned', 'yes');
                } elseif ($request->banned == 0) {
                    $query->where('is_banned', 'no');
                }
            });
        });
        $orderBy = $request->filled('order_by') && $request->order_by == 1 ? 'asc' : 'desc';

        if ($request->filled('par-page')) {
            $users = $request->get('par-page') == 'all' ? $query->orderBy('id', $orderBy)->get() : $query->orderBy('id', $orderBy)->paginate($request->get('par-page'))->withQueryString();
        } else {
            $users = $query->orderBy('id', $orderBy)->paginate()->withQueryString();
        }

        return view('customer::non_verified_customer')->with([
            'users' => $users,
        ]);
    }

    public function banned_customers(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.view');

        $query = User::query();
        $query->where('is_banned', 'yes');

        $query->when($request->filled('keyword'), function ($q) use ($request) {
            // 2026-06-25 — wrap the OR group so the keyword terms can't escape the
            // role/status AND constraints applied later (SQL AND binds tighter than
            // OR). Pre-fix, searching e.g. instructors could surface students/other
            // roles because only the last OR term was bound to the role filter.
            $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->keyword.'%')
                    ->orWhere('email', 'like', '%'.$request->keyword.'%')
                    ->orWhere('phone', 'like', '%'.$request->keyword.'%')
                    ->orWhere('address', 'like', '%'.$request->keyword.'%');
            });
        });

        $query->when($request->filled('verified'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                if ($request->verified == 1) {
                    $query->whereNotNull('email_verified_at');
                } elseif ($request->verified == 0) {
                    $query->whereNull('email_verified_at');
                }
            });
        });

        $orderBy = $request->filled('order_by') && $request->order_by == 1 ? 'asc' : 'desc';

        if ($request->filled('par-page')) {
            $users = $request->get('par-page') == 'all' ? $query->orderBy('id', $orderBy)->get() : $query->orderBy('id', $orderBy)->paginate($request->get('par-page'))->withQueryString();
        } else {
            $users = $query->orderBy('id', $orderBy)->paginate()->withQueryString();
        }

        return view('customer::banned_customer')->with([
            'users' => $users,
        ]);
    }

    public function show($id)
    {
        checkAdminHasPermissionAndThrowException('customer.view');

        $user = User::findOrFail($id);
        $experiences = UserExperience::where('user_id', $user->id)->get();
        $educations = UserEducation::where('user_id', $user->id)->get();
        $banned_histories = BannedHistory::where('user_id', $id)->orderBy('id', 'desc')->get();
        $states = State::where(['country_id' => $user->country_id, 'status' => 1])->get();
        $cities = City::where(['state_id' => $user->state_id, 'status' => 1])->get();
        $plans = Subscription::where('status', 'active')->get();

        return view('customer::customer_show')->with([
            'user' => $user,
            'banned_histories' => $banned_histories,
            'experiences' => $experiences,
            'educations' => $educations,
            'states' => $states,
            'cities' => $cities,
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');

        $rules = [
            'name' => 'required',
            'email' => ['required', 'email', 'max:255'],
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'address.required' => __('Address is required'),
            'email.required' => __('Email is required'),
            'email.email' => __('Email is not valid'),
            'email.max' => __('Email maximum 255 character'),
        ];
        $request->validate($rules, $customMessages);

        
        $user = User::findOrFail($id);

        $username = ($user->role =='instructor')?$request->username:Str::slug($request->name);
       
        $user->name = $request->name;
        $user->username = $username;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->age = $request->age;
        $user->gender = $request->gender;

        $user->save();

        $notification = __('Updated Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function bioUpdate(Request $request, $id)
    {
        // FT-IDOR-3 fix (2026-05-27) — was missing permission gate.
        // A sub-admin without customer.update could overwrite any user's
        // bio/designation by hitting this endpoint directly.
        checkAdminHasPermissionAndThrowException('customer.update');
        $rules = [
            'designation' => ['required', 'string', 'max:255'],
            'bio' => ['required', 'string', 'max:2000'],
            'short_bio' => ['required', 'string', 'max:300'],
        ];
        $messages = [
            'designation.required' => __('The designation field is required'),
            'designation.string' => __('The designation must be a string'),
            'designation.max' => __('The designation may not be greater than 255 characters.'),
            'bio.required' => __('The bio field is required'),
            'bio.string' => __('The bio must be a string'),
            'bio.max' => __('The bio may not be greater than 2000 characters.'),
            'short_bio.required' => __('The short bio field is required'),
            'short_bio.string' => __('The short bio must be a string'),
            'short_bio.max' => __('The short bio may not be greater than 300 characters.'),
        ];

        $this->validate($request, $rules, $messages);

        $user = User::findOrFail($id);
        $user->job_title = $request->designation;
        $user->bio = $request->bio;
        $user->short_bio = $request->short_bio;
        $user->save();

        $notification = __('Updated Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function experienceModal(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        $user = User::findOrFail($id);

        return view('customer::modals.experience-modal', compact('user'))->render();
    }

    public function experienceStore(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        // Confirm the target user actually exists before persisting an experience row against it.
        User::findOrFail($id);

        $rules = [
            'company' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'current' => ['boolean'],
        ];

        $messages = [
            'company.required' => __('The company field is required'),
            'company.string' => __('The company must be a string'),
            'company.max' => __('The company may not be greater than 255 characters.'),
            'position.required' => __('The position field is required'),
            'position.string' => __('The position must be a string'),
            'position.max' => __('The position may not be greater than 255 characters.'),
            'start_date.required' => __('The start date field is required'),
            'start_date.date' => __('The start date must be a date'),
            'end_date.date' => __('The end date must be a date'),
            'current.boolean' => __('The current field must be a boolean'),
        ];

        $this->validate($request, $rules, $messages);
        $experience = new UserExperience;
        $experience->user_id = $id;
        $experience->company = $request->company;
        $experience->position = $request->position;
        $experience->start_date = $request->start_date;
        $experience->end_date = $request->end_date;
        $experience->current = $request->current;
        $experience->save();

        $notification = __('Created Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function editExperienceModal(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        $experience = UserExperience::findOrFail($id);

        return view('customer::modals.edit-experience-modal', compact('experience'))->render();
    }

    public function experienceUpdate(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        $rules = [
            'company' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'current' => ['boolean'],
        ];

        $messages = [
            'company.required' => __('The company field is required'),
            'company.string' => __('The company must be a string'),
            'company.max' => __('The company may not be greater than 255 characters.'),
            'position.required' => __('The position field is required'),
            'position.string' => __('The position must be a string'),
            'position.max' => __('The position may not be greater than 255 characters.'),
            'start_date.required' => __('The start date field is required'),
            'start_date.date' => __('The start date must be a date'),
            'end_date.date' => __('The end date must be a date'),
            'current.boolean' => __('The current field must be a boolean'),
        ];

        $this->validate($request, $rules, $messages);
        $experience = UserExperience::whereId($id)->firstOrFail();
        $experience->company = $request->company;
        $experience->position = $request->position;
        $experience->start_date = $request->start_date;
        $experience->end_date = $request->end_date;
        $experience->current = $request->current;
        $experience->save();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function experienceDestroy($id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        $experience = UserExperience::whereId($id)->firstOrFail();
        $experience->delete();

        $notification = __('Deleted Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function educationModal(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        $user = User::findOrFail($id);

        return view('customer::modals.add-education-modal', compact('user'))->render();
    }

    public function educationStore(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        User::findOrFail($id);
        $rules = [
            'organization' => ['required', 'max:255', 'string'],
            'degree' => ['required', 'max:255', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
        $messages = [

            'organization.required' => __('The organization field is required.'),
            'organization.max' => __('The organization field must not be greater than 255 characters.'),
            'organization.string' => __('The organization field must be a string.'),

            'degree.required' => __('The degree field is required.'),
            'degree.max' => __('The degree field must not be greater than 255 characters.'),
            'degree.string' => __('The degree field must be a string.'),

            'start_date.required' => __('The start date field is required.'),
            'start_date.date' => __('The start date field must be a valid date.'),

            'end_date.required' => __('The end date field is required.'),
            'end_date.date' => __('The end date field must be a valid date.'),
        ];

        $this->validate($request, $rules, $messages);

        $education = new UserEducation;
        $education->user_id = $id;
        $education->organization = $request->organization;
        $education->degree = $request->degree;
        $education->start_date = $request->start_date;
        $education->end_date = $request->end_date;
        $education->current = $request->current;
        $education->save();

        $notification = __('Create Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function editEducationModal(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        $education = UserEducation::findOrFail($id);

        return view('customer::modals.edit-education-modal', compact('education'))->render();
    }

    public function educationUpdate(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');

        $rules = [
            'organization' => ['required', 'max:255', 'string'],
            'degree' => ['required', 'max:255', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
        $messages = [

            'organization.required' => __('The organization field is required.'),
            'organization.max' => __('The organization field must not be greater than 255 characters.'),
            'organization.string' => __('The organization field must be a string.'),

            'degree.required' => __('The degree field is required.'),
            'degree.max' => __('The degree field must not be greater than 255 characters.'),
            'degree.string' => __('The degree field must be a string.'),

            'start_date.required' => __('The start date field is required.'),
            'start_date.date' => __('The start date field must be a valid date.'),

            'end_date.required' => __('The end date field is required.'),
            'end_date.date' => __('The end date field must be a valid date.'),
        ];

        $this->validate($request, $rules, $messages);
        $education = UserEducation::whereId($id)->firstOrFail();
        $education->organization = $request->organization;
        $education->degree = $request->degree;
        $education->start_date = $request->start_date;
        $education->end_date = $request->end_date;
        $education->current = $request->current;
        $education->save();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function educationDestroy($id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        $education = UserEducation::whereId($id)->firstOrFail();
        $education->delete();

        $notification = __('Deleted Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function locationUpdate(Request $request, $id)
    {
        // FT-IDOR-3 fix (2026-05-27) — was missing permission gate.
        checkAdminHasPermissionAndThrowException('customer.update');
        $request->validate([
            'country' => ['required', 'integer', 'exists:countries,id'],
            'state' => ['nullable', 'max:255'],
            'city' => ['nullable', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'country.required' => __('You must select a country.'),
            'country.integer' => __('Country ID must be an integer.'),
            'country.exists' => __('The selected country is invalid.'),
            'state.integer' => __('State ID must be an integer.'),
            'state.exists' => __('The selected state is invalid.'),
            'city.integer' => __('City ID must be an integer.'),
            'city.exists' => __('The selected city is invalid.'),
            'address.string' => __('The address must be a string.'),
            'address.max' => __('The address may not be greater than 255 characters.'),
        ]);

        $user = User::findOrFail($id);
        $user->address = $request->address;
        $user->city = $request->city;
        $user->state = $request->state;
        $user->country_id = $request->country;
        $user->save();

        $notification = __('Updated Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function socialUpdate(Request $request, $id)
    {
        // FT-IDOR-3 fix (2026-05-27) — was missing permission gate.
        // Without this check, any authenticated admin (including read-only
        // sub-admins with only `customer.view`) could overwrite a target
        // user's social profile URLs by hitting the route directly. The
        // companion `bioUpdate` / `locationUpdate` / `password_change`
        // methods already gate on `customer.update`; this one was missed
        // when the original audit added the others.
        checkAdminHasPermissionAndThrowException('customer.update');

        $rules = [
            'facebook' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'github' => ['nullable', 'string', 'max:255'],
            'insatagram' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],
        ];
        $messages = [
            'facebook.string' => __('The facebook must be a string.'),
            'facebook.max' => __('The facebook may not be greater than 255 characters.'),
            'twitter.string' => __('The twitter must be a string.'),
            'twitter.max' => __('The twitter may not be greater than 255 characters.'),
            'linkedin.string' => __('The linkedin must be a string.'),
            'linkedin.max' => __('The linkedin may not be greater than 255 characters.'),
            'website.string' => __('The website must be a string.'),
            'website.max' => __('The website may not be greater than 255 characters.'),
            'github.string' => __('The github must be a string.'),
            'github.max' => __('The github may not be greater than 255 characters.'),
            'instagram.string' => __('The instagram must be a string.'),
            'instagram.max' => __('The instagram may not be greater than 255 characters.'),
            'youtube.string' => __('The youtube must be a string.'),
            'youtube.max' => __('The youtube may not be greater than 255 characters.'),
        ];

        $this->validate($request, $rules, $messages);

        $user = User::findOrFail($id);
        $user->facebook = $request->facebook;
        $user->instagram = $request->instagram;
        $user->youtube = $request->youtube;
        $user->twitter = $request->twitter;
        $user->website = $request->website;
        $user->linkedin = $request->linkedin;
        $user->github = $request->github;
        $user->save();

        $notification = __('Updated Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function password_change(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');

        // FT-AUTH-7 fix (2026-05-27) — was `min:4`.
        // Four-character passwords are crackable offline in seconds.
        // Self-service signup (Auth\RegisteredUserController) already
        // requires min:8; admin-driven password changes must match so
        // an admin can't downgrade a customer to a weaker password.
        $rules = [
            'password' => 'required|min:8|confirmed',
        ];
        $customMessages = [
            'password.required' => __('Password is required'),
            'password.min' => __('Password must be at least 8 characters'),
            'password.confirmed' => __('Confirm password does not match'),
        ];
        $this->validate($request, $rules, $customMessages);

        $user = User::findOrFail($id);

        $user->password = Hash::make($request->password);
        $user->save();

        $notification = __('Password change successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function send_banned_request(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');

        $rules = [
            'subject' => 'required|max:255',
            'description' => 'required',
        ];
        $customMessages = [
            'subject.required' => __('Subject is required'),
            'description.required' => __('Description is required'),
        ];

        $this->validate($request, $rules, $customMessages);

        $user = User::findOrFail($id);
        if ($user->is_banned == 'yes') {
            $user->is_banned = 'no';
            $user->save();

            $banned = new BannedHistory;
            $banned->user_id = $id;
            $banned->subject = $request->subject;
            $banned->reasone = 'for_unbanned';
            $banned->description = $request->description;
            $banned->save();
        } else {
            $user->is_banned = 'yes';
            $user->save();

            $banned = new BannedHistory;
            $banned->user_id = $id;
            $banned->subject = $request->subject;
            $banned->reasone = 'for_banned';
            $banned->description = $request->description;
            $banned->save();
        }

        (new MailSenderService)->SendUserBannedMailFromTrait($request->description, $request->subject, $user);

        $notification = __('Banned request successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function assign_subscription(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');

        $rules = [
            'subscription_plan_id' => 'required|exists:subscriptions,id',
        ];

        $customMessages = [
            'subscription_plan_id.required' => __('Subscription Plan Id Is Required'),
        ];

        $this->validate($request, $rules, $customMessages);

        $user = User::findOrFail($id);
        $method = 'offline';
        $subscription = Subscription::findOrFail($request->subscription_plan_id);

        $payable_amount = $subscription->price;
        $order_type = 'subscription';
        $order_details = null;

        try {
            $paid_amount = $payable_amount;

            DB::transaction(function () use ($user, $subscription, $method, $payable_amount, $paid_amount, $order_type, $order_details) {
                $order = Order::create([
                    'invoice_id' => Str::random(10),
                    'buyer_id' => $user->id,
                    'has_coupon' => 0,
                    'coupon_code' => '',
                    'coupon_discount_percent' => '',
                    'coupon_discount_amount' => 0,
                    'payment_method' => $method,
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'payable_amount' => $payable_amount,
                    'gateway_charge' => 0,
                    'payable_with_charge' => $paid_amount,
                    'paid_amount' => $paid_amount,
                    'payable_currency' => 'USD',
                    'conversion_rate' => 1,
                    'commission_rate' => 0,
                    'order_type' => $order_type,
                    'order_details' => $order_details,
                ]);

                SubscriptionHistory::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $subscription->id,
                    'order_id' => $order->id,
                    'price' => $paid_amount,
                    'description' => $subscription->description,
                    'start_date' => now(),
                    'end_date' => now()->addDays($subscription->duration_days),
                    'status' => 'active',
                ]);
            });

            $notification = __('Subscription Added Successfully');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];

            if($user->role=='instructor'){
                return redirect()->route('instructor.dashboard')->with($notification);
            }else{
                return redirect()->route('admin.all-instructors')->with($notification);
            }

        } catch (Exception $e) {
            \Log::warning('assign_subscription failed: ' . $e->getMessage());

            $notification = __('Addition Failed');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            if($user->role=='instructor'){
                return redirect()->route('instructor.dashboard')->with($notification);
            }else{
                return redirect()->route('admin.all-instructors')->with($notification);
            }
        }

    }

    public function send_verify_request(Request $request, $id)
    {
        // FT-IDOR-3 fix (2026-05-27) — was missing permission gate.
        // Resetting `verification_token` and re-issuing a verification
        // email is a customer-state mutation. A read-only sub-admin
        // shouldn't be able to spam users via this endpoint.
        checkAdminHasPermissionAndThrowException('customer.update');

        $user = User::findOrFail($id);
        $user->verification_token = Str::random(100);
        $user->save();

        (new MailSenderService)->sendVerifyMailToUserFromTrait('single_user', $user);

        $notification = __('A varification link has been send to user mail');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function send_verify_request_to_all(Request $request)
    {
        // FT-IDOR-3 fix (2026-05-27) — was missing permission gate.
        // Fans out a verification email to EVERY user on the platform.
        // High blast-radius: must require explicit bulk-mail authority
        // (same gate the bulk-mail screens already use).
        checkAdminHasPermissionAndThrowException('customer.bulk.mail');

        (new MailSenderService)->sendVerifyMailToUserFromTrait('all_user');

        $notification = __('A varification link has been send to user mail');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function send_mail_to_customer(Request $request, $id)
    {
        // FT-IDOR-3 fix (2026-05-27) — was missing permission gate.
        // Sends an arbitrary admin-authored email to a single customer.
        // Without a gate, a read-only sub-admin could weaponise this as
        // a templated phishing tool. Use the same single-customer mail
        // permission convention.
        checkAdminHasPermissionAndThrowException('customer.update');

        $rules = [
            'subject' => 'required|max:255',
            'description' => 'required',
        ];
        $customMessages = [
            'subject.required' => __('Subject is required'),
            'description.required' => __('Description is required'),
        ];

        $this->validate($request, $rules, $customMessages);

        $user = User::findOrFail($id);

        (new MailSenderService)->sendMailToUserFromTrait($request->subject, $request->description, 'single_user', $user);

        $notification = __('Mail send to customer successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function send_bulk_mail()
    {
        checkAdminHasPermissionAndThrowException('customer.bulk.mail');

        return view('customer::send_bulk_mail');
    }

    public function send_bulk_mail_to_all(Request $request)
    {
        checkAdminHasPermissionAndThrowException('customer.bulk.mail');

        $rules = [
            'subject' => 'required|max:255',
            'description' => 'required',
        ];

        $customMessages = [
            'subject.required' => __('Subject is required'),
            'description.required' => __('Description is required'),
        ];

        $this->validate($request, $rules, $customMessages);

        (new MailSenderService)->sendMailToUserFromTrait($request->subject, $request->description, 'all_user');

        $notification = __('Mail send to customer successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('customer.delete');

        $user = User::findOrFail($id);

        $redirect_path = 'admin.all-customers';

        // prevent delete instructor if exists course
        if ($user->role == 'instructor') {
            $coursesExist = Course::where('instructor_id', $user->id)->exists();
            if ($coursesExist) {
                $notification = __('Instructor can not be deleted. Instructor has courses');
                $notification = ['messege' => $notification, 'alert-type' => 'error'];

                return redirect()->back()->with($notification);
            }
            $redirect_path = 'admin.all-instructors';
        }

        // prevent delete instructor if exists course
        if ($user->role == 'student') {
            if ($user->orders()->count() > 0) {
                return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('A student cannot be deleted because he has purchased course.')]);
            }
        }

        if ($user->image) {
            $file = $user->image;
            if (! str($file)->contains('uploads/website-images') && File::exists(public_path($file))) {
                File::delete(public_path($file));
            }
        }

        $user->delete();

        $notification = __('User deleted successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return $this->redirectWithMessage(RedirectType::DELETE->value, $redirect_path);
    }

    public function verifyAccountManually($id)
    {
        checkAdminHasPermissionAndThrowException('customer.update');
        User::findOrFail($id)->update(['email_verified_at' => now()]);
        $notification = __('The user account has been successfully verified.');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
}
