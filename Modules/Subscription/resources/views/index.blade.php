@extends('admin.master_layout')
@section('title')
    <title>{{ $title }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ $title }}</h1>
            </div>

            <div class="section-body">
                <div class="row">
                    {{-- Search filter --}}
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="{{ route('admin.subscriptions') }}" method="GET" onchange="$(this).trigger('submit')"
                                    class="form_padding">
                                    <div class="row">
                                        <div class="col-md-4 form-group">
                                            <input type="text" name="keyword" value="{{ request()->get('keyword') }}"
                                                class="form-control" placeholder="{{ __('Search') }}">
                                        </div>

                                        <div class="col-md-2 form-group">
                                            <select name="subscription_status" class="form-control">
                                                <option value="">{{ __('Subscription Status') }}</option>
                                                <option value="active"
                                                    {{ request('subscription_status') == 'active' ? 'selected' : '' }}>
                                                    {{ __('Active') }}
                                                </option>
                                                <option value="inactive"
                                                    {{ request('subscription_status') == 'inactive' ? 'selected' : '' }}>
                                                    {{ __('Inactive') }}
                                                </option> 
                                            </select>
                                        </div> 
                                         
                                        <div class="col-md-2 form-group">
                                            <select name="order_by" id="order_by" class="form-control">
                                                <option value="">{{ __('Order By') }}</option>
                                                <option value="1" {{ request('order_by') == '1' ? 'selected' : '' }}>
                                                    {{ __('ASC') }}
                                                </option>
                                                <option value="0" {{ request('order_by') == '0' ? 'selected' : '' }}>
                                                    {{ __('DESC') }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <select name="par-page" id="par-page" class="form-control">
                                                <option value="">{{ __('Per Page') }}</option>
                                                <option value="10" {{ '10' == request('par-page') ? 'selected' : '' }}>
                                                    {{ __('10') }}
                                                </option>
                                                <option value="50" {{ '50' == request('par-page') ? 'selected' : '' }}>
                                                    {{ __('50') }}
                                                </option>
                                                <option value="100"
                                                    {{ '100' == request('par-page') ? 'selected' : '' }}>
                                                    {{ __('100') }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                  
                    <div class="col-12 col-md-12 col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h4>Subscription List</h4>
                                <div>
                                    <a href="{{ route('admin.subscriptions.create')}}" class="btn btn-primary"> <i class="fa fa-plus"></i>Add New</a>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <tr>
                                            <th>{{ __('SN') }}</th>
                                            <th>{{ __('Name') }}</th>
                                            <th>{{ __('Price') }}</th>
                                            <th>{{ __('Duration Days') }}</th> 
                                            <th>{{ __('Status') }}</th> 
                                            <th>{{ __('Action') }}</th>
                                        </tr>

                                        @forelse ($subscriptions as $index => $subscription)
                                            <tr>
                                                <td>{{ ++$index }}</td> 
                                                <td>{{ $subscription->name }}</td>
                                                <td>{{ $subscription->price }}</td>
                                                <td>  {{ $subscription->duration_days }}</td>
                                                <td>
                                                    @if ($subscription->status == 'active')
                                                        <div class="badge badge-success">{{ __('Active') }}</div>
                                                    @elseif($subscription->status == 'inactive')
                                                        <div class="badge badge-danger">{{ __('Inactive') }}</div> 
                                                    @endif
                                                </td>
 

                                                <td>
                                                    <a href="{{ route('admin.subscriptions.edit', $subscription->id) }}"
                                                        class="btn btn-success btn-sm"><i class="fa fa-edit"></i></a> 
                                                    <a href="{{ route('admin.subscriptions.show', $subscription->id) }}"
                                                        class="btn btn-primary btn-sm"><i class="fa fa-eye"></i></a> 
                                                    <a href="javascript:;" data-toggle="modal" data-target="#deleteModal"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="deleteData({{ $subscription->id }})"><i class="fa fa-trash"
                                                            aria-hidden="true"></i></a>
                                                </td>
                                            </tr>
                                        @empty
                                            <x-empty-table :name="__('Subscriptions')" route="" create="no" :message="__('No data found!')"
                                                colspan="9"></x-empty-table>
                                        @endforelse
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end">
                                    {{ $subscriptions->links() }}
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>


    <x-admin.delete-modal />

    <script>
        'use strict'

        function deleteData(id) {
            $("#deleteForm").attr("action", "{{ url('admin/order-delete/') }}" + "/" + id)
        }
    </script>
@endsection
