@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <style>
        .statusbtn {
            vertical-align: middle;
            padding: 7px 12px;
            font-weight: 600;
            letter-spacing: .3px;
            border-radius: 30px;
            font-size: 12px;
            display: inline-block;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            color: #fff;
        }

        .statusbtn-success {
            background-color: #47c363;
        }

        .statusbtn-danger {
            background-color: #e23026;
        }
    </style>
    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components.
           The only bespoke element here is the semantic status badge (.statusbtn): white
           text on green/red semantic backgrounds. Per the color map, semantic colors are
           kept as-is, and there are no light surfaces / borders / neutral text to override. */
    </style>
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title d-flex flex-wrap justify-content-between">
            <h4 class="title">{{ __('Edit Student') }}</h4>
            <a href="{{ route('instructor.coach-staff.index') }}" class="btn btn-primary btn-hight-basic">Go Back</a>
        </div>

        <div class="section-body">
            <div class="invoice">
                <div class="invoice-print">
                    <div class="row ">
                        <div class="col-md-12">
                            
                            <form action="{{ route('instructor.coach-staff.update',$user->id) }}" method="POST">
                                @csrf
                                 @method('PUT')
                                <div class="row">
                                    <div class="form-group col-6">
                                        <label for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                                        <input type="text" id="name" class="form-control" name="name"
                                            value="{{$user->name }}">
                                    </div>
                                    <div class="form-group col-6">
                                        <label for="email">{{ __('Email') }} <span class="text-danger">*</span></label>
                                        <input type="email" id="slug" class="form-control" name="email"
                                            value="{{$user->email }}">
                                    </div>
                                    <div class="form-group col-6">
                                        <label for="password">{{ __('Password') }} <span
                                                class="text-danger">*</span></label>
                                        <input type="password" id="password" class="form-control" name="password">
                                    </div>

                                      <div class="form-group col-6">
                                        <label for="role">{{ __('Role') }} <span class="text-danger">*</span></label>
                                        <select id="role" name="role_id" class="form-control">
                                            <option value="">{{ __('Choose Role') }}</option>  
                                            @foreach ($role as $r)
                                            <option @selected($user->role_id  == $r->id) value="{{$r->id}}">{{ __($r->role_name) }}</option>  
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group col-6">
                                        <label for="status">{{ __('Status') }} <span class="text-danger">*</span></label>
                                        <select id="status" name="status" class="form-control">
                                            <option @selected($user->status  == 'active') value="active">{{ __('Active') }}</option>
                                            <option @selected($user->status  == 'inactive') value="inactive">{{ __('Inactive') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <x-admin.save-button :text="__('Save')"></x-admin.save-button>
                                    </div>
                                </div>
                            </form>


                        </div>
                    </div>
                </div>
                <hr>
                <div class="text-md-right">
                </div>
            </div>
        </div>
    </div>
@endsection
