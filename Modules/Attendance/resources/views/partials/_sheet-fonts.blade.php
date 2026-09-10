{{--
    Attendance-module readability bump. Scoped to the attendance sheets only —
    it raises font sizes on top of the shared `payroll::partials.ui` (.pv)
    stylesheet without touching that shared file. Include this right after the
    `payroll::partials.ui` include so source order wins over equal-specificity
    rules; `!important` is used only where `ui.blade.php` sets the same property
    at the same specificity.
--}}
<style>
    .pv{font-size:14.5px;}
    .pv .pv-head .s{font-size:14px;}
    .pv .pv-card>.h{font-size:16px;}

    .pv .pv-table{font-size:15.5px !important;}
    .pv .pv-table th{font-size:12.5px !important;}
    .pv .pv-table td{padding:13px 14px;}

    .pv .pv-label{font-size:12.5px !important;}
    .pv .pv-input,
    .pv .pv-select{font-size:14.5px;}
    .pv .pv-mut2{font-size:12px !important;}
    .pv .pv-badge{font-size:12.5px !important;}
    .pv .pv-btn{font-size:13.5px;}
    .pv .pv-btn.sm{font-size:12.5px;}

    /* HR monthly workspace (team-sheet.blade.php) */
    .att-workspace .att-person .name{font-size:14.5px;}
    .att-workspace .att-person .meta{font-size:12px;}
    .att-workspace .att-summary .num{font-size:24px;}
    .att-workspace .att-summary .label{font-size:12.5px;}
    .att-workspace .att-calendar .dow{font-size:12.5px;}
    .att-workspace .att-date{font-size:13.5px;}
    .att-workspace .att-time{font-size:11px;}

    /* Employee calendar (my.blade.php) */
    .pv .pv-cal .dow{font-size:12.5px;}
    .pv .pv-cal .cell .d{font-size:14px;}
</style>
