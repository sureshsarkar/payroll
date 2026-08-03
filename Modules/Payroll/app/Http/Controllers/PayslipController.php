<?php

namespace Modules\Payroll\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Models\PayrollRun;
use Modules\Payroll\app\Services\PayrollRunService;
use Modules\Payroll\app\Support\FormXiPayslip;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee self-service — view and download own payslips (approved runs only).
 */
class PayslipController extends Controller
{
    public function __construct(private readonly PayrollRunService $runs)
    {
    }

    /** List this employee's payslips from approved/paid runs. */
    public function index(Request $request): View
    {
        $items = PayrollItem::where('user_id', $request->user()->id)
            ->whereHas('run', fn ($q) => $q->whereIn('status', [PayrollRun::ADMIN_APPROVED, PayrollRun::PAID]))
            ->with('run')
            ->get()
            ->sortByDesc(fn ($i) => sprintf('%04d%02d', $i->run->year, $i->run->month))
            ->values();

        return view('payroll::payslips', [
            'items'    => $items,
            'currency' => config('payroll.payslip.currency_symbol', '₹'),
        ]);
    }

    /** Download one payslip PDF (own only). */
    public function download(Request $request, PayrollItem $item): StreamedResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        abort_unless(in_array($item->run->status, [PayrollRun::ADMIN_APPROVED, PayrollRun::PAID], true), 403);

        // regenerate on the fly if the stored file is missing
        $disk = config('payroll.payslip.storage_disk', 'public');
        if (! $item->payslip_path || ! Storage::disk($disk)->exists($item->payslip_path)) {
            $this->runs->generatePayslip($item->refresh());
        }

        $filename = sprintf('payslip-%d-%02d.pdf', $item->run->year, $item->run->month);

        return Storage::disk($disk)->download($item->payslip_path, $filename);
    }

    /** Download the statutory Form XI pay slip for one of this employee's items. */
    public function formXi(Request $request, PayrollItem $item, FormXiPayslip $payslip)
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        abort_unless(in_array($item->run->status, [PayrollRun::ADMIN_APPROVED, PayrollRun::PAID], true), 403);

        return response($payslip->render($item), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$payslip->filename($item).'"',
        ]);
    }
}
