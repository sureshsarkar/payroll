<?php

namespace Modules\CryptoPayment\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\CryptoPayment\app\Models\CryptoPG;

class CryptoPaymentController extends Controller {
    public function __construct() {
        $this->middleware('auth:admin');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {
        checkAdminHasPermissionAndThrowException('basic.payment.update');

        $request->validate([
            'crypto_status'           => 'required|in:active,inactive',
            'crypto_sandbox'          => 'required|in:1,0',
            'crypto_token'            => 'required|string',
            // FT-UPLOAD-3 (2026-05-28) — explicit mimes (no svg).
            'crypto_image'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:256',
            // FT-PAY-8 fix (2026-05-27) — see BkashPGController for full
            // explanation. Charge is a percentage applied to checkout total;
            // clamp to 0..100.
            'crypto_charge'           => 'required|numeric|min:0|max:100',
            'crypto_receive_currency' => 'required|string',
        ]);

        $cryptoImage = CryptoPG::firstOrCreate(['key' => 'crypto_image'], ['value' => 'uploads/website-images/coingate.webp']);
        $oldImage = $cryptoImage->value;

        if ($request->hasFile('crypto_image')) {
            $image = file_upload(
                file: $request->file('crypto_image'),
                path: 'uploads/custom-images/crypto/',
                oldFile: $oldImage
            );
            $cryptoImage->update(['value' => $image]);
        }

        $cryptoKeys = [
            'crypto_status',
            'crypto_sandbox',
            'crypto_token',
            'crypto_charge',
            'crypto_receive_currency',
        ];

        foreach ($request->only($cryptoKeys) as $key => $value) {
            CryptoPG::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget('cryptoConfig');

        return redirect()->back()->with(['messege' => __('Updated Successfully'), 'alert-type' => 'success']);
    }
}
