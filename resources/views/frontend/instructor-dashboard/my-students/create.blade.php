@extends('frontend.instructor-dashboard.layouts.master')
@section('dashboard-contents')

<style>
:root {
  --g50:#f0faf4;--g100:#d6f5e3;--g200:#aeeac8;
  --g400:#4fbe80;--g500:#29a65c;--g600:#1f8a4a;
  --n50:#f8f9fa;--n100:#f1f3f5;--n200:#e4e8ec;
  --n400:#9ca3af;--n600:#4b5563;--n800:#1f2937;
  --ff:'DM Sans','Segoe UI',sans-serif;
  --tr:.2s ease; --radius:16px;
}

/* ── Page ── */
.add-student-page { font-family:var(--ff); }

/* ── Top bar ── */
.add-student-topbar {
  display:flex; align-items:center;
  justify-content:space-between; flex-wrap:wrap;
  gap:12px; margin-bottom:28px;
}
.add-student-topbar h4 {
  font-size:22px; font-weight:700; color:var(--n800);
  margin:0; display:flex; align-items:center; gap:10px;
}
.add-student-topbar h4::before {
  content:''; width:4px; height:24px;
  background:linear-gradient(180deg,var(--g500),var(--g400));
  border-radius:4px; display:inline-block;
}
.btn-go-back {
  display:inline-flex; align-items:center; gap:7px;
  background:#fff; color:var(--g600) !important;
  font-size:13.5px; font-weight:500;
  padding:9px 20px; border-radius:100px;
  text-decoration:none;
  border:1.5px solid var(--g200);
  transition:var(--tr);
}
.btn-go-back:hover { background:var(--g50); transform:translateX(-2px); }

/* ── Form card ── */
.add-student-card {
  background:#fff; border-radius:var(--radius);
  border:1.5px solid var(--n100);
  box-shadow:0 2px 16px rgba(0,0,0,.05);
  overflow:hidden;
}

/* ── Card header strip ── */
.add-student-card-header {
  background:linear-gradient(135deg,var(--g500),var(--g400));
  padding:20px 28px; position:relative; overflow:hidden;
  display:flex; align-items:center; gap:14px;
}
.add-student-card-header::before {
  content:''; position:absolute; top:-30px; right:-30px;
  width:120px; height:120px; border-radius:50%;
  background:rgba(255,255,255,.1);
}
.add-student-card-header::after {
  content:''; position:absolute; bottom:-50px; left:30px;
  width:160px; height:160px; border-radius:50%;
  background:rgba(255,255,255,.07);
}
.header-icon {
  width:44px; height:44px; border-radius:12px;
  background:rgba(255,255,255,.2);
  border:1.5px solid rgba(255,255,255,.3);
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-size:18px; flex-shrink:0;
  position:relative; z-index:1;
}
.add-student-card-header h6 {
  color:#fff; font-size:16px; font-weight:600;
  margin:0; position:relative; z-index:1;
}
.add-student-card-header p {
  color:rgba(255,255,255,.75); font-size:12.5px;
  margin:2px 0 0; position:relative; z-index:1;
}

/* ── Form body ── */
.add-student-form-body { padding:28px; }

/* ── Field ── */
.as-field { display:flex; flex-direction:column; gap:6px; margin-bottom:20px; }
.as-field label {
  font-size:13px; font-weight:500; color:var(--n800);
  display:flex; align-items:center; gap:5px;
}
.req-dot {
  width:6px; height:6px; background:var(--g500);
  border-radius:50%; display:inline-block; flex-shrink:0;
}
.as-field input,
.as-field select {
  width:100%; font-family:var(--ff); font-size:14px;
  color:var(--n800); background:var(--n50);
  border:1.5px solid var(--n200); border-radius:10px;
  padding:11px 14px; outline:none; transition:var(--tr);
  appearance:none; -webkit-appearance:none;
}
.as-field input:focus,
.as-field select:focus {
  border-color:var(--g400); background:var(--g50);
  box-shadow:0 0 0 3px rgba(79,190,128,.15);
}
.as-field input::placeholder { color:var(--n400); }

/* select arrow */
.select-wrap { position:relative; }
.select-wrap::after {
  content:''; position:absolute; top:50%; right:14px;
  transform:translateY(-50%);
  border:5px solid transparent; border-top:6px solid var(--n400);
  pointer-events:none;
}

/* ── Password toggle ── */
.pw-wrap { position:relative; }
.pw-wrap input { padding-right:42px; }
.pw-toggle {
  position:absolute; top:50%; right:13px;
  transform:translateY(-50%);
  background:none; border:none; cursor:pointer;
  color:var(--n400); font-size:14px; padding:0;
  transition:var(--tr);
}
.pw-toggle:hover { color:var(--g500); }

/* ── Divider ── */
.as-divider {
  height:1px; background:var(--n100);
  margin:4px 0 24px;
}

/* ── Footer ── */
.add-student-footer {
  display:flex; align-items:center;
  justify-content:flex-end; gap:12px;
  padding:20px 28px;
  border-top:1px solid var(--n100);
}
.btn-cancel-as {
  font-family:var(--ff); font-size:14px; font-weight:500;
  padding:11px 26px; border-radius:100px;
  background:var(--n100); color:var(--n600);
  border:1.5px solid var(--n200);
  text-decoration:none; transition:var(--tr);
  display:inline-flex; align-items:center; gap:7px;
}
.btn-cancel-as:hover { background:var(--n200); color:var(--n800); }
.btn-save-as {
  font-family:var(--ff); font-size:14px; font-weight:500;
  padding:11px 28px; border-radius:100px;
  background:#fff;
  color:#10b981; border:2px solid #10b981; cursor:pointer; 
  transition:var(--tr);
  display:inline-flex; align-items:center; gap:8px;
}
.btn-save-as:hover { transform:translateY(-2px);color:#10b981; }
.btn-save-as:active { transform:translateY(0); }

/* ── Validation errors ── */
.field-error {
  font-size:12px; color:#dc2626;
  display:flex; align-items:center; gap:5px; margin-top:2px;
}
.field-error i { font-size:11px; }
.as-field input.is-invalid,
.as-field select.is-invalid {
  border-color:#f87171;
  background:rgba(239,68,68,.04);
}
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
/* Page-local neutral --n* tokens remapped for dark; the emerald --g* accent
   tokens (header gradient, focus rings, back button) are kept. */
html[data-theme="dark"] {
  --n50:  #17233a;
  --n100: #22304a;
  --n200: #2a3a55;
  --n400: #94a3b8;
  --n600: #94a3b8;
  --n800: #e2e8f0;
}
html[data-theme="dark"] .btn-go-back { background:#1e293b; }
html[data-theme="dark"] .add-student-card { background:#1e293b; }
html[data-theme="dark"] .btn-save-as { background:#1e293b; }
</style>

<div class="dashboard__content-wrap add-student-page">

  <!-- Top bar -->
  <div class="add-student-topbar">
    <h4>{{ __('Add New Student') }}</h4>
    <a href="{{ route('instructor.my-students.index') }}" class="btn-go-back">
      <i class="bi bi-arrow-left"></i> Go Back
    </a>
  </div>

  <!-- Form card -->
  <div class="add-student-card">

    <!-- Card header -->
    <div class="add-student-card-header">
      <div class="header-icon">
        <i class="fas fa-user-plus"></i>
      </div>
      <div>
        <h6>{{ __('Student Information') }}</h6>
        <p>Fill in the details below to register a new student.</p>
      </div>
    </div>

    <!-- Form -->
    <form action="{{ route('instructor.my-students.store') }}" method="POST">
      @csrf

      <div class="add-student-form-body">
        <div class="row g-4">

          <!-- Name -->
          <div class="col-md-6">
            <div class="as-field">
              <label for="name">{{ __('Full Name') }} <span class="req-dot"></span></label>
              <input type="text" id="name" name="name"
                     placeholder="e.g. John Smith"
                     value="{{ old('name') }}"
                     class="{{ $errors->has('name') ? 'is-invalid' : '' }}">
              @error('name')
                <span class="field-error"><i class="fas fa-exclamation-circle"></i>{{ $message }}</span>
              @enderror
            </div>
          </div>

          <!-- Email -->
          <div class="col-md-6">
            <div class="as-field">
              <label for="email">{{ __('Email Address') }} <span class="req-dot"></span></label>
              <input type="email" id="email" name="email"
                     placeholder="student@example.com"
                     value="{{ old('email') }}"
                     class="{{ $errors->has('email') ? 'is-invalid' : '' }}">
              @error('email')
                <span class="field-error"><i class="fas fa-exclamation-circle"></i>{{ $message }}</span>
              @enderror
            </div>
          </div>

          <!-- Password -->
          <div class="col-md-6">
            <div class="as-field">
              <label for="password">{{ __('Password') }} <span class="req-dot"></span></label>
              <div class="pw-wrap">
                <input type="password" id="password" name="password"
                       placeholder="Min. 8 characters"
                       class="{{ $errors->has('password') ? 'is-invalid' : '' }}">
                <button type="button" class="pw-toggle" onclick="
                  var f=document.getElementById('password');
                  f.type=f.type==='password'?'text':'password';
                  this.querySelector('i').classList.toggle('fa-eye');
                  this.querySelector('i').classList.toggle('fa-eye-slash');
                ">
                  <i class="fas fa-eye-slash"></i>
                </button>
              </div>
              @error('password')
                <span class="field-error"><i class="fas fa-exclamation-circle"></i>{{ $message }}</span>
              @enderror
            </div>
          </div>

          <!-- Status -->
          <div class="col-md-6">
            <div class="as-field">
              <label for="status">{{ __('Status') }} <span class="req-dot"></span></label>
              <div class="select-wrap">
                <select id="status" name="status"
                        class="{{ $errors->has('status') ? 'is-invalid' : '' }}">
                  <option @selected(old('status','active')=='active')  value="active">{{ __('Active') }}</option>
                  <option @selected(old('status')=='inactive') value="inactive">{{ __('Inactive') }}</option>
                </select>
              </div>
              @error('status')
                <span class="field-error"><i class="fas fa-exclamation-circle"></i>{{ $message }}</span>
              @enderror
            </div>
          </div>

          {{-- Audit 2026-05-19 phase 4 (post-SRS) — Batch dropdown.
               Optional: a coach can add a student without immediately
               assigning them to a batch. Picking one creates an
               Enrollment row automatically (no Order needed — coach-
               added enrolments carry order_id=NULL by design). --}}
          <div class="col-md-12">
            <div class="as-field">
              <label for="batch_id">
                {{ __('Assign to Batch') }}
                <small class="text-muted" style="font-weight:400; font-size:11px;">
                  ({{ __('optional') }})
                </small>
              </label>
              <div class="select-wrap">
                <select id="batch_id" name="batch_id"
                        class="{{ $errors->has('batch_id') ? 'is-invalid' : '' }}">
                  <option value="">{{ __('— No batch assignment —') }}</option>
                  @foreach (($batches ?? collect()) as $batch)
                    <option value="{{ $batch->id }}"
                            @selected(old('batch_id')==$batch->id)>
                      {{ $batch->course?->title ? $batch->course->title.' · ' : '' }}{{ $batch->title }}
                    </option>
                  @endforeach
                </select>
              </div>
              <small style="font-size:11px; color:var(--n400);">
                {{ __('When set, the student is auto-enrolled in the batch\'s course.') }}
              </small>
              @error('batch_id')
                <span class="field-error"><i class="fas fa-exclamation-circle"></i>{{ $message }}</span>
              @enderror
            </div>
          </div>

        </div>
      </div><!-- /form-body -->

      <!-- Footer -->
      <div class="as-divider"></div>
      <div class="add-student-footer">
        <a href="{{ route('instructor.my-students.index') }}" class="btn-cancel-as">
          <i class="bi bi-x-lg"></i> Cancel
        </a>
        <button type="submit" class="btn-save-as">
          <i class="fas fa-check"></i> {{ __('Save Student') }}
        </button>
      </div>

    </form>
  </div>

</div>
@endsection