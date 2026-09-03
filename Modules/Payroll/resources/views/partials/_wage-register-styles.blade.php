{{-- Shared print styles for the Delhi Form IV "Register of Payment of Wages /
     Salary". Used by the whole-run register and the "all salary slips" batch
     (both wage-register-pdf, differing only in row count) so every export
     renders pixel-identically.

     Pagination model: the top page margin is reserved space; the company
     letterhead (.running-header) is position:fixed with a negative top so
     dompdf lifts it into that reserved strip and repaints it on every page,
     while the statutory column headings ride in the table's <thead> so dompdf
     repeats those too. The row list then flows across as many pages as needed. --}}
<style>
{{-- 34mm top margin = the reserved letterhead strip (~10mm..30mm) plus the
     "Page X of Y" line the controller stamps above it (~5mm). --}}
@page { margin: 34mm 3mm 8mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #000; font-family: DejaVu Sans, sans-serif; font-size: 7.5px; }
.sheet { padding: 0; }
.running-header {
    position: fixed;
    top: -24mm;            /* climb out of the content box, up into the top margin */
    left: 0; right: 0;
    background: #fff;
    border: 1px solid #000;
    padding: 2px 4px 2px;
}
.hdr { width: 100%; border-collapse: collapse; }
.hdr td { vertical-align: top; padding: 0; }
.title { text-align: center; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
.subtitle { text-align: center; font-size: 7.5px; }
.form-meta { text-align: right; font-size: 7.5px; line-height: 10px; }
.form-meta .strong { font-weight: bold; }
.estab { font-size: 8.5px; line-height: 11px; margin-top: 2px; }
.estab .lbl { display: inline-block; min-width: 82px; }
.reg-for { font-size: 8.5px; font-weight: bold; margin: 2px 0 1px; }
.stat-no { font-size: 8px; line-height: 11px; text-align: right; }

table.reg { border-collapse: collapse; width: 100%; table-layout: fixed; margin-top: 0; }
.reg th, .reg td { border: 1px solid #000; padding: 0.5px 1px; vertical-align: top; text-align: center; overflow-wrap: break-word; }
.reg th { font-weight: bold; font-size: 7px; line-height: 8.5px; background: #fff; }
.reg td { font-size: 7px; }
.reg .l { text-align: left; }
.reg .r { text-align: right; }
{{-- Identity/attendance/rate text is kept close to the 7px numeric size so a
     page holds as many employee rows as the reference register does; only the
     name is nudged up and bolded to stay scannable. --}}
.reg .ident { line-height: 9px; }
.reg .ident .nm { font-weight: bold; font-size: 8px; }
.reg .ident .sub { font-size: 6.5px; }
.mini { width: 100%; border-collapse: collapse; }
.mini td { border: none; padding: 0 0.5px; font-size: 6.5px; line-height: 8.5px; }
.mini td.k { text-align: left; }
.mini td.v { text-align: right; font-weight: bold; }
/* Not bolding every totals cell on purpose: bold DejaVu Sans is measurably
   wider than regular, and that was enough to wrap "40,000"/"16,000" onto two
   lines in this row while the identical, non-bold value fit on one line in
   the data row above — a font-weight side effect, not a column-width one.
   Keep bold only on the label and the two figures that matter most. */
.reg tr.totals td.l, .reg tr.totals td.emph { font-weight: bold; }
.foot { font-size: 7px; margin-top: 3px; text-align: right; color: #333; }
</style>
