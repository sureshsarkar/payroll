@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap">
    <div class="dashboard__content-title d-flex flex-wrap justify-content-between mb-3">
        <h4 class="title">{{ __('Import preview') }}</h4>
        <a href="{{ route('instructor.landing-page-enquiry.import') }}" class="btn">
            ← {{ __('Back') }}
        </a>
    </div>

    <div class="alert alert-info">
        {{ __('Showing first :n of :t parsed rows.', ['n' => count($preview), 't' => $total]) }}
        {{ __('Rows missing a name AND (email OR phone) will be skipped on commit.') }}
    </div>

    @if (count($preview) > 0)
        <div class="card mb-3" style="border:1px solid #e5e7eb;border-radius:12px;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                @foreach (array_keys($preview[0] ?? []) as $col)
                                    <th>{{ ucfirst(str_replace('_', ' ', $col)) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    @foreach ($row as $val)
                                        <td>{{ \Illuminate\Support\Str::limit((string) $val, 60) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('instructor.landing-page-enquiry.import.commit') }}">
            @csrf
            <input type="hidden" name="stash_path" value="{{ $stashPath }}">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"
                        onclick="return confirm('{{ __('Import all :t rows now?', ['t' => $total]) }}')">
                    {{ __('Import :t rows', ['t' => $total]) }}
                </button>
                <a href="{{ route('instructor.landing-page-enquiry.import') }}" class="btn btn-outline-secondary">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    @else
        <div class="alert alert-warning">
            {{ __('No rows could be parsed from this CSV. Check that the file has a header row with recognized column names.') }}
        </div>
    @endif
</div>
@endsection
