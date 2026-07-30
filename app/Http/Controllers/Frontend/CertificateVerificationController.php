<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CertificateCredential;

/**
 * Public certificate verification — the target of the QR + credential id printed
 * on every enterprise certificate. Confirms authenticity without exposing any
 * private data beyond what's on the certificate itself.
 */
class CertificateVerificationController extends Controller
{
    public function show(string $uid)
    {
        $credential = CertificateCredential::where('uid', $uid)->first();

        return view('frontend.certificate-verify', [
            'credential' => $credential,
            'uid'        => $uid,
        ]);
    }
}
