<?php

namespace Modules\MercadoPagoPG\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\MercadoPagoPG\app\Models\MercadoPagoPG;

class MercadoPagoPGController extends Controller {
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse {
        checkAdminHasPermissionAndThrowException('basic.payment.update');

        $request->validate([
            'mercadopago_status'  => 'required|in:active,inactive',
            'mercadopago_sandbox' => 'required|in:1,0',
            // FT-PAY-8 fix (2026-05-27) — clamp percentage to 0..100;
            // see BkashPGController for full explanation.
            'mercadopago_charge'  => 'required|numeric|min:0|max:100',
            'public_key'          => 'required|string',
            'access_token'        => 'required|string',
            // FT-UPLOAD-3 (2026-05-28) — explicit mimes (no svg).
            'mercadopago_image'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:256',
        ]);

        $mercadopagopgImage = MercadoPagoPG::firstOrCreate(['key' => 'mercadopago_image'], ['value' => 'uploads/website-images/mercado-pago.png']);
        $oldImage = $mercadopagopgImage->value;

        if ($request->hasFile('mercadopago_image')) {
            $image = file_upload(
                file: $request->file('mercadopago_image'),
                path: 'uploads/custom-images/mercado-pago/',
                oldFile: $oldImage
            );
            $mercadopagopgImage->update(['value' => $image]);
        }

        $mercadopagopgKeys = [
            'mercadopago_status',
            'mercadopago_sandbox',
            'mercadopago_charge',
            'public_key',
            'access_token',
        ];

        foreach ($request->only($mercadopagopgKeys) as $key => $value) {
            MercadoPagoPG::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget('mercadopagoConfig');

        return redirect()->back()->with(['messege' => __('Updated Successfully'), 'alert-type' => 'success']);
    }
}
