@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <style>
        :root {
            --g50: #f0faf4;
            --g100: #d6f5e3;
            --g200: #aeeac8;
            --g400: #4fbe80;
            --g500: #29a65c;
            --g600: #1f8a4a;
            --n50: #f8f9fa;
            --n100: #f1f3f5;
            --n200: #e4e8ec;
            --n400: #9ca3af;
            --n600: #4b5563;
            --n800: #1f2937;
            --ff: 'DM Sans', 'Segoe UI', sans-serif;
            --tr: .2s ease;
            --radius: 16px;
        }

        /* ── Page ── */
        .sell-create-page {
            font-family: var(--ff);
        }

        /* ── Top bar ── */
        .sell-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }

        .sell-topbar h4 {
            font-size: 22px;
            font-weight: 700;
            color: var(--n800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sell-topbar h4::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, var(--g500), var(--g400));
            border-radius: 4px;
            display: inline-block;
        }

        .btn-go-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            color: var(--g600) !important;
            font-size: 13.5px;
            font-weight: 500;
            padding: 9px 20px;
            border-radius: 100px;
            text-decoration: none;
            border: 1.5px solid var(--g200);
            transition: var(--tr);
        }

        .btn-go-back:hover {
            background: var(--g50);
            transform: translateX(-2px);
        }

        /* ── Form card ── */
        .sell-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1.5px solid var(--n100);
            box-shadow: 0 2px 16px rgba(0, 0, 0, .05);
            overflow: hidden;
        }

        /* ── Card header ── */
        .sell-card-header {
            background: linear-gradient(135deg, var(--g500), var(--g400));
            padding: 20px 28px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .sell-card-header::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .1);
        }

        .sell-card-header::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 30px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
        }

        .sell-header-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .2);
            border: 1.5px solid rgba(255, 255, 255, .3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .sell-card-header div {
            position: relative;
            z-index: 1;
        }

        .sell-card-header h6 {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .sell-card-header p {
            color: rgba(255, 255, 255, .75);
            font-size: 12.5px;
            margin: 3px 0 0;
        }

        /* ── Steps indicator ── */
        .sell-steps {
            display: flex;
            align-items: center;
            gap: 0;
            padding: 20px 28px;
            border-bottom: 1.5px solid var(--n100);
            background: var(--n50);
        }

        .sell-step {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .sell-step:last-child {
            flex: none;
        }

        .step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            transition: var(--tr);
            border: 2px solid var(--n200);
            background: #fff;
            color: var(--n400);
        }

        .step-num.active {
            background: var(--g500);
            border-color: var(--g500);
            color: #fff;
            box-shadow: 0 4px 12px rgba(41, 166, 92, .3);
        }

        .step-num.done {
            background: var(--g100);
            border-color: var(--g400);
            color: var(--g600);
        }

        .step-label {
            font-size: 12.5px;
            font-weight: 500;
            color: var(--n400);
        }

        .step-label.active {
            color: var(--g600);
        }

        .step-connector {
            flex: 1;
            height: 2px;
            background: var(--n200);
            margin: 0 12px;
            border-radius: 2px;
        }

        .step-connector.done {
            background: var(--g300);
        }

        /* ── Form body ── */
        .sell-form-body {
            padding: 28px;
        }

        /* ── Section label ── */
        .sell-section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .8px;
            text-transform: uppercase;
            color: var(--g500);
            margin: 0 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sell-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--g100);
        }

        /* ── Field ── */
        .sf-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sf-field label {
            font-size: 13px;
            font-weight: 500;
            color: var(--n800);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .req-dot {
            width: 6px;
            height: 6px;
            background: var(--g500);
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .sf-field select {
            width: 100%;
            font-family: var(--ff);
            font-size: 14px;
            color: var(--n800);
            background: var(--n50);
            border: 1.5px solid var(--n200);
            border-radius: 10px;
            padding: 11px 38px 11px 14px;
            outline: none;
            transition: var(--tr);
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }

        .sf-field select:focus {
            border-color: var(--g400);
            background: var(--g50);
            box-shadow: 0 0 0 3px rgba(79, 190, 128, .15);
        }

        .sf-field select:disabled {
            opacity: .55;
            cursor: not-allowed;
            background: var(--n100);
        }

        .select-wrap {
            position: relative;
        }

        .select-wrap::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-top: 6px solid var(--n400);
            pointer-events: none;
            transition: var(--tr);
        }

        /* ── Batch loading indicator ── */
        .batch-loading {
            display: none;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--n400);
            margin-top: 6px;
        }

        .batch-loading.show {
            display: flex;
        }

        .spin {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid var(--g200);
            border-top-color: var(--g500);
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Info card for batch hint ── */
        .batch-hint {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--g50);
            border: 1.5px solid var(--g100);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13px;
            color: var(--g600);
            margin-top: 6px;
        }

        .batch-hint i {
            color: var(--g400);
            flex-shrink: 0;
        }

        /* ── Footer ── */
        .sell-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 28px;
            border-top: 1px solid var(--n100);
        }

        .btn-cancel-sf {
            font-family: var(--ff);
            font-size: 14px;
            font-weight: 500;
             padding: 9px 20px;
            border-radius: 100px;
            background: var(--n100);
            color: var(--n600);
            border: 1.5px solid var(--n200);
            text-decoration: none;
            transition: var(--tr);
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .btn-cancel-sf:hover {
            background: var(--n200);
            color: var(--n800);
        }

        .btn-submit-sf {
            font-family: var(--ff);
            font-size: 14px;
            font-weight: 500;
               padding: 9px 20px;
            border-radius: 100px;
            background: #fff;
            color: #10b981;
            border: 2px solid #a7f3d0 !important;
            cursor: pointer; 
            transition: var(--tr);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit-sf:hover {
            transform: translateY(-2px); 
            background: #ecfdf5;
            color: #10b981;
        }

        .btn-submit-sf:active {
            transform: translateY(0);
        }
    </style>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        /* Page-local neutral --n* tokens remapped for dark; the emerald --g* accent
           tokens (header gradient, focus rings, hints, active steps) are kept. */
        html[data-theme="dark"] {
            --n50:  #17233a;
            --n100: #22304a;
            --n200: #2a3a55;
            --n400: #94a3b8;
            --n600: #94a3b8;
            --n800: #e2e8f0;
        }
        html[data-theme="dark"] .btn-go-back { background:#1e293b; }
        html[data-theme="dark"] .sell-card { background:#1e293b; }
        html[data-theme="dark"] .step-num { background:#1e293b; }
        html[data-theme="dark"] .btn-submit-sf { background:#1e293b; }
    </style>

    <div class="dashboard__content-wrap sell-create-page">

        <!-- Top bar -->
        <div class="sell-topbar">
            <h4>{{ __('Sells History') }}</h4>
            <a href="{{ route('instructor.my-sells.index') }}" class="btn-go-back">
                <i class="fa fa-arrow-left"></i> Go Back
            </a>
        </div>

        <!-- Form card -->
        <div class="sell-card">

            <!-- Card header -->
            <div class="sell-card-header">
                <div class="sell-header-icon"><i class="fas fa-shopping-cart"></i></div>
                <div>
                    <h6>{{ __('Create New Sale') }}</h6>
                    <p>Assign a student to a course and batch to record the sale.</p>
                </div>
            </div>

            <!-- Step indicators -->
            <div class="sell-steps">
                <div class="sell-step">
                    <div class="step-num active">1</div>
                    <span class="step-label active">Select Student</span>
                </div>
                <div class="step-connector"></div>
                <div class="sell-step">
                    <div class="step-num">2</div>
                    <span class="step-label">Select Course</span>
                </div>
                <div class="step-connector"></div>
                <div class="sell-step">
                    <div class="step-num">3</div>
                    <span class="step-label">Select Batch</span>
                </div>
            </div>

            <!-- Form -->
            <form action="{{ route('instructor.my-sells.store') }}" method="POST">
                @csrf

                <div class="sell-form-body">

                    <!-- Student & Course -->
                    <div class="sell-section-label">Enrolment Details</div>
                    <div class="row g-4 mb-4">

                        <div class="col-md-6">
                            <div class="sf-field">
                                <label for="user_id">{{ __('Student') }} <span class="req-dot"></span></label>
                                <div class="select-wrap">
                                    <select name="user_id" id="user_id" required onchange="updateSteps(1)">
                                        <option value="">Choose a student…</option>
                                        @foreach ($stidents as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sf-field">
                                <label for="courseId">{{ __('Course') }} <span class="req-dot"></span></label>
                                <div class="select-wrap">
                                    <select name="course_id" id="courseId" required onchange="updateSteps(2)">
                                        <option value="">Choose a course…</option>
                                        @foreach ($courses as $c)
                                            <option value="{{ $c->id }}">{{ $c->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Batch -->
                    {{-- Audit 2026-05-19 (post-Phase 4C feedback) — Batch
                         is optional on Manual Sale. A coach may record a
                         course sale without yet assigning the student to
                         a specific batch (e.g. recorded-only courses, or
                         when the batch isn't decided yet). --}}
                    <div class="sell-section-label">Batch Assignment</div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="sf-field">
                                <label for="batchId">
                                    {{ __('Batch') }}
                                    <small class="text-muted" style="font-weight:400; font-size:11px;">({{ __('optional') }})</small>
                                </label>
                                <div class="select-wrap">
                                    <select name="batch_id" id="batchId" disabled>
                                        <option value="">Select a course first to load batches…</option>
                                    </select>
                                </div>
                                <div class="batch-loading" id="batchLoading">
                                    <div class="spin"></div> Loading available batches…
                                </div>
                            </div>
                            <div class="batch-hint" id="batchHint">
                                <i class="fas fa-info-circle"></i>
                                Batches will load automatically once you pick a course above. Leave blank if no batch yet.
                            </div>
                        </div>
                    </div>

                </div><!-- /form-body -->

                <!-- Footer -->
                <div class="sell-footer">
                    <a href="{{ route('instructor.my-sells.index') }}" class="btn-cancel-sf">
                        <i class="bi bi-x-lg"></i> Cancel
                    </a>
                    <button type="submit" class="btn-submit-sf">
                        <i class="fas fa-check"></i> {{ __('Submit Sale') }}
                    </button>
                </div>

            </form>
        </div>

    </div>

    <script>
        /* ── Step highlight ── */
        function updateSteps(completedStep) {
            const nums = document.querySelectorAll('.step-num');
            const labels = document.querySelectorAll('.step-label');
            const connectors = document.querySelectorAll('.step-connector');
            for (let i = 0; i <= completedStep; i++) {
                nums[i].classList.remove('active');
                nums[i].classList.add('done');
                labels[i].classList.remove('active');
                if (connectors[i]) connectors[i].classList.add('done');
            }
            if (nums[completedStep + 1]) {
                nums[completedStep + 1].classList.add('active');
                labels[completedStep + 1].classList.add('active');
            }
        }

        /* ── Dynamic batch loader ── */
        document.getElementById('courseId').addEventListener('change', function() {
            const courseId = this.value;
            const batchSel = document.getElementById('batchId');
            const loading = document.getElementById('batchLoading');
            const hint = document.getElementById('batchHint');

            batchSel.innerHTML = '<option value="">Choose Select Batch</option>';

            if (!courseId) {
                batchSel.disabled = true;
                hint.style.display = 'flex';
                return;
            }

            hint.style.display = 'none';
            loading.classList.add('show');
            batchSel.disabled = true;

            fetch("{{ route('instructor.my-sells.getbatches') }}?course_id=" + courseId)
                .then(r => r.json())
                .then(data => {
                    loading.classList.remove('show');
                    batchSel.disabled = false;
                    updateSteps(2);

                    if (!data.length) {
                        batchSel.innerHTML = '<option value="">No batches available for this course</option>';
                        return;
                    }

                    data.forEach(function(value) {
                        const opt = document.createElement('option');
                        opt.value = value.id;

                        const fmtDate = d => new Date(d).toLocaleDateString('en-GB', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        });
                        const fmtTime = t => new Date('1970-01-01T' + t).toLocaleTimeString('en-IN', {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });

                        opt.textContent =
                            `${value.title}  (${fmtDate(value.start_date)} – ${fmtDate(value.end_date)})  ${fmtTime(value.start_time)} – ${fmtTime(value.end_time)}`;
                        batchSel.appendChild(opt);
                    });
                })
                .catch(() => {
                    loading.classList.remove('show');
                    batchSel.innerHTML = '<option value="">Error loading batches. Try again.</option>';
                    batchSel.disabled = false;
                });
        });
    </script>
@endsection
