<?php

namespace App\Traits;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

trait GenerateSecureLinkTrait {
    /**
     * Generate a secure signed URL for accessing a protected video file.
     *
     * @param string $path The file path of the video to secure.
     * @return string|bool The signed URL if successful, or `false` on failure.
     */
    private function generateSecureLink($path) {
        try {
            $data = ['user_id' => userAuth()->id, 'path' => $path];
            $encryptedData = Crypt::encrypt($data);
            return URL::signedRoute('secure.video', ['hash' => $encryptedData]);
        } catch (\Exception $e) {
            info($e->getMessage());
            return false;
        }
    }

    /**
     * Preview a secure video file based on a hashed link.
     *
     * @param string $hash The encrypted hash containing the user ID and file path.
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    private function previewSecureLink($hash) {
        try {
            $data = Crypt::decrypt($hash);
            if (!isset($data['user_id'], $data['path']) || userAuth()->id !== $data['user_id']) {
                abort(401);
            }

            $path = public_path($data['path']);
            if (!file_exists($path)) {
                abort(404);
            }

            return response()->file($path);
        } catch (\Exception $e) {
            abort(401);
        }
    }
}
