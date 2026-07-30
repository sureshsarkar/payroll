@extends('admin.master_layout')
@section('title')<title>{{ __('Edit Announcement') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>{{ __('Edit Announcement') }}</h1>
            <div class="section-header-breadcrumb">
                <a class="btn btn-light" href="{{ route('admin.announcements.show', $announcement->id) }}">
                    <i class="fas fa-eye"></i> {{ __('View') }}
                </a>
                <a class="btn btn-light" href="{{ route('admin.announcements.index') }}">
                    <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                </a>
            </div>
        </div>

        <div class="section-body">
            <div class="card">
                <form action="{{ route('admin.announcements.update', $announcement->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">

                        <div class="form-group">
                            <label style="font-weight:600; font-size:13px;">{{ __('Audience') }} <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 flex-wrap" style="margin-top:6px;">
                                <label class="audience-pill">
                                    <input type="radio" name="audience_type" value="all_students"
                                           {{ old('audience_type', $announcement->audience_type) === 'all_students' ? 'checked' : '' }}>
                                    <span>🌐 {{ __('All Students') }}</span>
                                </label>
                                <label class="audience-pill">
                                    <input type="radio" name="audience_type" value="batch_specific"
                                           {{ old('audience_type', $announcement->audience_type) === 'batch_specific' ? 'checked' : '' }}>
                                    <span>📚 {{ __('Batch-wise Students') }}</span>
                                </label>
                            </div>
                        </div>

                        <div id="batch-scope-block" class="row" style="display:none;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight:600; font-size:13px;">{{ __('Course') }} <span class="text-danger">*</span></label>
                                    <select name="course_id" id="admin-ann-course" class="form-control"
                                            data-load-url="{{ route('admin.announcements.batches-for-course', ['course' => 0]) }}">
                                        <option value="">{{ __('— Select a course —') }}</option>
                                        @foreach ($courses as $c)
                                            <option value="{{ $c->id }}" @selected(old('course_id', $announcement->course_id) == $c->id)>{{ $c->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight:600; font-size:13px;">{{ __('Batches') }} <span class="text-danger">*</span></label>
                                    @php $selectedBatchIds = $announcement->batches->pluck('id')->toArray(); @endphp
                                    <select name="batches[]" id="admin-ann-batches" class="form-control" multiple size="4">
                                        @foreach ($batches as $b)
                                            <option value="{{ $b->id }}" @selected(in_array($b->id, $selectedBatchIds))>{{ $b->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-weight:600; font-size:13px;">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required maxlength="255"
                                   value="{{ old('title', $announcement->title) }}">
                        </div>

                        <div class="form-group">
                            <label style="font-weight:600; font-size:13px;">{{ __('Message') }} <span class="text-danger">*</span></label>
                            <textarea name="announcement" rows="6" class="form-control" required>{{ old('announcement', $announcement->announcement) }}</textarea>
                        </div>

                        <div class="form-group">
                            <label style="font-weight:600; font-size:13px;">{{ __('Status') }} <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="active" @selected(old('status', $announcement->status)=='active')>{{ __('Published') }}</option>
                                <option value="inactive" @selected(old('status', $announcement->status)=='inactive')>{{ __('Draft') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <a href="{{ route('admin.announcements.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save changes') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<style>
.audience-pill { display:flex; flex-direction:column; align-items:flex-start;
    border:2px solid #e5e7eb; border-radius:10px; padding:10px 14px;
    cursor:pointer; min-width:240px; transition:all .15s; background:#fff; }
.audience-pill input { margin-right:6px; }
.audience-pill:has(input:checked) { border-color:#5751e1; background:#f3f2ff; box-shadow:0 0 0 3px rgba(87,81,225,0.08); }
.audience-pill > span { font-weight:600; font-size:13px; color:#1c1a4a; }
</style>

<script>
(function () {
    function refresh() {
        var picked = document.querySelector('input[name="audience_type"]:checked');
        var block = document.getElementById('batch-scope-block');
        if (!picked || !block) return;
        block.style.display = picked.value === 'batch_specific' ? 'flex' : 'none';
    }
    document.querySelectorAll('input[name="audience_type"]').forEach(function (el) {
        el.addEventListener('change', refresh);
    });
    refresh();

    var courseEl = document.getElementById('admin-ann-course');
    var batchEl  = document.getElementById('admin-ann-batches');
    courseEl && courseEl.addEventListener('change', function () {
        var courseId = this.value;
        batchEl.innerHTML = '<option value="">{{ __('Loading…') }}</option>';
        if (!courseId) { batchEl.innerHTML = '<option value="">{{ __('— Select a course first —') }}</option>'; return; }
        fetch(this.dataset.loadUrl.replace('/0','/'+courseId), { headers:{'Accept':'application/json'}, credentials:'same-origin' })
            .then(r => r.json()).then(data => {
                var opts = (data.batches||[]).map(b => '<option value="'+b.id+'">'+b.title+'</option>').join('');
                batchEl.innerHTML = opts || '<option value="">{{ __('No batches') }}</option>';
            });
    });
})();
</script>
@endsection
