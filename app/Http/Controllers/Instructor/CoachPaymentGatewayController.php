<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CoachPaymentGateway;
use App\Services\Payment\CoachGatewayConfigService;
use App\Services\Payment\GatewayFieldRegistry;
use Illuminate\Http\Request;

/**
 * Enterprise coach panel → Settings → Payment gateway.
 *
 * The Enterprise gate is enforced by the `requires.enterprise` route middleware
 * (blocks non-Enterprise coaches at the URL, not just the menu). Tenant
 * isolation is STRUCTURAL: the coach id is taken from the authenticated user
 * (head coach for staff), never from the request — so a coach can only ever
 * read/write their OWN config. There is no coach_id parameter to tamper with.
 */
class CoachPaymentGatewayController extends Controller
{
    public function __construct(private CoachGatewayConfigService $service)
    {
    }

    public function index()
    {
        $coachId = $this->coachId();

        $configs = CoachPaymentGateway::where('coach_id', $coachId)
            ->where('manager_type', CoachPaymentGateway::MANAGER_COACH)
            ->get()
            ->keyBy('gateway');

        $gateways = GatewayFieldRegistry::coreGateways();

        return view('instructor.payment-gateways.index', [
            'gateways' => $gateways,
            'configs'  => $configs,
            'registry' => GatewayFieldRegistry::class,
        ]);
    }

    public function update(Request $request, string $gateway)
    {
        $gateway = strtolower($gateway);
        abort_unless(GatewayFieldRegistry::isCore($gateway), 404);

        // Required credential fields must be present (unless we already have a
        // stored value for that secret — handled by the service's keep-blank).
        $coachId  = $this->coachId();
        $existing = CoachPaymentGateway::where('coach_id', $coachId)
            ->where('manager_type', CoachPaymentGateway::MANAGER_COACH)
            ->where('gateway', $gateway)->first();

        $rules = ['status' => 'required|in:active,inactive', 'charge' => 'nullable|numeric|min:0|max:100'];
        foreach (GatewayFieldRegistry::requiredFields($gateway) as $field) {
            $hasStored = $existing && ! empty(($existing->credentials ?? [])[$field] ?? '');
            $rules[$field] = $hasStored ? 'nullable|string|max:500' : 'required|string|max:500';
        }
        $validated = $request->validate($rules);

        $this->service->save(
            $coachId,
            CoachPaymentGateway::MANAGER_COACH,
            $gateway,
            $request->all(),
            $request->file('image')
        );

        return back()->with([
            'messege'    => __(':gateway settings saved.', ['gateway' => GatewayFieldRegistry::label($gateway)]),
            'alert-type' => 'success',
        ]);
    }

    /** Head coach for staff (coach_id set); the coach themselves otherwise. */
    private function coachId(): int
    {
        $user = auth()->user();
        return (int) ($user->coach_id ?: $user->id);
    }
}
