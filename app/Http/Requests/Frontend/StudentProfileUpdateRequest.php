<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StudentProfileUpdateRequest extends FormRequest
{
    function __construct()
    {
        setFormTabStep('profile_tab', 'profile');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 2026-07-18 (Dashboard Nav Enhancement #5) — the mobile number is
        // MANDATORY for coaches on /instructor/setting (they need a reachable
        // contact for payouts, live-class coordination, and student support).
        // This request is shared with the student profile form, so the
        // "required" only applies when the authenticated user is a coach —
        // students keep the optional field. Global for every coach tenant
        // (role-based, no hard-coded coach/domain).
        $authUser = $this->user('web') ?? $this->user();
        $isCoach = ($authUser?->role === 'instructor');

        return [
            // 2026-05-29 — UI/UX audit P0-6.
            // Standardized to max:100 across all PROFILE forms
            // (admin / instructor / student). Was max:50 — too tight
            // for international names with transliteration.
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            // FT-UPLOAD-3 (2026-05-28) — explicit mimes (no svg).
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2000'],
            // Accepts an optional leading "+" country code plus digits, spaces,
            // hyphens and parentheses; needs at least 7 digits overall.
            'phone' => [
                $isCoach ? 'required' : 'nullable',
                'string', 'max:30',
                'regex:/^\+?[0-9][0-9\s\-()]{5,}[0-9]$/',
            ],
            'age' => ['nullable', 'integer', 'max:150'],
        ];
    }

    // custom validation error messages
    function messages(): array
    {
        return [
            'name.required' => __('The name field is required'),
            'name.string' => __('The name must be a string'),
            'name.max' => __('The name may not be greater than 100 characters.'),
            'email.required' => __('The email field is required'),
            'email.email' => __('The email must be a valid email address'),
            'email.max' => __('The email may not be greater than 255 characters'),
            'image.image' => __('The image must be an image'),
            'image.max' => __('The image may not be greater than 2000 kilobytes'),
            'cover.image' => __('The cover must be an image'),
            'cover.max' => __('The cover may not be greater than 2000 kilobytes'),
            'phone.required' => __('The mobile number field is required'),
            'phone.string' => __('The phone must be a string'),
            'phone.max' => __('The phone may not be greater than 30 characters'),
            'phone.regex' => __('Enter a valid mobile number (7–15 digits, optional country code).'),
            'age.integer' => __('The age must be an integer'),
            'age.max' => __('The age may not be greater than 150'),
        ];
    }

}
