<?php

namespace App\Http\Controllers;

use App\Traits\GenerateSecureLinkTrait;

class SecureLinkPreviewController extends Controller {
    use GenerateSecureLinkTrait;
    /**
     * Handle the incoming request.
     */
    public function __invoke($hash) {
        try {
            return $this->previewSecureLink($hash);
        } catch (\Exception $e) {
            abort(403);
        }
    }
}
