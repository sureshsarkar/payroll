{{-- Shared, self-contained design system for all payroll-system module pages.
     Scoped under .pv so it never depends on (or fights) the host panel's CSS
     framework/version. Include once at the top of a page's content section and
     wrap the page in <div class="pv"> … </div>. --}}
@once
<style>
    .pv{--pv-bg:#f5f7fb;--pv-card:#fff;--pv-ink:#0f172a;--pv-sub:#64748b;--pv-mut:#94a3b8;
        --pv-line:#e8ecf3;--pv-line2:#f1f4f9;--pv-brand:#4f46e5;--pv-brand-2:#6366f1;
        --pv-sky:#0ea5e9;--pv-green:#059669;--pv-amber:#d97706;--pv-red:#dc2626;--pv-violet:#7c3aed;
        --pv-r:14px;--pv-r-sm:10px;
        font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        color:var(--pv-ink);line-height:1.45;-webkit-font-smoothing:antialiased;}
    .pv *{box-sizing:border-box;}
    .pv a{text-decoration:none;}

    /* header */
    .pv-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:2px 0 22px;}
    .pv-head .t{font-size:22px;font-weight:700;margin:0;letter-spacing:-.01em;}
    .pv-head .s{font-size:13px;color:var(--pv-sub);margin:2px 0 0;}
    .pv-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center;}

    /* stat tiles */
    .pv-stats{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:20px;}
    .pv-stat{background:var(--pv-card);border:1px solid var(--pv-line);border-radius:var(--pv-r);
        padding:18px 18px;box-shadow:0 1px 2px rgba(16,24,40,.04);position:relative;overflow:hidden;}
    .pv-stat::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--c,var(--pv-brand));opacity:.9;}
    .pv-stat .n{font-size:27px;font-weight:750;line-height:1;color:var(--c,var(--pv-ink));}
    .pv-stat .l{font-size:12.5px;color:var(--pv-sub);margin-top:7px;font-weight:500;}
    .pv-stat .sub{font-size:11px;color:var(--pv-mut);margin-top:2px;}

    /* cards */
    .pv-card{background:var(--pv-card);border:1px solid var(--pv-line);border-radius:var(--pv-r);
        box-shadow:0 1px 2px rgba(16,24,40,.04);margin-bottom:18px;}
    .pv-card>.h{padding:15px 20px;border-bottom:1px solid var(--pv-line2);font-weight:650;font-size:14.5px;
        display:flex;align-items:center;gap:10px;}
    .pv-card>.b{padding:20px;}
    .pv-card>.b.tight{padding:8px 8px;}

    /* columns */
    .pv-cols{display:grid;gap:18px;grid-template-columns:1fr;}
    @media(min-width:900px){.pv-cols.c2{grid-template-columns:1fr 1fr;}
        .pv-cols.c73{grid-template-columns:1.4fr 1fr;}.pv-cols.c3{grid-template-columns:1fr 1fr 1fr;}}

    /* table */
    .pv-tw{overflow-x:auto;}
    .pv-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13.5px;min-width:520px;}
    .pv-table th{text-align:left;color:var(--pv-sub);font-weight:600;font-size:11px;text-transform:uppercase;
        letter-spacing:.04em;padding:11px 14px;border-bottom:1px solid var(--pv-line);white-space:nowrap;}
    .pv-table td{padding:12px 14px;border-bottom:1px solid var(--pv-line2);vertical-align:middle;}
    .pv-table tbody tr:last-child td{border-bottom:0;}
    .pv-table tbody tr:hover td{background:#fafbff;}
    .pv-table tfoot td{font-weight:700;border-top:2px solid var(--pv-line);}
    .pv-r{text-align:right;} .pv-c{text-align:center;}

    /* buttons */
    .pv-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 15px;border-radius:var(--pv-r-sm);
        font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;transition:.15s;
        background:#fff;color:var(--pv-ink);border-color:var(--pv-line);line-height:1;}
    .pv-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(16,24,40,.08);}
    .pv-btn.p{background:var(--pv-brand);color:#fff;border-color:var(--pv-brand);}
    .pv-btn.g{background:var(--pv-green);color:#fff;border-color:var(--pv-green);}
    .pv-btn.d{background:#fff;color:var(--pv-red);border-color:#f6caca;}
    .pv-btn.sm{padding:6px 11px;font-size:12px;}
    .pv-btn[disabled]{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
    .pv-btngrp{display:inline-flex;gap:6px;}

    /* badges */
    .pv-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:650;letter-spacing:.01em;}
    .pv-badge.present,.pv-badge.approved,.pv-badge.admin_approved,.pv-badge.active,.pv-badge.paid{background:#e7f7ef;color:#067a4b;}
    .pv-badge.absent,.pv-badge.rejected{background:#fdeaea;color:#c0322c;}
    .pv-badge.halfday,.pv-badge.pending,.pv-badge.hr_submitted,.pv-badge.onboarding{background:#fef4e6;color:#b26a05;}
    .pv-badge.leave,.pv-badge.wfh{background:#eef0fe;color:#4b45c7;}
    .pv-badge.holiday,.pv-badge.draft,.pv-badge.cancelled{background:#eef1f5;color:#5b6472;}

    /* forms */
    .pv-field{margin-bottom:13px;}
    .pv-label{display:block;font-size:12px;color:var(--pv-sub);font-weight:600;margin-bottom:5px;}
    .pv-input,.pv-select,.pv-textarea{width:100%;padding:9px 12px;border:1px solid var(--pv-line);
        border-radius:var(--pv-r-sm);font-size:13.5px;background:#fff;color:var(--pv-ink);font-family:inherit;transition:.15s;}
    .pv-input:focus,.pv-select:focus,.pv-textarea:focus{outline:none;border-color:var(--pv-brand);box-shadow:0 0 0 3px rgba(79,70,229,.12);}
    .pv-inline{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;}
    .pv-check{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--pv-sub);}

    /* alerts */
    .pv-alert{padding:12px 16px;border-radius:var(--pv-r-sm);margin-bottom:18px;font-size:13.5px;font-weight:500;border:1px solid;}
    .pv-alert.ok{background:#ecfdf5;color:#065f46;border-color:#a7f3d0;}
    .pv-alert.err{background:#fef2f2;color:#991b1b;border-color:#fecaca;}

    /* misc */
    .pv-muted{color:var(--pv-sub);} .pv-mut2{color:var(--pv-mut);font-size:12px;}
    .pv-empty{text-align:center;color:var(--pv-sub);padding:34px 16px;}
    .pv-empty .ic{font-size:30px;margin-bottom:8px;opacity:.55;}
    .pv-grid-actions{display:grid;gap:10px;}
    .pv-linkrow{display:flex;align-items:center;gap:10px;padding:11px 14px;border:1px solid var(--pv-line);
        border-radius:var(--pv-r-sm);color:var(--pv-ink);font-size:13.5px;font-weight:600;transition:.15s;}
    .pv-linkrow:hover{border-color:var(--pv-brand);background:#fafaff;}
    .pv-cal{display:grid;grid-template-columns:repeat(7,1fr);gap:7px;}
    .pv-cal .dow{font-size:11px;font-weight:700;color:var(--pv-mut);text-align:center;text-transform:uppercase;}
    .pv-cal .cell{min-height:74px;border:1px solid var(--pv-line2);border-radius:9px;padding:7px 8px;background:#fff;}
    .pv-cal .cell.mut{background:#fafbfc;}
    .pv-cal .cell .d{font-size:12px;font-weight:700;color:var(--pv-sub);}
</style>
@endonce

@if(session('success'))<div class="pv-alert ok">{{ session('success') }}</div>@endif
@if(session('error'))<div class="pv-alert err">{{ session('error') }}</div>@endif
@if(isset($errors) && $errors->any())<div class="pv-alert err"><ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
