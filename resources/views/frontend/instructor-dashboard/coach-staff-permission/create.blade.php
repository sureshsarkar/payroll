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
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title d-flex flex-wrap justify-content-between">
            <h4 class="title">{{ __('Add Coach Staff Permission') }}</h4>
            <a href="{{ route('instructor.coach-staff-permission.index') }}" class="btn btn-primary btn-hight-basic">Go
                Back</a>
        </div>

        <div class="section-body">
            <div class="invoice">
                <div class="invoice-print">
                    <div class="row ">
                        <div class="col-md-12">

                            <form action="{{ route('instructor.coach-staff-permission.store') }}" method="POST">
                                @csrf
                                <div class="row">

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Permission Name</label>
                                            <input type="text" name="name" class="form-control permission_name"
                                                placeholder="Permission Name" required>
                                            <span class="text-danger">{{ $errors->first('name') }}</span>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Permission Slug</label>
                                            <input type="text" name="slug" class="form-control permission_slug"
                                                placeholder="Permission Slug" required>
                                            <span class="text-danger">{{ $errors->first('slug') }}</span>
                                        </div>
                                    </div>

                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <x-admin.save-button :text="__('Save')" />
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


    <script>
        function createSlug(title) {
            return title
                .toLowerCase() // Convert to lowercase
                .trim() // Remove leading and trailing whitespace
                .replace(/[^a-z0-9\s-]/g, '') // Remove non-alphanumeric characters except spaces and hyphens
                .replace(/\s+/g, '-') // Replace spaces (and multiple spaces) with a single hyphen
                .replace(/--+/g, '-') // Replace multiple hyphens with a single hyphen
                .replace(/^-+|-+$/g, ''); // Remove leading and trailing hyphens
        }

        let permissionName = document.getElementsByClassName('permission_name');
        let permissionSlug = document.getElementsByClassName('permission_slug');
        permissionName[0].addEventListener('keyup', function() {
            let textValue = this.value;
            let slug = createSlug(textValue);
            permissionSlug[0].value = slug;
        });
    </script>
@endsection
