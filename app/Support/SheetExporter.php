<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared export helper for the payroll system's "View + download" sheets
 * (attendance, payroll, leave, employees). Produces CSV, Excel (.xls via an
 * HTML table — no PhpSpreadsheet dependency, same convention as
 * CoachReportsController) and PDF (dompdf).
 *
 * Usage:
 *   return app(SheetExporter::class)->download($format, 'Attendance July 2026',
 *       ['Employee','Present','Absent','Net'], $rows);
 */
class SheetExporter
{
    /** @param array<int,string> $headers  @param array<int,array<int,scalar|null>> $rows */
    public function download(string $format, string $title, array $headers, array $rows, string $orientation = 'portrait'): Response|StreamedResponse
    {
        return match (strtolower($format)) {
            'csv'         => $this->csv($title, $headers, $rows),
            'pdf'         => $this->pdf($title, $headers, $rows, $orientation),
            'xls', 'xlsx' => $this->xls($title, $headers, $rows),
            default       => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    public function csv(string $title, array $headers, array $rows): StreamedResponse
    {
        $filename = $this->filename($title, 'csv');

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Excel-compatible .xls built from an HTML table. Excel/LibreOffice open it
     * natively; avoids adding a spreadsheet library.
     */
    public function xls(string $title, array $headers, array $rows): StreamedResponse
    {
        $filename = $this->filename($title, 'xls');
        $html = $this->tableHtml($title, $headers, $rows, forExcel: true);

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function pdf(string $title, array $headers, array $rows, string $orientation = 'portrait'): Response
    {
        $html = $this->tableHtml($title, $headers, $rows, forExcel: false);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', $orientation === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($title, 'pdf').'"',
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function tableHtml(string $title, array $headers, array $rows, bool $forExcel): string
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $style = $forExcel ? '' : '<style>
            *{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#222}
            h3{color:#1f2d3d;margin:0 0 8px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #cfd6e0;padding:5px 7px}
            th{background:#f1f4f8;text-align:left}
            .meta{color:#888;font-size:9px;margin-top:10px}
        </style>';

        $head = '';
        foreach ($headers as $h) {
            $head .= '<th>'.$esc($h).'</th>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($row as $cell) {
                $body .= '<td>'.$esc($cell).'</td>';
            }
            $body .= '</tr>';
        }

        $company = $esc(config('app.name'));
        $generated = now()->format('d M Y H:i');

        return "<!doctype html><html><head><meta charset='utf-8'>{$style}</head><body>"
            ."<h3>{$company} — {$esc($title)}</h3>"
            ."<table><thead><tr>{$head}</tr></thead><tbody>{$body}</tbody></table>"
            ."<p class='meta'>Generated {$generated}</p>"
            .'</body></html>';
    }

    private function filename(string $title, string $ext): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($title));
        $slug = trim($slug, '-');

        return $slug.'-'.now()->format('Ymd').'.'.$ext;
    }
}
