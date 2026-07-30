@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-newspaper"></i> {{ __('Blog') }}</h4>
            <p>{{ __('Write and manage blog posts for your website. Published posts appear in your site\'s Blog section.') }}</p>
        </div>
        <div class="corp-header__actions">
            @if (checkPermissionView('blogs-create'))
                <a href="{{ route('instructor.blogs.create') }}" class="btn-corp-primary"><i class="fas fa-plus"></i> {{ __('New post') }}</a>
            @endif
        </div>
    </div>

    @if(session('messege'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13.5px;">
            {{ session('messege') }}
        </div>
    @endif

    <form method="GET" style="margin-bottom:14px;display:flex;gap:8px;max-width:360px;">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('Search by title…') }}"
               style="flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:14px;">
        <button class="btn-corp-light"><i class="fas fa-search"></i></button>
    </form>

    @if ($blogs->count())
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Post') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Status') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Publish date') }}</th>
                            <th style="padding:12px 14px;font-weight:600;">{{ __('Views') }}</th>
                            <th style="padding:12px 14px;font-weight:600;text-align:right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blogs as $blog)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px 14px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <img src="{{ $blog->imageUrl() }}" alt="" style="width:46px;height:34px;object-fit:cover;border-radius:6px;border:1px solid #eef0f5;">
                                        <span style="font-weight:600;color:#1e293b;">{{ \Illuminate\Support\Str::limit($blog->title, 60) }}</span>
                                    </div>
                                </td>
                                <td style="padding:12px 14px;">
                                    @if($blog->status === 'published')
                                        <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;">{{ __('Published') }}</span>
                                    @else
                                        <span style="background:#f1f5f9;color:#64748b;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;">{{ __('Draft') }}</span>
                                    @endif
                                </td>
                                <td style="padding:12px 14px;color:#64748b;">
                                    {{ ($blog->published_at ?? $blog->created_at)?->format('d M Y') }}
                                </td>
                                <td style="padding:12px 14px;color:#64748b;">{{ $blog->views }}</td>
                                <td style="padding:12px 14px;text-align:right;white-space:nowrap;">
                                    <form action="{{ route('instructor.blogs.status', $blog->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('PUT')
                                        <button class="btn-corp-light" title="{{ __('Toggle draft/published') }}" style="padding:6px 10px;">
                                            <i class="fas fa-{{ $blog->status === 'published' ? 'eye-slash' : 'eye' }}"></i>
                                        </button>
                                    </form>
                                    {{-- 2026-07-06 (Role Permission Test doc) — edit/delete gated by granular permission. --}}
                                    @if (checkPermissionView('blogs-edit'))
                                        <a href="{{ route('instructor.blogs.edit', $blog->id) }}" class="btn-corp-light" style="padding:6px 10px;"><i class="fas fa-pen"></i></a>
                                    @endif
                                    @if (checkPermissionView('blogs-delete'))
                                        <form action="{{ route('instructor.blogs.destroy', $blog->id) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('{{ __('Delete this post? This cannot be undone.') }}');">
                                            @csrf @method('DELETE')
                                            <button class="btn-corp-light" style="padding:6px 10px;color:#dc2626;"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top:16px;">{{ $blogs->links() }}</div>
    @else
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="text-align:center;padding:40px 20px;color:#94a3b8;">
                <i class="fas fa-newspaper" style="font-size:32px;margin-bottom:10px;"></i>
                <p style="margin:0;">{{ __('No blog posts yet. Click “New post” to write your first one.') }}</p>
            </div>
        </div>
    @endif
</div>
@endsection
