@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title d-flex justify-content-between">
            <h4 class="title">{{ __('Zoom live setting') }}</h4>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="instructor__profile-form-wrap">
                    <div class="row">
                        <form action="{{ route('instructor.zoom-setting.update') }}" method="POST"
                            class="col-xl-6 instructor__profile-form">
                            @csrf
                            @method('PUT')

                            <h5 class="mt-2 mb-3">{{ __('Server-to-Server OAuth (creates meetings)') }}</h5>
                            <div class="form-grp">
                                <label for="account_id">{{ __('Account ID') }} <code>*</code></label>
                                <input id="account_id" name="account_id" type="text"
                                       value="{{ $credential?->account_id }}" autocomplete="off">
                            </div>
                            <div class="form-grp">
                                <label for="client_id">{{ __('Client ID') }} <code>*</code></label>
                                <input id="client_id" name="client_id" type="text"
                                       value="{{ $credential?->client_id }}" autocomplete="off">
                            </div>
                            <div class="form-grp">
                                <label for="client_secret">{{ __('Client secret') }} <code>*</code></label>
                                <input id="client_secret" name="client_secret" type="password"
                                       value="{{ $credential?->client_secret }}" autocomplete="off">
                            </div>

                            <h5 class="mt-4 mb-3">{{ __('Meeting SDK (joins meetings in browser)') }}</h5>
                            <div class="form-grp">
                                <label for="sdk_key">{{ __('SDK Key') }} <code>*</code></label>
                                <input id="sdk_key" name="sdk_key" type="text"
                                       value="{{ $credential?->sdk_key }}" autocomplete="off">
                            </div>
                            <div class="form-grp">
                                <label for="sdk_secret">{{ __('SDK Secret') }} <code>*</code></label>
                                <input id="sdk_secret" name="sdk_secret" type="password"
                                       value="{{ $credential?->sdk_secret }}" autocomplete="off">
                            </div>

                            <div class="submit-btn mt-25">
                                <button type="submit" class="btn">✓ {{ __('Save') }}</button>
                            </div>
                        </form>
                        <div class="col-xl-6">
                            <div class="alert alert-info" role="alert">
                                <h4 class="alert-heading">{{ __('Two Zoom Marketplace apps are required') }}</h4>
                                <p class="mb-2">
                                    {{ __('Zoom uses different credentials for REST API calls vs. embedding the meeting in the browser. You need both:') }}
                                </p>
                                <hr>
                                <h6 class="mb-2">1. {{ __('Server-to-Server OAuth app') }}</h6>
                                <ol class="mb-2" style="padding-left:1.2rem;">
                                    <li>{{ __('Open') }} <a href="https://marketplace.zoom.us/" target="_blank" rel="noopener">Zoom Marketplace</a> → <b>Develop → Build App</b>.</li>
                                    <li>{{ __('Choose') }} <b>Server-to-Server OAuth</b>{{ __(', name it (e.g.') }} <code>MBSGuru-API</code>{{ __(').') }}</li>
                                    <li>{{ __('Copy') }} <b>Account ID</b>, <b>Client ID</b>, <b>Client Secret</b> → {{ __('paste into the top three fields on the left.') }}</li>
                                    <li>{{ __('Add scopes (use the') }} <code>:admin</code> {{ __('variants):') }}
                                        <code>meeting:write:meeting:admin</code>,
                                        <code>meeting:update:meeting:admin</code>,
                                        <code>meeting:read:meeting:admin</code>,
                                        <code>cloud_recording:read:list_recording_files:admin</code>,
                                        <code>user:read:user:admin</code></li>
                                    <li>{{ __('Activate the app.') }}</li>
                                </ol>
                                <hr>
                                <h6 class="mb-2">2. {{ __('Meeting SDK app') }}</h6>
                                <ol class="mb-2" style="padding-left:1.2rem;">
                                    <li>{{ __('In') }} <a href="https://marketplace.zoom.us/develop/createLegacy" target="_blank" rel="noopener">Build Legacy App</a> → <b>Meeting SDK</b> → {{ __('Create.') }}</li>
                                    <li>{{ __('Name it (e.g.') }} <code>MBSGuru-SDK</code>{{ __(').') }}</li>
                                    <li>{{ __('Copy') }} <b>SDK Key</b> {{ __('and') }} <b>SDK Secret</b> → {{ __('paste into the bottom two fields on the left.') }}</li>
                                    <li>{{ __('Activate the app.') }}</li>
                                </ol>
                                <hr>
                                <p class="mb-0 small">
                                    <strong>{{ __('Why two apps?') }}</strong>
                                    {{ __("Server-to-Server OAuth gives non-expiring REST API access (creating meetings server-side). The Meeting SDK app provides the SDK Key + Secret used to sign the JWT that the browser SDK validates. Mixing them causes 'Signature is invalid' errors.") }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
