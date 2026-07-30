@extends('admin.master_layout')
@section('title')
<title>{{ __('Message Details') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Message Details') }}</h1>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-striped">
                                    <tr>
                                        <td>{{ __('Name') }}</td>
                                        <td>{{ html_decode($message->first_name.' '.$message->last_name) }}</td>
                                    </tr>

                                    <tr>
                                        <td>{{ __('Email') }}</td>
                                        <td><a href="mailto:{{ html_decode($message->email) }}">{{ html_decode($message->email) }}</a></td>
                                    </tr>

                                    <tr>
                                        <td>{{ __('Service') }}</td>
                                        <td>{{ html_decode($message->service) }}</td>
                                    </tr>

                                    <tr>
                                        <td>{{ __('Message') }}</td>
                                        <td>{!! clean($message->message) !!}</td>
                                    </tr>

                                    <tr>
                                        <td>{{ __('Created at') }}</td>
                                        <td>{{ $message->created_at->format('h:iA, d M Y') }}</td>
                                    </tr>

                                    <tr>
                                        <td>{{ __('Action') }}</td>
                                        <td>
                                            <a onclick="deleteData({{ $message->id }})" href="javascript:;" data-toggle="modal" data-target="#deleteModal" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> {{ __('Delete') }}</a>
                                        </td>
                                    </tr>

                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <x-admin.delete-modal />
    @push('js')
  <script>
    function deleteData(id) {

        let url = "{{ route('admin.landing-page-message.delete', ':id') }}";
        url = url.replace(':id', id);

        $("#deleteForm").attr("action", url);
    }
</script>
    @endpush
@endsection
