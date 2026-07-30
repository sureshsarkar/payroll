<?php

namespace Modules\BkashPG\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\BkashPG\app\Models\BkashPGModel;

class BkashPGController extends Controller
{
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('basic.payment.update');

        $request->validate([
            'bkash_status'   => 'required|in:active,inactive',
            'bkash_sandbox'  => 'required|in:1,0',
            // FT-PAY-8 fix (2026-05-27) — was `required|numeric`.
            // Consumed by GetGlobalInformationTrait::calculate_payable_charge
            // as `payable_amount * (gateway_charge / 100)`, i.e. a percentage.
            // Without bounds, an admin (or attacker who compromises an admin
            // account) could set the charge to a negative value (refund-on-pay)
            // or 9999 (DoS the checkout flow with absurd totals). Clamp to a
            // sane 0..100 range. Same bug-class as FT-COUP-1 (coupon percentage).
            'bkash_charge'   => 'required|numeric|min:0|max:100',
            'bkash_key'      => 'required|string',
            'bkash_secret'   => 'required|string',
            'bkash_username' => 'required|string',
            'bkash_password' => 'required|string',
            // FT-UPLOAD-3 (2026-05-28) — explicit mimes (no svg).
            'bkash_image'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:256',
        ]);

        $bkashImage = BkashPGModel::firstOrCreate(['key' => 'bkash_image'], ['value' => 'uploads/website-images/bkash.png']);
        $oldImage = $bkashImage->value;


        if ($request->hasFile('bkash_image')) {
            $image = file_upload(
                file: $request->file('bkash_image'),
                path: 'uploads/custom-images/bkash/',
                oldFile: $oldImage
            );
            $bkashImage->update(['value' => $image]);
        }
        
        $bkashKeys = [
            'bkash_status',
            'bkash_sandbox',
            'bkash_charge',
            'bkash_key',
            'bkash_secret',
            'bkash_username',
            'bkash_password',
        ];
        
        foreach ($request->only($bkashKeys) as $key => $value) {
            BkashPGModel::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget('bkashConfig');

        return redirect()->back()->with(['messege' => __('Bkash Credentials Update Successfully'), 'alert-type' => 'success']);
    }
}
