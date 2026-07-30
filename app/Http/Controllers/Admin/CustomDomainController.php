<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachDomain;
use App\Services\CustomDomainSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Superadmin custom-domain manager (2026-06 governance layer).
 *
 * View every coach domain, see DNS + SSL + verification status, and
 * approve / reject / suspend / resume / remove / re-check. Plus the
 * feature controls: enable the feature, set the server IP coaches point
 * at, toggle strict approval mode, set the per-coach quota.
 *
 * Auth: admin guard (route group). RBAC: custom_domain.view /
 * custom_domain.manage / custom_domain.settings.
 */
class CustomDomainController extends Controller
{
    public function __construct(private CustomDomainSettings $settings) {}

    /** List + filters + summary cards. */
    public function index(Request $request): View
    {
        checkAdminHasPermissionAndThrowException('custom_domain.view');

        $q = CoachDomain::query()->with('coach:id,name,email')->where('kind', 'custom');

        if ($status = $request->get('status')) {
            $q->where('status', $status);
        }
        if ($keyword = trim((string) $request->get('q', ''))) {
            $q->where(function ($x) use ($keyword) {
                $x->where('hostname', 'like', "%$keyword%")
                  ->orWhereHas('coach', fn ($c) => $c->where('name', 'like', "%$keyword%")
                      ->orWhere('email', 'like', "%$keyword%"));
            });
        }

        $domains = $q->orderByDesc('id')->paginate(25)->withQueryString();

        $totals = [
            'total'     => CoachDomain::custom()->count(),
            'pending'   => CoachDomain::custom()->where('status', CoachDomain::STATUS_PENDING)->count(),
            'verified'  => CoachDomain::custom()->where('status', CoachDomain::STATUS_VERIFIED)->count(),
            'active'    => CoachDomain::custom()->where('status', CoachDomain::STATUS_ACTIVE)->count(),
            'failed'    => CoachDomain::custom()->where('status', CoachDomain::STATUS_FAILED)->count(),
            'suspended' => CoachDomain::custom()->where('status', CoachDomain::STATUS_SUSPENDED)->count(),
        ];

        $settings = $this->settings;

        return view('admin.custom-domains.index', compact('domains', 'totals', 'settings'));
    }

    /** Detail + audit timeline for one domain. */
    public function show(CoachDomain $domain): View
    {
        checkAdminHasPermissionAndThrowException('custom_domain.view');
        $domain->load('coach:id,name,email', 'events');
        return view('admin.custom-domains.show', compact('domain'));
    }

    /** Feature controls page. */
    public function settings(): View
    {
        checkAdminHasPermissionAndThrowException('custom_domain.settings');
        $settings = $this->settings;
        return view('admin.custom-domains.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('custom_domain.settings');

        $data = $request->validate([
            'custom_domain_enabled'           => ['nullable', 'boolean'],
            'custom_domain_requires_approval' => ['nullable', 'boolean'],
            'custom_domain_server_ip'         => ['nullable', 'string', 'max:255'],
            'custom_domain_max_per_coach'     => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $this->settings->put([
            CustomDomainSettings::KEY_ENABLED   => $request->boolean('custom_domain_enabled') ? '1' : '0',
            CustomDomainSettings::KEY_APPROVAL  => $request->boolean('custom_domain_requires_approval') ? '1' : '0',
            CustomDomainSettings::KEY_SERVER_IP => trim((string) ($data['custom_domain_server_ip'] ?? '')),
            CustomDomainSettings::KEY_MAX       => (string) $data['custom_domain_max_per_coach'],
        ]);

        return back()->with(['messege' => __('Custom-domain settings saved'), 'alert-type' => 'success']);
    }

    /* ───────── lifecycle actions ───────── */

    public function approve(CoachDomain $domain): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('custom_domain.manage');

        $domain->markStatus(CoachDomain::STATUS_ACTIVE, [
            'fill'  => ['verified_at' => now(), 'approved_at' => now(),
                        'approved_by' => (int) Auth::guard('admin')->id(), 'rejected_reason' => null],
            'event' => 'approved',
            'actor_type' => 'admin', 'actor_id' => (int) Auth::guard('admin')->id(),
        ]);

        return back()->with(['messege' => __('Domain approved and activated'), 'alert-type' => 'success']);
    }

    public function reject(Request $request, CoachDomain $domain): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('custom_domain.manage');

        $domain->markStatus(CoachDomain::STATUS_FAILED, [
            'fill'  => ['verified_at' => null,
                        'rejected_reason' => (string) $request->input('reason', '')],
            'event' => 'rejected',
            'actor_type' => 'admin', 'actor_id' => (int) Auth::guard('admin')->id(),
            'meta'  => ['reason' => (string) $request->input('reason', '')],
        ]);

        return back()->with(['messege' => __('Domain rejected'), 'alert-type' => 'success']);
    }

    public function suspend(CoachDomain $domain): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('custom_domain.manage');

        $domain->markStatus(CoachDomain::STATUS_SUSPENDED, [
            'event' => 'suspended',
            'actor_type' => 'admin', 'actor_id' => (int) Auth::guard('admin')->id(),
        ]);

        return back()->with(['messege' => __('Domain suspended — it has stopped serving'), 'alert-type' => 'success']);
    }

    public function resume(CoachDomain $domain): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('custom_domain.manage');

        $domain->markStatus(CoachDomain::STATUS_ACTIVE, [
            'fill'  => ['verified_at' => $domain->verified_at ?? now()],
            'event' => 'resumed',
            'actor_type' => 'admin', 'actor_id' => (int) Auth::guard('admin')->id(),
        ]);

        return back()->with(['messege' => __('Domain resumed'), 'alert-type' => 'success']);
    }

    public function remove(CoachDomain $domain): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('custom_domain.manage');

        // Audit BEFORE delete so the event keeps the hostname snapshot.
        CoachDomain::recordEvent($domain, 'removed', [
            'actor_type' => 'admin', 'actor_id' => (int) Auth::guard('admin')->id(),
        ]);
        $host = $domain->hostname;
        $domain->delete();
        CoachDomain::forgetCacheForHost($host);

        return back()->with(['messege' => __('Domain removed'), 'alert-type' => 'success']);
    }

    public function recheck(CoachDomain $domain): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('custom_domain.manage');

        Artisan::call('domains:recheck-pending', ['--id' => $domain->id, '--force' => true]);

        $fresh = $domain->fresh();
        $ok = $fresh->status === CoachDomain::STATUS_ACTIVE || $fresh->status === CoachDomain::STATUS_VERIFIED;

        return back()->with([
            'messege'    => $ok ? __('Re-check passed — domain is now :status', ['status' => $fresh->status])
                                : __('Re-check ran — domain still does not point to us'),
            'alert-type' => $ok ? 'success' : 'warning',
        ]);
    }
}
