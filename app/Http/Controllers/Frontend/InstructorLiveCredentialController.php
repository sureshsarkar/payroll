<?php

namespace App\Http\Controllers\Frontend;

use App\Models\YoutubeCredential;
use Illuminate\View\View;
use App\Enums\RedirectType;
use Illuminate\Http\Request;
use App\Models\ZoomCredential;
use App\Traits\RedirectHelperTrait;
use App\Http\Controllers\Controller;

class InstructorLiveCredentialController extends Controller {
    use RedirectHelperTrait;

        function test(): View {
        return view('frontend.instructor-dashboard.zoom.test');
    }


    function index(): View {
        $credential = userAuth()->zoom_credential;
        return view('frontend.instructor-dashboard.zoom.index', compact('credential'));
    }
    function update(Request $request) {
        $validated = $request->validate([
            'account_id'    => 'required|string|max:64',
            'client_id'     => 'required|string|max:128',
            'client_secret' => 'required|string|max:128',
            'sdk_key'       => 'required|string|max:128',
            'sdk_secret'    => 'required|string|max:128',
        ],[
            'account_id.required'    => __('Account ID is required'),
            'client_id.required'     => __('Client ID is required'),
            'client_secret.required' => __('Client secret is required'),
            'sdk_key.required'       => __('SDK Key is required'),
            'sdk_secret.required'    => __('SDK Secret is required'),
        ]);

        // Reset cached token + health on credential change so the next API
        // call re-mints under the new creds and we don't hand out a stale
        // token that was issued by a different Zoom app.
        ZoomCredential::updateOrCreate(
            ['instructor_id' => userAuth()->id],
            $validated + [
                'zoom_access_token'     => null,
                'zoom_token_expires_at' => null,
                'health_status'         => 'unchecked',
                'health_message'        => null,
                'last_health_check_at'  => null,
            ]
        );
        return $this->redirectWithMessage(RedirectType::UPDATE->value);
    }


    // Youtube Credential
    
    function youtube_index(): View {
        $credential = userAuth()->youtube_credential;
        return view('frontend.instructor-dashboard.youtube.index', compact('credential'));
    }

    function youtube_update(Request $request) {
        $validated = $request->validate([
            'channel_id' => 'required',
            'api_key' => 'required',
        ],[
            'channel_id.required' => __('Client ID is required'),
            'api_key.required' => __('Client secret is required'),
        ]);
        YoutubeCredential::updateOrCreate(['instructor_id' => userAuth()->id],$validated);
        return $this->redirectWithMessage(RedirectType::UPDATE->value);
    }
    
}
