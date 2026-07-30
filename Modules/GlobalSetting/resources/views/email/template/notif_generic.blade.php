@extends('admin.master_layout')
@section('title')
    <title>{{ __('Email Template') }}</title>
@endsection
@section('admin-content')
    @php
        $registry = \App\Notifications\NotificationEmailTemplates::all();
        $entry = $registry[$template->name] ?? null;
        $placeholders = $entry['placeholders'] ?? [];
        $label = $entry['label'] ?? $template->name;
    @endphp
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Email Template') }} — {{ $label }}</h1>
            </div>

            <div class="section-body">
                <a href="{{ route('admin.email-configuration') }}" class="btn btn-primary">
                    <i class="fas fa-list"></i> {{ __('Email Templates') }}
                </a>

                <div class="row mt-4">
                    <div class="col">
                        <div class="card">
                            <div class="card-header">
                                <h4 style="margin:0;">{{ __('Available variables') }}</h4>
                                <p class="text-muted" style="margin:4px 0 0; font-size:13px;">
                                    {{ __('Wrap each variable in double curly braces, e.g.') }}
                                    <code>&#123;&#123;user_name&#125;&#125;</code>.
                                </p>
                            </div>
                            <div class="card-body">
                                @if (count($placeholders) > 0)
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th style="width:30%;">{{ __('Variable') }}</th>
                                                <th>{{ __('Meaning') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($placeholders as $key => $desc)
                                                <tr>
                                                    <td><code>&#123;&#123;{{ $key }}&#125;&#125;</code></td>
                                                    <td>{{ __($desc) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <p class="text-muted" style="margin:0;">
                                        {{ __('This template has no variables — text will be sent as-is.') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="{{ route('admin.update-email-template', $template->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-group">
                                        <label for="">{{ __('Subject') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" value="{{ $template->subject }}" name="subject">
                                    </div>
                                    <div class="form-group">
                                        <label for="">{{ __('Message') }} <span class="text-danger">*</span></label>
                                        <textarea name="message" cols="30" rows="10" class="form-control summernote">{{ $template->message }}</textarea>
                                    </div>
                                    <button class="btn btn-success" type="submit">{{ __('Update') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
