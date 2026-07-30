@extends('admin.master_layout')
@section('title')
    <title>{{ __('Manage Admin') }}</title>
@endsection
@section('admin-content')
    <!-- Main Content -->
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('admin.settings') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ __('Manage Admin') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item active"><a href="{{ route('admin.settings') }}">{{ __('Settings') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Manage Admin') }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="mt-4 row">
                    <div class="col">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h4>{{ __('Manage Admin') }}</h4>
                                <div>
                                    @adminCan('admin.create')
                                        <a href="{{ route('admin.admin.create') }}" class="btn btn-primary"><i
                                                class="fa fa-plus"></i> {{ __('Add New') }}</a>
                                    @endadminCan
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    {{-- M11 fix (2026-05-12) — added `table-hover` alongside the
                                         existing `table-striped`. Pre-fix, some admin tables had
                                         only one or the other. Both classes coexist cleanly;
                                         standardizing on both gives consistent feedback. --}}
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>{{ __('SN') }}</th>
                                                {{-- M6 (2026-05-12) — sortable headers via the
                                                     shared partial. SN, Roles, Action stay static
                                                     because they're either a row counter or not
                                                     a meaningful sort axis. --}}
                                                @include('admin.partials.sort-header', ['key' => 'name',   'label' => __('Name'),   'sort' => $sort, 'dir' => $dir, 'route' => 'admin.admin.index'])
                                                @include('admin.partials.sort-header', ['key' => 'email',  'label' => __('Email'),  'sort' => $sort, 'dir' => $dir, 'route' => 'admin.admin.index'])
                                                <th>{{ __('Roles') }}</th>
                                                @include('admin.partials.sort-header', ['key' => 'status', 'label' => __('Status'), 'sort' => $sort, 'dir' => $dir, 'route' => 'admin.admin.index'])
                                                @adminCan('admin.delete')
                                                    <th>{{ __('Action') }}</th>
                                                @endadminCan
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- M4 fix — was a bare @foreach with no empty branch;
                                                 a filtered list with zero matches rendered as an
                                                 empty <tbody> that looked like the table was broken. --}}
                                            @forelse ($admins as $index => $admin)
                                                <tr>
                                                    <td>{{ ++$index }}</td>
                                                    <td>{{ $admin->name }}</td>
                                                    <td>{{ $admin->email }}</td>
                                                    <td>
                                                        @foreach ($admin->getRoleNames() as $role)
                                                        <span class="badge badge-primary">{{ $role }}</span>
                                                        @endforeach
                                                    </td>
                                                    <td>
                                                        @if($admin->id != 1)
                                                        @if ($admin->status == 'active')
                                                            <a href="javascript:;"
                                                                onclick="changeAdminStatus({{ $admin->id }})">
                                                                <input id="status_toggle" type="checkbox" checked
                                                                    data-toggle="toggle" data-on="{{ __('Active') }}"
                                                                    data-off="{{ __('Inactive') }}" data-onstyle="success"
                                                                    data-offstyle="danger">
                                                            </a>
                                                        @else
                                                            <a href="javascript:;"
                                                                onclick="changeAdminStatus({{ $admin->id }})">
                                                                <input id="status_toggle" type="checkbox"
                                                                    data-toggle="toggle" data-on="{{ __('Active') }}"
                                                                    data-off="{{ __('Inactive') }}" data-onstyle="success"
                                                                    data-offstyle="danger">
                                                            </a>
                                                        @endif
                                                        @else
                                                            <span class="text-muted">{{ __('( default admin )') }}</span>
                                                        @endif
                                                    </td>
                                                    @adminCan('admin.delete')
                                                        <td>
                                                            @if($admin->id != 1)
                                                            @adminCan('admin.edit')
                                                                <a href="{{ route('admin.admin.edit', $admin->id) }}"
                                                                    class="btn btn-warning btn-sm"
                                                                    title="{{ __('Edit admin') }}"
                                                                    aria-label="{{ __('Edit') }} {{ $admin->name }}"><i class="fa fa-edit"
                                                                        aria-hidden="true"></i></a>
                                                            @endadminCan
                                                            @adminCan('admin.delete')
                                                                <a href="javascript:;" data-toggle="modal"
                                                                    data-target="#deleteModal" class="btn btn-danger btn-sm"
                                                                    title="{{ __('Delete admin') }}"
                                                                    aria-label="{{ __('Delete') }} {{ $admin->name }}"
                                                                    onclick="deleteData({{ $admin->id }})"><i
                                                                        class="fa fa-trash" aria-hidden="true"></i></a>
                                                            @endadminCan
                                                            @else
                                                                <span class="text-muted">{{ __('( default admin )') }}</span>
                                                            @endif
                                                        </td>
                                                    @endadminCan
                                                </tr>
                                            @empty
                                                @include('admin.partials.empty-state', [
                                                    'colspan'  => 6,
                                                    'icon'     => 'fa-user-cog',
                                                    'title'    => __('No admins yet'),
                                                    'subtitle' => __('Add a new admin to get started.'),
                                                ])
                                            @endforelse
                                        </tbody>
                                    </table>
                                    <div class="float-right">
                                        {{ $admins->links() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </section>
    </div>
    <x-admin.delete-modal />
@endsection

@push('js')
    <script>
        function deleteData(id) {
            $("#deleteForm").attr("action", "{{ url('admin/admin/') }}" + "/" + id)
        }

        function changeAdminStatus(id) {
            var isDemo = "{{ env('PROJECT_MODE') ?? 1 }}"
            if (isDemo == 0) {
                toastr.error('This Is Demo Version. You Can Not Change Anything');
                return;
            }
            $.ajax({
                type: "put",
                data: {
                    _token: '{{ csrf_token() }}'
                },
                url: "{{ url('/admin/admin-status/') }}" + "/" + id,
                success: function(response) {
                    toastr.success(response.message)
                },
                error: function(err) {
                    console.log(err);
                }
            })
        }
    </script>
@endpush
