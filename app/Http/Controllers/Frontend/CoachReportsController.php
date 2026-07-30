<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Order\app\Models\OrderItem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Centralised coach Reports module (2026-07-18, Dashboard Nav Enhancement
 * #6/#7). One controller, four report types — Revenue, Payments, Invoices,
 * Attendance — each rendered through a single data-driven view and a shared
 * export pipeline (CSV / Excel / PDF). No parallel money logic: every currency
 * figure is produced by the certified OrderItem::netPaid / commissionAmount /
 * coachPayout methods (never re-derived in SQL here). Fully tenant-scoped: a
 * coach only ever sees rows for the courses they own (added_by / instructor_id)
 * — the coach id comes from auth, never the request, so there is no IDOR seam.
 * White-label safe (no hard-coded coach / domain / currency).
 */
class CoachReportsController extends Controller
{
    private const TYPES = ['revenue', 'payments', 'invoices', 'attendance'];

    /** The coach whose data this panel shows (a staff member inherits their coach). */
    private function coachId(): int
    {
        $u = userAuth();
        return $u->role === 'instructor' ? (int) $u->id : (int) ($u->coach_id ?? 0);
    }

    /** Course ids owned by this coach — the tenant boundary for every report. */
    private function courseIds(): array
    {
        $coachId = $this->coachId();
        return Course::where(fn ($q) => $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    /** Course id => title, for the filter dropdown (tenant-scoped). */
    private function coachCourses(): Collection
    {
        return Course::whereIn('id', $this->courseIds())
            ->orderBy('title')->pluck('title', 'id');
    }

    /** Resolve the [from, to] date window from the request (defaults: this month). */
    private function dateWindow(Request $r): array
    {
        $from = $r->filled('from') ? $r->date('from')->startOfDay() : now()->startOfMonth();
        $to   = $r->filled('to')   ? $r->date('to')->endOfDay()     : now()->endOfDay();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        return [$from, $to];
    }

    private function primaryCurrency(): string
    {
        return function_exists('getSessionCurrency') ? (getSessionCurrency() ?: 'USD') : 'USD';
    }

    private function money($amount, ?string $code = null): string
    {
        $code = $code ?: $this->primaryCurrency();
        return function_exists('formatMoney') ? formatMoney($amount, $code) : number_format((float) $amount, 2);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Screens
    // ─────────────────────────────────────────────────────────────────────

    /** Reports hub: headline KPIs + cards linking to each report. */
    public function index(Request $r)
    {
        [$from, $to] = $this->dateWindow($r);
        $courseIds = $this->courseIds();

        $items = $this->paidItems($courseIds, $from, $to)->get();
        $gross = $commission = $payout = 0.0;
        foreach ($items as $it) {
            $rate = $it->order->commission_rate ?? null;
            $gross      += $it->netPaid();
            $commission += $it->commissionAmount($rate);
            $payout     += $it->coachPayout($rate);
        }
        $orderCount = $items->pluck('order_id')->unique()->count();

        $kpis = [
            ['label' => __('Gross Collected'),  'value' => $this->money($gross),      'icon' => 'bi-cash-coin',        'accent' => '#10b981'],
            ['label' => __('Admin Commission'), 'value' => $this->money($commission), 'icon' => 'bi-diagram-3',        'accent' => '#f59e0b'],
            ['label' => __('Your Net Payout'),  'value' => $this->money($payout),     'icon' => 'bi-wallet2',          'accent' => '#0ea5e9'],
            ['label' => __('Paid Orders'),      'value' => number_format($orderCount),'icon' => 'bi-bag-check',        'accent' => '#7c3aed'],
        ];

        $cards = [
            ['type' => 'revenue',    'title' => __('Revenue Report'),    'desc' => __('Course-wise gross, commission and your net payout.'), 'icon' => 'bi-graph-up-arrow'],
            ['type' => 'payments',   'title' => __('Payments Report'),   'desc' => __('Every paid transaction with student, course and amount.'), 'icon' => 'bi-credit-card'],
            ['type' => 'invoices',   'title' => __('Invoices Report'),   'desc' => __('Orders as invoices — number, buyer, total and status.'), 'icon' => 'bi-receipt'],
            ['type' => 'attendance', 'title' => __('Attendance Report'), 'desc' => __('Live-class attendance across your courses.'), 'icon' => 'bi-calendar-check'],
        ];

        return view('frontend.instructor-dashboard.reports.index', compact('kpis', 'cards', 'from', 'to'));
    }

    /** Render one report (paginated HTML). */
    public function show(Request $r, string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        $report = $this->build($type, $r);

        // Manual paginator over the already-tenant-scoped, filtered, sorted rows.
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = $report['rows']->slice(($page - 1) * $perPage, $perPage)->values();
        $rows = new LengthAwarePaginator($slice, $report['rows']->count(), $perPage, $page, [
            'path' => $r->url(), 'query' => $r->query(),
        ]);

        $courses = $this->coachCourses();
        return view('frontend.instructor-dashboard.reports.show', compact('report', 'rows', 'courses'));
    }

    /** Export one report as csv | xlsx | pdf. */
    public function export(Request $r, string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        $format = strtolower($r->get('format', 'csv'));
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $report = $this->build($type, $r);
        $base = 'report-' . $type . '-' . now()->format('Ymd-His');

        return match ($format) {
            'csv'  => $this->streamCsv($report, $base . '.csv'),
            'xlsx' => $this->streamXls($report, $base . '.xls'),
            'pdf'  => $this->streamPdf($report, $base . '.pdf'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Report builders — each returns a normalized structure:
    //   ['key','title','columns'=>[[key,label,align,money?]], 'rows'=>Collection, 'summary'=>[cards]]
    // ─────────────────────────────────────────────────────────────────────

    private function build(string $type, Request $r): array
    {
        [$from, $to] = $this->dateWindow($r);
        $courseIds = $this->courseIds();
        $method = 'build' . Str::studly($type);
        $report = $this->{$method}($r, $courseIds, $from, $to);
        $report['type']  = $type;
        $report['from']  = $from;
        $report['to']    = $to;
        $report['sort']  = $r->get('sort');
        $report['dir']   = strtolower($r->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $report['search'] = trim((string) $r->get('q', ''));
        $report['course'] = $r->get('course_id');

        // Generic search + sort applied to the normalized rows.
        if ($report['search'] !== '') {
            $needle = Str::lower($report['search']);
            $report['rows'] = $report['rows']->filter(function ($row) use ($needle) {
                foreach ($row as $v) {
                    if (is_scalar($v) && Str::contains(Str::lower((string) $v), $needle)) return true;
                }
                return false;
            })->values();
        }
        if ($report['sort']) {
            $key = $report['sort'];
            $report['rows'] = $report['rows']->sortBy(function ($row) use ($key) {
                return $row['_sort'][$key] ?? ($row[$key] ?? null);
            }, SORT_REGULAR, $report['dir'] === 'desc')->values();
        }
        return $report;
    }

    /** Paid order-items for the coach's courses within the window. */
    private function paidItems(array $courseIds, $from, $to)
    {
        return OrderItem::query()
            ->whereIn('course_id', $courseIds)
            ->with(['order', 'course'])
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid')->whereBetween('created_at', [$from, $to]));
    }

    private function buildRevenue(Request $r, array $courseIds, $from, $to): array
    {
        $q = $this->paidItems($courseIds, $from, $to);
        if ($r->filled('course_id')) $q->where('course_id', (int) $r->get('course_id'));

        $byCourse = [];
        $tGross = $tComm = $tPayout = 0.0;
        foreach ($q->get() as $it) {
            $cid = (int) $it->course_id;
            $rate = $it->order->commission_rate ?? null;
            $net = $it->netPaid(); $comm = $it->commissionAmount($rate); $pay = $it->coachPayout($rate);
            $byCourse[$cid] ??= ['course' => $it->course->title ?? ('#' . $cid), 'orders' => [], 'gross' => 0.0, 'comm' => 0.0, 'payout' => 0.0];
            $byCourse[$cid]['orders'][$it->order_id] = true;
            $byCourse[$cid]['gross'] += $net; $byCourse[$cid]['comm'] += $comm; $byCourse[$cid]['payout'] += $pay;
            $tGross += $net; $tComm += $comm; $tPayout += $pay;
        }

        $rows = collect($byCourse)->map(fn ($c) => [
            'course'     => $c['course'],
            'orders'     => count($c['orders']),
            'gross'      => $this->money($c['gross']),
            'commission' => $this->money($c['comm']),
            'payout'     => $this->money($c['payout']),
            '_sort'      => ['gross' => $c['gross'], 'commission' => $c['comm'], 'payout' => $c['payout'], 'orders' => count($c['orders'])],
        ])->values();

        return [
            'key' => 'revenue', 'title' => __('Revenue Report'),
            'columns' => [
                ['key' => 'course', 'label' => __('Course'), 'align' => 'left'],
                ['key' => 'orders', 'label' => __('Orders'), 'align' => 'right', 'sortable' => true],
                ['key' => 'gross', 'label' => __('Gross Collected'), 'align' => 'right', 'sortable' => true],
                ['key' => 'commission', 'label' => __('Commission'), 'align' => 'right', 'sortable' => true],
                ['key' => 'payout', 'label' => __('Net Payout'), 'align' => 'right', 'sortable' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => __('Gross Collected'), 'value' => $this->money($tGross), 'accent' => '#10b981'],
                ['label' => __('Admin Commission'), 'value' => $this->money($tComm), 'accent' => '#f59e0b'],
                ['label' => __('Your Net Payout'), 'value' => $this->money($tPayout), 'accent' => '#0ea5e9'],
            ],
        ];
    }

    private function buildPayments(Request $r, array $courseIds, $from, $to): array
    {
        $q = $this->paidItems($courseIds, $from, $to);
        if ($r->filled('course_id')) $q->where('course_id', (int) $r->get('course_id'));

        $tPaid = $tPayout = 0.0;
        $rows = $q->get()->map(function ($it) use (&$tPaid, &$tPayout) {
            $o = $it->order; $rate = $o->commission_rate ?? null;
            $net = $it->netPaid(); $pay = $it->coachPayout($rate);
            $tPaid += $net; $tPayout += $pay;
            $cur = $o->payable_currency ?: $this->primaryCurrency();
            return [
                'date'    => optional($o->created_at)->format('d M Y'),
                'invoice' => $o->invoice_id ?: ('#' . $o->id),
                'student' => optional($o->user)->name ?? '—',
                'course'  => $it->course->title ?? ('#' . $it->course_id),
                'paid'    => $this->money($net, $cur),
                'payout'  => $this->money($pay, $cur),
                '_sort'   => ['paid' => $net, 'payout' => $pay, 'date' => optional($o->created_at)->timestamp],
            ];
        })->values();

        return [
            'key' => 'payments', 'title' => __('Payments Report'),
            'columns' => [
                ['key' => 'date', 'label' => __('Date'), 'align' => 'left', 'sortable' => true],
                ['key' => 'invoice', 'label' => __('Invoice'), 'align' => 'left'],
                ['key' => 'student', 'label' => __('Student'), 'align' => 'left'],
                ['key' => 'course', 'label' => __('Course'), 'align' => 'left'],
                ['key' => 'paid', 'label' => __('Amount Paid'), 'align' => 'right', 'sortable' => true],
                ['key' => 'payout', 'label' => __('Your Payout'), 'align' => 'right', 'sortable' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => __('Transactions'), 'value' => number_format($rows->count()), 'accent' => '#7c3aed'],
                ['label' => __('Total Paid'), 'value' => $this->money($tPaid), 'accent' => '#10b981'],
                ['label' => __('Your Payout'), 'value' => $this->money($tPayout), 'accent' => '#0ea5e9'],
            ],
        ];
    }

    private function buildInvoices(Request $r, array $courseIds, $from, $to): array
    {
        // One row per order that contains this coach's courses; the amount is the
        // coach's own netPaid share of that order (not the whole-order total).
        $items = $this->paidItems($courseIds, $from, $to)->get();
        if ($r->filled('course_id')) $items = $items->where('course_id', (int) $r->get('course_id'));

        $byOrder = [];
        $tTotal = 0.0;
        foreach ($items as $it) {
            $o = $it->order; if (!$o) continue;
            $oid = (int) $o->id;
            $byOrder[$oid] ??= ['order' => $o, 'items' => 0, 'amount' => 0.0];
            $byOrder[$oid]['items']++;
            $byOrder[$oid]['amount'] += $it->netPaid();
            $tTotal += $it->netPaid();
        }

        $rows = collect($byOrder)->map(function ($g) {
            $o = $g['order']; $cur = $o->payable_currency ?: $this->primaryCurrency();
            return [
                'invoice' => $o->invoice_id ?: ('#' . $o->id),
                'date'    => optional($o->created_at)->format('d M Y'),
                'buyer'   => optional($o->user)->name ?? '—',
                'items'   => $g['items'],
                'amount'  => $this->money($g['amount'], $cur),
                'status'  => ucfirst($o->payment_status ?? 'paid'),
                '_sort'   => ['amount' => $g['amount'], 'date' => optional($o->created_at)->timestamp, 'items' => $g['items']],
            ];
        })->values();

        return [
            'key' => 'invoices', 'title' => __('Invoices Report'),
            'columns' => [
                ['key' => 'invoice', 'label' => __('Invoice #'), 'align' => 'left'],
                ['key' => 'date', 'label' => __('Date'), 'align' => 'left', 'sortable' => true],
                ['key' => 'buyer', 'label' => __('Buyer'), 'align' => 'left'],
                ['key' => 'items', 'label' => __('Items'), 'align' => 'right', 'sortable' => true],
                ['key' => 'amount', 'label' => __('Your Amount'), 'align' => 'right', 'sortable' => true],
                ['key' => 'status', 'label' => __('Status'), 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => __('Invoices'), 'value' => number_format($rows->count()), 'accent' => '#7c3aed'],
                ['label' => __('Your Total'), 'value' => $this->money($tTotal), 'accent' => '#10b981'],
            ],
        ];
    }

    private function buildAttendance(Request $r, array $courseIds, $from, $to): array
    {
        // Per live-class attendance summary across the coach's courses. Uses the
        // persisted live_class_attendances rows (role = student).
        $courseFilter = $r->filled('course_id') ? [(int) $r->get('course_id')] : $courseIds;

        // course_live_classes has no title column (it links via lesson_id / batch_id);
        // label each class by id + course + date, which is stable across the schema.
        $classes = DB::table('course_live_classes as lc')
            ->whereIn('lc.course_id', $courseFilter)
            ->whereBetween('lc.created_at', [$from, $to])
            ->leftJoin('courses as c', 'c.id', '=', 'lc.course_id')
            ->select('lc.id', 'lc.start_time', 'c.title as course_title')
            ->get();

        $attByClass = DB::table('live_class_attendances')
            ->select('course_live_class_id',
                DB::raw("COUNT(DISTINCT CASE WHEN role='student' THEN user_id END) as joined"),
                DB::raw("COUNT(DISTINCT CASE WHEN role='student' AND attendance_verified=1 THEN user_id END) as verified"))
            ->whereIn('course_live_class_id', $classes->pluck('id'))
            ->groupBy('course_live_class_id')
            ->get()->keyBy('course_live_class_id');

        $tJoined = 0; $tVerified = 0;
        $rows = $classes->map(function ($lc) use ($attByClass, &$tJoined, &$tVerified) {
            $a = $attByClass->get($lc->id);
            $joined = (int) ($a->joined ?? 0); $verified = (int) ($a->verified ?? 0);
            $tJoined += $joined; $tVerified += $verified;
            $date = $lc->start_time ? substr((string) $lc->start_time, 0, 10) : null;
            return [
                'class'    => __('Live Class') . ' #' . $lc->id,
                'course'   => $lc->course_title ?: '—',
                'date'     => $date,
                'joined'   => $joined,
                'verified' => $verified,
                '_sort'    => ['joined' => $joined, 'verified' => $verified, 'date' => $date],
            ];
        })->values();

        return [
            'key' => 'attendance', 'title' => __('Attendance Report'),
            'columns' => [
                ['key' => 'class', 'label' => __('Live Class'), 'align' => 'left'],
                ['key' => 'course', 'label' => __('Course'), 'align' => 'left'],
                ['key' => 'date', 'label' => __('Date'), 'align' => 'left', 'sortable' => true],
                ['key' => 'joined', 'label' => __('Students Joined'), 'align' => 'right', 'sortable' => true],
                ['key' => 'verified', 'label' => __('Verified'), 'align' => 'right', 'sortable' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => __('Live Classes'), 'value' => number_format($rows->count()), 'accent' => '#7c3aed'],
                ['label' => __('Total Joins'), 'value' => number_format($tJoined), 'accent' => '#10b981'],
                ['label' => __('Verified Joins'), 'value' => number_format($tVerified), 'accent' => '#0ea5e9'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Exporters (no external Excel dependency; dompdf is already installed)
    // ─────────────────────────────────────────────────────────────────────

    private function exportMatrix(array $report): array
    {
        $headers = array_map(fn ($c) => $c['label'], $report['columns']);
        $keys = array_map(fn ($c) => $c['key'], $report['columns']);
        $data = $report['rows']->map(function ($row) use ($keys) {
            return array_map(fn ($k) => (string) ($row[$k] ?? ''), $keys);
        })->all();
        return [$headers, $data];
    }

    private function streamCsv(array $report, string $filename): StreamedResponse
    {
        [$headers, $data] = $this->exportMatrix($report);
        return response()->streamDownload(function () use ($headers, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents/₹ correctly
            fputcsv($out, $headers);
            foreach ($data as $line) fputcsv($out, $line);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** SpreadsheetML 2003 — opens natively in Excel, needs no PHP Excel library. */
    private function streamXls(array $report, string $filename): StreamedResponse
    {
        [$headers, $data] = $this->exportMatrix($report);
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return response()->streamDownload(function () use ($headers, $data, $esc, $report) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Worksheet ss:Name="' . $esc(Str::limit($report['title'], 28, '')) . '"><Table>';
            echo '<Row>';
            foreach ($headers as $h) echo '<Cell><Data ss:Type="String">' . $esc($h) . '</Data></Cell>';
            echo '</Row>';
            foreach ($data as $line) {
                echo '<Row>';
                foreach ($line as $cell) echo '<Cell><Data ss:Type="String">' . $esc($cell) . '</Data></Cell>';
                echo '</Row>';
            }
            echo '</Table></Worksheet></Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function streamPdf(array $report, string $filename): \Illuminate\Http\Response
    {
        // $brand is provided by the global view composer; the PDF view reads it
        // null-safely so a coach's white-label name/logo appears on the export.
        $html = view('frontend.instructor-dashboard.reports.pdf', compact('report'))->render();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
