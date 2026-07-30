<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachPaymentGateway;
use App\Models\User;
use App\Services\Payment\CoachGatewayConfigService;
use App\Services\Payment\GatewayFieldRegistry;
use Illuminate\Http\Request;

/**
 * Super-Admin → Coaches → (a coach) → Payment gateway configuration.
 *
 * Lets the Super-Admin manage a SPECIFIC coach's gateway credentials
 * (manager_type=admin → priority tier "super_admin_for_coach"). Reuses the same
 * CoachGatewayConfigService as the coach panel so behaviour is identical.
 *
 * Access: admin guard + the existing payment-gateway permission. The coach is
 * resolved + validated to be an instructor on every request (no cross-type
 * tampering); the manager_type is fixed to 'admin' server-side so this surface
 * can never overwrite a coach's own self-managed row.
 */
class CoachPaymentGatewayController extends Controller
{
    public function __construct(private CoachGatewayConfigService $service)
    {
    }

    public function index(int $coach)
    {
        checkAdminHasPermissionAndThrowException('basic.payment.view');
        $coachModel = User::where('role', 'instructor')->findOrFail($coach);

        $adminConfigs = CoachPaymentGateway::where('coach_id', $coach)
            ->where('manager_type', CoachPaymentGateway::MANAGER_ADMIN)
            ->get()->keyBy('gateway');

        // For the "coach also self-manages" indicator on each gateway.
        $selfConfigs = CoachPaymentGateway::where('coach_id', $coach)
            ->where('manager_type', CoachPaymentGateway::MANAGER_COACH)
            ->get()->keyBy('gateway');

        return view('admin.coach-payment-gateways.index', [
            'coach'        => $coachModel,
            'gateways'     => GatewayFieldRegistry::coreGateways(),
            'configs'      => $adminConfigs,
            'selfConfigs'  => $selfConfigs,
        ]);
    }

    public function update(Request $request, int $coach, string $gateway)
    {
        checkAdminHasPermissionAndThrowException('basic.payment.update');
        $coachModel = User::where('role', 'instructor')->findOrFail($coach);

        $gateway = strtolower($gateway);
        abort_unless(GatewayFieldRegistry::isCore($gateway), 404);

        $existing = CoachPaymentGateway::where('coach_id', $coachModel->id)
            ->where('manager_type', CoachPaymentGateway::MANAGER_ADMIN)
            ->where('gateway', $gateway)->first();

        $rules = ['status' => 'required|in:active,inactive', 'charge' => 'nullable|numeric|min:0|max:100'];
        foreach (GatewayFieldRegistry::requiredFields($gateway) as $field) {
            $hasStored = $existing && ! empty(($existing->credentials ?? [])[$field] ?? '');
            $rules[$field] = $hasStored ? 'nullable|string|max:500' : 'required|string|max:500';
        }
        $request->validate($rules);

        $this->service->save(
            $coachModel->id,
            CoachPaymentGateway::MANAGER_ADMIN,
            $gateway,
            $request->all(),
            $request->file('image')
        );

        return back()->with([
            'messege'    => __(':gateway settings saved for :coach.', [
                'gateway' => GatewayFieldRegistry::label($gateway),
                'coach'   => $coachModel->name,
            ]),
            'alert-type' => 'success',
        ]);
    }
}
