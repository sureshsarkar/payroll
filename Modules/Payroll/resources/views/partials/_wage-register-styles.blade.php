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
{{-- 44mm top margin = the reserved letterhead strip (dompdf renders the block
     ~32mm tall at these font sizes) plus the "Page X of Y" line stamped above
     it. The strip must clear the table's <thead> or the fixed header overpaints
     the first column headings on page 1. --}}
@page { margin: 44mm 3mm 8mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #000; font-family: DejaVu Sans, sans-serif; font-size: 9.5px; }
.sheet { padding: 0; }
.running-header {
    position: fixed;
    top: -37mm;            /* climb out of the content box, up into the top margin */
    left: 0; right: 0;
    background: #fff;
    border: 1px solid #000;
    padding: 3px 4px;
}
.hdr { width: 100%; border-collapse: collapse; }
.hdr td { vertical-align: top; padding: 0; }
.title { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
.subtitle { text-align: center; font-size: 9.5px; }
.form-meta { text-align: right; font-size: 9.5px; line-height: 13px; }
.form-meta .strong { font-weight: bold; }
.estab { font-size: 11.5px; line-height: 15px; margin-top: 3px; }
.estab .lbl { display: inline-block; min-width: 96px; }
.reg-for { font-size: 11.5px; font-weight: bold; margin: 4px 0 1px; }
.stat-no { font-size: 10.5px; line-height: 14px; text-align: right; }

table.reg { border-collapse: collapse; width: 100%; table-layout: fixed; margin-top: 0; }
.reg th, .reg td { border: 1px solid #000; padding: 1.5px 2px; vertical-align: top; text-align: center; overflow-wrap: break-word; }
{{-- Header labels: center them and only break at spaces so a word like
     "Signature" is never split mid-word. --}}
.reg th { font-weight: bold; font-size: 9px; line-height: 11.5px; background: #fff; vertical-align: middle; overflow-wrap: normal; }
.reg td { font-size: 8.5px; }
.reg .l { text-align: left; }
{{-- Money columns are right-aligned and kept on one line: the identity text is
     what needed enlarging for readability, and letting "1,234.56" wrap to two
     lines in a narrow statutory column looks worse than a hair of overflow. --}}
.reg .r { text-align: right; white-space: nowrap; }
{{-- Identity/attendance/rate text was formerly shrunk to ~6.5px to pack rows;
     the register almost always fits well within a page, so it is sized for
     readability instead. --}}
.reg .ident { line-height: 12.5px; }
.reg .ident .nm { font-weight: bold; font-size: 10.5px; }
.reg .ident .sub { font-size: 9px; }
.mini { width: 100%; border-collapse: collapse; }
.mini td { border: none; padding: 0 1px; font-size: 9px; line-height: 12px; }
.mini td.k { text-align: left; }
.mini td.v { text-align: right; font-weight: bold; }
/* Not bolding every totals cell on purpose: bold DejaVu Sans is measurably
   wider than regular, and that was enough to wrap "40,000"/"16,000" onto two
   lines in this row while the identical, non-bold value fit on one line in
   the data row above — a font-weight side effect, not a column-width one.
   Keep bold only on the label and the two figures that matter most. */
.reg tr.totals td.l, .reg tr.totals td.emph { font-weight: bold; }
.foot { font-size: 9px; margin-top: 4px; text-align: right; color: #333; }
</style>
