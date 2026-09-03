{{-- Test stub for hremployee::employees — bypasses the instructor-dashboard
     master layout (settings/brand cache) so EmployeeListTest can assert on the
     controller's real search / sort / pagination output. --}}
<?php
echo json_encode([
    'total'       => $employees->total(),
    'perPage'     => $employees->perPage(),
    'currentPage' => $employees->currentPage(),
    'lastPage'    => $employees->lastPage(),
    'hasPages'    => $employees->hasPages(),
    'ids'         => $employees->pluck('id')->values()->all(),
    'names'       => $employees->pluck('name')->values()->all(),
    'sort'        => $sort,
    'dir'         => $dir,
    'search'      => $search,
    'nextPageUrl' => $employees->nextPageUrl(),
]);
