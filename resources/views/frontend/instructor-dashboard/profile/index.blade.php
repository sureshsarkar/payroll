@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

{{-- 2026-05-20 — corporate wrapper on top of the legacy 7-tab settings
     page. The PER-TAB sub-partials (profile / biography / education /
     location / social / payout / password) still own their own form
     markup — they're complex, work today, and out of scope here.
     This page just upgrades the OUTER chrome: corp-page wrapper,
     corp-header, and a proper corp-style tab navigation row. --}}

<style>
    /* Corporate-tinted variant of the Bootstrap 5 tabs API. We can't
       drop the .nav-tabs structure because the page sections all bind
       to data-bs-target/aria-* attributes — preserving them keeps the
       existing JS/show.bs.tab handlers wired. We just restyle. */
    .corp-tabnav {
        background: var(--corp-card);
        border: 1px solid var(--corp-line);
        border-radius: 10px;
        padding: 6px;
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .corp-tabnav .nav-tabs {
        border-bottom: none;
        gap: 4px;
        flex-wrap: wrap;
        width: 100%;
    }
    .corp-tabnav .nav-item { margin-bottom: 0; }
    .corp-tabnav .nav-link {
        background: transparent;
        border: 1px solid transparent;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--corp-muted);
        transition: all .14s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .corp-tabnav .nav-link:hover {
        background: var(--corp-surface, #f8fafc);
        color: var(--corp-text);
    }
    .corp-tabnav .nav-link.active {
        background: var(--corp-brand);
        color: #fff;
        border-color: var(--corp-brand);
        box-shadow: 0 2px 6px rgba(16, 185, 129, .25);
    }

    /* Wrap the per-tab content so each panel reads like a corp-form-
       card without us having to touch every sub-partial. */
    .corp-tab-host .tab-content { padding: 0; }

    @media (max-width: 640px) {
        .corp-tabnav .nav-link { padding: 7px 10px; font-size: 11.5px; }
    }
</style>

<div class="corp-page" id="instructorSettings">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Settings') }}</h4>
            <p>{{ __('Your profile, biography, contacts, social links, payout details, and security — all in one place.') }}</p>
        </div>
    </div>

    {{-- Tab navigation — same structure, corp skin --}}
    <div class="corp-tabnav">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('profile_tab') == 'profile' ? 'active' : '' }}" id="itemOne-tab" data-bs-toggle="tab"
                    data-bs-target="#itemOne-tab-pane" type="button" role="tab"
                    aria-controls="itemOne-tab-pane" aria-selected="true">
                    <i class="fas fa-user"></i> {{ __('Profile') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('profile_tab') == 'bio' ? 'active' : '' }}" id="itemFour-tab" data-bs-toggle="tab"
                    data-bs-target="#itemFour-tab-pane" type="button" role="tab"
                    aria-controls="itemFour-tab-pane" aria-selected="true">
                    <i class="fas fa-book-open"></i> {{ __('Biography') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('profile_tab') == 'education' ? 'active' : '' }}" id="itemFive-tab" data-bs-toggle="tab"
                    data-bs-target="#itemFive-tab-pane" type="button" role="tab"
                    aria-controls="itemFive-tab-pane" aria-selected="true">
                    <i class="fas fa-graduation-cap"></i> {{ __('Education & Experience') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('profile_tab') == 'location' ? 'active' : '' }}" id="itemSix-tab" data-bs-toggle="tab"
                    data-bs-target="#itemSix-tab-pane" type="button" role="tab"
                    aria-controls="itemSix-tab-pane" aria-selected="true">
                    <i class="fas fa-map-marker-alt"></i> {{ __('Location') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('profile_tab') == 'social' ? 'active' : '' }}" id="itemThree-tab" data-bs-toggle="tab"
                    data-bs-target="#itemThree-tab-pane" type="button" role="tab"
                    aria-controls="itemThree-tab-pane" aria-selected="false">
                    <i class="fas fa-share-alt"></i> {{ __('Social') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('profile_tab') == 'payout' ? 'active' : '' }}" id="itemSeven-tab-btn" data-bs-toggle="tab"
                    data-bs-target="#itemSeven-tab-pane" type="button" role="tab"
                    aria-controls="itemSeven-tab-pane" aria-selected="false">
                    <i class="fas fa-wallet"></i> {{ __('Payout') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('profile_tab') == 'password' ? 'active' : '' }}" id="itemTwo-tab" data-bs-toggle="tab"
                    data-bs-target="#itemTwo-tab-pane" type="button" role="tab"
                    aria-controls="itemTwo-tab-pane" aria-selected="false">
                    <i class="fas fa-lock"></i> {{ __('Password') }}
                </button>
            </li>
        </ul>
    </div>

    {{-- Tab content — per-section partials handle their own layout
         inside. We just wrap in a corp-form-card so each tab reads
         like a proper section card. --}}
    <div class="corp-form-card corp-tab-host">
        <div class="corp-form-card__body">
            <div class="tab-content" id="myTabContent">
                @include('frontend.instructor-dashboard.profile.sections.profile')

                @include('frontend.instructor-dashboard.profile.sections.biography')

                @include('frontend.instructor-dashboard.profile.sections.password')

                @include('frontend.instructor-dashboard.profile.sections.education-and-experience')

                @include('frontend.instructor-dashboard.profile.sections.location')

                @include('frontend.instructor-dashboard.profile.sections.payout')

                @include('frontend.instructor-dashboard.profile.sections.social')
            </div>
        </div>
    </div>
</div>
@endsection
