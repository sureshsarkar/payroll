@extends('admin.master_layout')
@section('title')
    <title>{{ __('Coupon List') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Coupon List') }}</h1>
            </div>

            <div class="section-body">
                <div class="text-right">
 <a href="javascript:;" data-toggle="modal" data-target="#create_coupon_id" class="btn btn-primary"><i
                        class="fas fa-plus"></i> {{ __('Add New') }}</a>
                </div>

                {{-- 2026-06-02 (CRUD audit) — search by coupon code --}}
                <form method="GET" class="mt-3" role="search">
                    <div class="input-group" style="max-width:360px;">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="{{ __('Search coupon code...') }}" aria-label="{{ __('Search coupons') }}">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                            @if(request('search'))
                                <a href="{{ url()->current() }}" class="btn btn-secondary">{{ __('Clear') }}</a>
                            @endif
                        </div>
                    </div>
                </form>

                <div class="row mt-sm-4">
                    <div class="col-12">
                        <div class="card ">
                            <div class="card-body">
                                <div class="table-responsive table-invoice">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>{{ __('SN') }}</th>
                                                <th>{{ __('Coupon Code') }}</th>
                                                <th>{{ __('Min Price') }}</th>
                                                <th>{{ __('Offer') }}</th>
                                                <th>{{ __('End time') }}</th>
                                                <th>{{ __('Status') }}</th>
                                                <th>{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($coupons as $index => $coupon)
                                                <tr>
                                                    <td>{{ ++$index }}</td>
                                                    <td>{{ $coupon->coupon_code }}</td>
                                                    <td>{{ currency($coupon->min_price) }}</td>
                                                    <td>{{ $coupon->offer_percentage }}%</td>

                                                    <td>{{ date('d M Y', strtotime($coupon->expired_date)) }}</td>

                                                    <td>
                                                        @if ($coupon->status == 'active')
                                                            <span class="badge badge-success">{{ __('Active') }}</span>
                                                        @else
                                                            <span class="badge badge-danger">{{ __('Inactive') }}</span>
                                                        @endif
                                                    </td>

                                                    <td>

                                                        <a href="javascript:;" data-toggle="modal"
                                                            data-target="#edit_coupon_id_{{ $coupon->id }}"
                                                            class="btn btn-primary btn-sm"
                                                            title="{{ __('Edit coupon') }}"
                                                            aria-label="{{ __('Edit coupon') }}: {{ $coupon->coupon_code }}"><i class="fa fa-edit"
                                                                aria-hidden="true"></i></a>

                                                        <a href="javascript:;" data-toggle="modal"
                                                            data-target="#deleteModal" class="btn btn-danger btn-sm"
                                                            onclick="deleteData({{ $coupon->id }})"
                                                            title="{{ __('Delete coupon') }}"
                                                            aria-label="{{ __('Delete coupon') }}: {{ $coupon->coupon_code }}"><i
                                                                class="fa fa-trash" aria-hidden="true"></i></a>

                                                    </td>

                                                </tr>
                                            @empty
                                                <x-empty-table :name="__('Coupon')" route="" create="no"
                                                    :message="__('No data found!')" colspan="7"></x-empty-table>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">{{ $coupons->links() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @foreach ($coupons as $index => $coupon)
        <div class="modal fade" id="edit_coupon_id_{{ $coupon->id }}" tabindex="-1" role="dialog"
            aria-labelledby="modelTitleId" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="container-fluid">
                            <form action="{{ route('admin.coupon.update', $coupon->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="form-group">
                                    <label for="">{{ __('Coupon Code') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="coupon_code" autocomplete="off" class="form-control"
                                        value="{{ $coupon->coupon_code }}">
                                </div>

                                <div class="form-group">
                                    <label for="">{{ __('Minimum purchase price') }} <span data-toggle="tooltip"
                                            data-placement="top" class="fa fa-info-circle text--primary"
                                            title="Price should be USD($)"> <span class="text-danger">*</span></label>
                                    <input type="text" name="min_price" autocomplete="off" class="form-control"
                                        value="{{ $coupon->min_price }}">
                                </div>

                                <div class="form-group">
                                    <label for="">{{ __('Offer') }}(%) <span class="text-danger">*</span></label>
                                    <input type="text" name="offer_percentage" autocomplete="off" class="form-control"
                                        value="{{ $coupon->offer_percentage }}">
                                </div>

                                <div class="form-group">
                                    {{-- 2026-05-29 UI/UX audit P1-1: native HTML5 date input.
                                         Replaced the bootstrap-datepicker JS widget with
                                         type="date" so mobile users get OS-native pickers
                                         and screen readers can reach native semantics.
                                         The `expired_date` column stores YYYY-MM-DD which
                                         is what type=date emits. --}}
                                    <label for="edit_coupon_expired_date_{{ $coupon->id }}">{{ __('End time') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="expired_date" autocomplete="off"
                                        id="edit_coupon_expired_date_{{ $coupon->id }}"
                                        class="form-control" value="{{ \Carbon\Carbon::parse($coupon->expired_date)->format('Y-m-d') }}"
                                        min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                </div>

                                <div class="form-group">
                                    <label>{{ __('Status') }} <span class="text-danger">*</span></label>
                                    <select name="status" id="" class="form-control">
                                        <option {{ $coupon->status == 'active' ? 'selected' : '' }} value="active">
                                            {{ __('Active') }}</option>
                                        <option {{ $coupon->status == 'inactive' ? 'selected' : '' }} value="inactive">
                                            {{ __('Inactive') }}</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>{{ __('Coupon for') }}</label>
                                    <select name="coach_id" class="form-control">
                                        <option value="">{{ __('All coaches (platform-wide)') }}</option>
                                        @foreach($coaches as $c)
                                            <option value="{{ $c->id }}" {{ (int) $coupon->coach_id === (int) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">{{ __('Restrict this coupon to one coach\'s website. Leave as "All coaches" for a platform-wide coupon.') }}</small>
                                </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-warning" data-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach


    <!-- Modal -->
    <div class="modal fade" id="create_coupon_id" tabindex="-1" role="dialog" aria-labelledby="modelTitleId"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid">
                        <form action="{{ route('admin.coupon.store') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">{{ __('Coupon Code') }} <span class="text-danger">*</span></label>
                                <input type="text" name="coupon_code" autocomplete="off" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="">{{ __('Minimum purchase price') }} <span data-toggle="tooltip"
                                        data-placement="top" class="fa fa-info-circle text--primary"
                                        title="Price should be USD($)"> <span class="text-danger">*</span></label>
                                <input type="text" name="min_price" autocomplete="off" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="">{{ __('Offer') }}(%) <span class="text-danger">*</span></label>
                                <input type="text" name="offer_percentage" autocomplete="off" class="form-control">
                            </div>



                            <div class="form-group">
                                {{-- 2026-05-29 UI/UX audit P1-1: native HTML5 date input. --}}
                                <label for="create_coupon_expired_date">{{ __('End time') }} <span class="text-danger">*</span></label>
                                <input type="date" name="expired_date" autocomplete="off"
                                    id="create_coupon_expired_date"
                                    class="form-control" min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                            </div>

                            <div class="form-group">
                                <label>{{ __('Status') }} <span class="text-danger">*</span></label>
                                <select name="status" id="" class="form-control">
                                    <option value="active">{{ __('Active') }}</option>
                                    <option value="inactive">{{ __('Inactive') }}</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>{{ __('Coupon for') }}</label>
                                <select name="coach_id" class="form-control">
                                    <option value="">{{ __('All coaches (platform-wide)') }}</option>
                                    @foreach($coaches as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ __('Restrict this coupon to one coach\'s website. Leave as "All coaches" for a platform-wide coupon.') }}</small>
                            </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" data-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
                </form>
            </div>
        </div>
    </div>


    <x-admin.delete-modal />
    <script>
        "use strict"

        function deleteData(id) {
            $("#deleteForm").attr("action", '{{ url('admin/coupon/') }}' + "/" + id)
        }
    </script>
@endsection
