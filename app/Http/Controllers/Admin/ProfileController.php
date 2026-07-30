<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use RedirectHelperTrait;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function edit_profile()
    {
        abort_unless(checkAdminHasPermission('admin.profile.view'), 403);
        $admin = Auth::guard('admin')->user();

        return view('admin.profile.edit_profile', compact('admin'));
    }

    public function profile_update(Request $request)
    {
        checkAdminHasPermissionAndThrowException('admin.profile.update');

        $admin = Auth::guard('admin')->user();
        // FT-VAL-1 fix (2026-05-27) — added `email|max:190` so admin
        // self-service edit form requires a real email address.
        // FT-UPLOAD-3 + FT-VAL-19 (2026-05-28) — added image rule
        // (mimes, max) and bio length cap. The pre-fix profile_update
        // accepted any image format (file_upload helper rejected
        // downstream; validate upstream for friendly errors) and
        // an unbounded `bio` body that could DOS the admins.bio
        // column on save.
        // 2026-05-29 — UI/UX audit P0-6.
        // Was max:190 here; max:50 on student profile; max:255 on
        // register flows. Standardized on max:100 across all PROFILE
        // forms — long enough for international names with full
        // transliteration, short enough to fit UI cards/badges
        // without overflow. Register/create stays at 255 (org name
        // surface, different semantics).
        $rules = [
            'name'  => 'required|string|max:100',
            'email' => 'required|email|max:190|unique:admins,email,'.$admin->id,
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2000'],
            'bio'   => ['nullable', 'string', 'max:5000'],
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.email'    => __('Please enter a valid email address'),
            'email.unique' => __('Email already exist'),
        ];
        $this->validate($request, $rules, $customMessages);

        $admin = Auth::guard('admin')->user();

        if ($request->file('image')) {
            $file_name = file_upload(file: $request->image, path: 'uploads/custom-images/', oldFile: $admin->image);
            $admin->image = $file_name;
            $admin->save();
        }

        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->bio = $request->bio;
        $admin->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value);
    }

    public function update_password(Request $request)
    {
        checkAdminHasPermissionAndThrowException('admin.profile.update');

        $admin = Auth::guard('admin')->user();
        $rules = [
            'current_password' => 'required',
            'password' => 'required|confirmed|min:8',
        ];
        $customMessages = [
            'current_password.required' => __('Current password is required'),
            'password.required' => __('Password is required'),
            'password.confirmed' => __('Confirm password does not match'),
            'password.min' => __('Password must be at least 8 characters'),
        ];
        $this->validate($request, $rules, $customMessages);

        if (Hash::check($request->current_password, $admin->password)) {
            $admin->password = Hash::make($request->password);
            $admin->save();

            $notification = __('Password updated successfully');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];

            return $this->redirectWithMessage(RedirectType::UPDATE->value, '', [], $notification);

        } else {
            $notification = __('Current password does not match');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }
    }
}
