<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class BankInformationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'bank_name' => 'required|string|max:190',
            'account_number' => 'required|string|max:190',
            'routing_number' => 'nullable|string|max:190',
            'branch' => 'required|string|max:190',
            // FT-PAY-7 fix (2026-05-27) — `transaction` was unvalidated.
            // BasicPayment\PaymentController::pay_via_bank() reads
            // $request->transaction and (a) stores it inside the
            // payment_details JSON and (b) compares it against every
            // other order's payment_details to detect duplicate-receipt
            // fraud. With no rule, an empty / array / 10KB payload was
            // accepted, which:
            //   - lets a fraudster skip the dup-check on first submit
            //     by sending no value (empty == empty across rows so
            //     the loop short-circuits to "not present"),
            //   - lets an attacker pass an array (Laravel allows
            //     arbitrary input types when no validator runs against
            //     the key), triggering type errors deep in the
            //     persistence path,
            //   - allows oversize values that may exceed the
            //     payment_details column length once JSON-encoded with
            //     the other fields.
            // Tightened to: required string, length-bounded to 64 chars
            // (typical bank-transfer reference IDs are 8–32 alnum chars;
            //  64 gives slack for IBAN-style refs without enabling abuse).
            'transaction' => 'required|string|max:64',
        ];
    }

    public function messages()
    {
        return [
            'bank_name.required' => __('Bank Name is required.'),
            'account_number.required' => __('Account Number is required.'),
            'routing_number.required' => __('Routing Number is required.'),
            'branch.required' => __('Branch is required.'),
            'transaction.required' => __('Transaction reference is required.'),
            'transaction.string'   => __('Transaction reference must be a text value.'),
            'transaction.max'      => __('Transaction reference may not be longer than 64 characters.'),
        ];
    }
}
