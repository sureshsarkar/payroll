@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <style>
        .templete-img {
            width: 30%;
            margin: auto;
        }

        .templet-title {
            font-size: 25px;
            font-weight: 800;
            color: #10b981;
        }

        .website-icon {
            font-size: 50px;
        }

        .no-website-section i {
            color: #00bc77;
            padding: 8px 14px;
            background: #c9ffeb;
            border-radius: 47px;
        }

        .no-website-section {
            text-align: center;
            background: #e8fff4;
            padding: 30px 0px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .no-website-section p {
            color: #5a5555;
        }

        .no-website-section h3 {
            color: #676a69;
        }

        .website-left-section {
            text-align: center;
            background: #e8fff4;
            padding: 30px 0px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .lc-modal__header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            background: #fff;
            align-items: flex-start;
        }

        .lc-modal__header-inner {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lc-modal__icon-wrap {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #00c896, #0e9de8);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(0, 200, 150, 0.25);
            flex-shrink: 0;
        }

        .lc-modal__icon-wrap i {
            font-size: 32px;
        }

        .lc-modal__title {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
            color: #111;
        }

        .lc-modal__subtitle {
            font-size: 12px;
            color: #888;
            margin: 0;
        }

        .lc-btn--save {
            background: linear-gradient(135deg, #04cb99, #00b085);
            color: #fff;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
        }

        .lc-btn {
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: background 0.2s ease;
        }

        .modal-dialog {
            margin-top: 150px;
        }

        .lc-modal__body small {
            margin-top: 4px !important;
        }


        button:disabled {
            background-color: #ccc;
            color: #666;
            cursor: not-allowed;
            opacity: 0.7;
        }

        tr {
            background: #e8fff4 !important;
        }

        .btn-danger {
            border: none !important;
        }

        .btn-outline-danger {
            background: #ffdede;
            color: red;
            border: 2px solid red !important;
        }

        .select2-container {
            z-index: 999999 !important;
        }

        .select2-selection.select2-selection--multiple {
            height: auto !important;
        }


        .template-card { cursor:pointer; border:1px solid #eee; border-radius:10px; padding:8px; transition:.3s; background:#fff; height:100%; }
        .template-card:hover { transform:translateY(-4px); box-shadow:0 10px 25px rgba(0,0,0,0.08); }
        .template-card.active { border:2px solid #0d6efd; }
        .template-preview { height:180px; overflow:hidden; border-radius:8px; position:relative; }
        .template-img { width:100%; transition:transform 4s ease; }
        .template-card:hover .template-img { transform:translateY(-50%); }
        .select2-selection__choice__display { color: #1a1a1a; }
        .select2-results__option { color: #000000; }

        /* Phase 2 — template browse/filter UX (2026-05-12).
           Replaces the malformed nav-tabs with filter chips + search +
           preview-before-select sub-modal. */
        .tpl-toolbar { display:flex; align-items:center; gap:14px; margin-bottom:16px; flex-wrap:wrap; }
        .tpl-search { position:relative; flex:1; max-width:340px; min-width:220px; }
        .tpl-search i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; }
        .tpl-search input { padding-left:40px !important; height:42px; border-radius:21px; border:1px solid #e5e7eb; }
        .tpl-results-count { color:#64748b; font-size:13px; font-weight:600; }
        .tpl-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #f1f5f9; }
        .tpl-chip { background:#f8fafc; border:1px solid #e5e7eb; color:#475569; padding:8px 16px; border-radius:22px; font-size:13px; font-weight:600; cursor:pointer; transition:.2s; display:inline-flex; align-items:center; gap:6px; }
        .tpl-chip:hover { background:#fff; border-color:#0d6efd; color:#0d6efd; }
        .tpl-chip.active { background:#0d6efd; border-color:#0d6efd; color:#fff; }
        .tpl-chip-count { background:#e5e7eb; color:#475569; padding:1px 8px; border-radius:10px; font-size:11px; font-weight:700; }
        .tpl-chip.active .tpl-chip-count { background:rgba(255,255,255,.25); color:#fff; }
        .tpl-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
        .tpl-card-wrap { animation:tplFade .25s ease; }
        @keyframes tplFade { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        .tpl-card { border:2px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; transition:.25s; height:100%; display:flex; flex-direction:column; }
        .tpl-card:hover { border-color:#cbd5e1; box-shadow:0 8px 24px rgba(13,110,253,.08); }
        .tpl-card.active { border-color:#0d6efd; box-shadow:0 8px 24px rgba(13,110,253,.18); }
        .tpl-card.active::after { content:'✓'; position:absolute; top:10px; left:10px; background:#0d6efd; color:#fff; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; }
        .tpl-card-preview { position:relative; aspect-ratio:5/3; overflow:hidden; background:#f1f5f9; }
        .tpl-card-img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s ease; }
        .tpl-card:hover .tpl-card-img { transform:scale(1.04); }
        .tpl-preview-btn { position:absolute; bottom:10px; right:10px; background:rgba(15,23,42,.85); color:#fff; border:none; padding:7px 14px; border-radius:18px; font-size:12px; font-weight:600; cursor:pointer; opacity:0; transform:translateY(6px); transition:.2s; backdrop-filter:blur(6px); }
        .tpl-card:hover .tpl-preview-btn { opacity:1; transform:translateY(0); }
        .tpl-card-body { padding:12px 14px; cursor:pointer; flex:1; position:relative; }
        .tpl-card-title { font-weight:600; color:#1f2937; font-size:14px; margin-bottom:2px; }
        .tpl-card-cat { font-size:11px; color:#94a3b8; letter-spacing:.5px; text-transform:uppercase; font-weight:600; }
        .tpl-card.active .tpl-card-title { color:#0d6efd; }
        .tpl-empty { text-align:center; padding:60px 20px; color:#94a3b8; }
        .tpl-empty i { font-size:42px; opacity:.4; display:block; margin-bottom:10px; }

        /* Preview sub-modal */
        #templatePreviewModal .modal-dialog { max-width:980px; margin-top:60px; }
        #templatePreviewModal .modal-content { border-radius:14px; overflow:hidden; }
        #templatePreviewModal .preview-frame { background:#f1f5f9; aspect-ratio:16/9; overflow:hidden; max-height:540px; }
        #templatePreviewModal .preview-frame img { width:100%; height:100%; object-fit:cover; display:block; }
        #templatePreviewModal .use-tpl-btn { background:linear-gradient(135deg,#0d6efd,#0a58ca); color:#fff; border:none; padding:12px 26px; border-radius:8px; font-weight:700; cursor:pointer; }
        #templatePreviewModal .preview-meta-cat { display:inline-block; background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
    </style>
    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components.
           Website-Builder listing + template-picker chrome only. Brand blues (#0d6efd),
           brand greens/green tints, gradients and semantic swatches (amber/red) are kept. */
        html[data-theme="dark"] .lc-modal__header { background: #1e293b; }

        html[data-theme="dark"] .template-card { background: #1e293b; }
        html[data-theme="dark"] .template-card:hover { box-shadow: none; }

        html[data-theme="dark"] .tpl-search input { border-color: #2a3a55; }
        html[data-theme="dark"] .tpl-results-count { color: #94a3b8; }
        html[data-theme="dark"] .tpl-chips { border-bottom-color: #2a3a55; }
        html[data-theme="dark"] .tpl-chip { background: #17233a; border-color: #2a3a55; color: #94a3b8; }
        html[data-theme="dark"] .tpl-chip:hover { background: #1e293b; }
        html[data-theme="dark"] .tpl-chip-count { background: #2a3a55; color: #94a3b8; }
        html[data-theme="dark"] .tpl-card { background: #1e293b; border-color: #2a3a55; }
        html[data-theme="dark"] .tpl-card-preview { background: #22304a; }
        html[data-theme="dark"] #templatePreviewModal .preview-frame { background: #22304a; }
    </style>
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title">
            <h4 class="title">{{ __('Website Builder') }}</h4>
        </div>
        @if (isset($page) && $page->count() > 0)
            <div class="row">
                <div class="col-md-9 no-website-section">
                    <table class="table table-borderless website-builder-table">
                        <thead>
                            <tr>
                                <th>{{ __('No') }}</th>
                                <th>{{ __('Template') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>01</td>
                                <td><img src="{{ asset($page->getTemplate->image ?? '') }}" alt="{{ $page->website_name }}"
                                        class="rounded-2" style="width: 92px; height: 52px; object-fit: cover;"></td>
                                <td>{{ $page->website_name }}</td>
                                <td>
                                    @if ($page->is_published == 1)
                                        <a href="{{ route('instructor.setPublishData', [$page->id, 0]) }}"
                                            class="btn btn-outline-success btn-xs" title="Click to unpublish">Published</a>
                                    @else
                                        <a href="{{ route('instructor.setPublishData', [$page->id, 1]) }}"
                                            class="btn btn-outline-danger btn-xs" title="Click to publish">UnPublished</a>
                                    @endif
                                </td>

                                <td>
                                    <a href="{{ route('instructor.landing-page-builder.index') }}" class="edit-btn-style"
                                        title="{{ __('Edit') }}">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <form method="post" action="{!! route('instructor.website-builder-delete', [$page->id]) !!}" style="display: inline-block;">
                                        {!! csrf_field() !!}
                                        {!! method_field('DELETE') !!}
                                        <button type="submit" class="btn btn-danger btn-sm raw-margin-right-8 p-0"
                                            onclick="return confirm('Are you sure you want to delete website')"><i
                                                class="fa fa-trash"></i>
                                        </button>
                                    </form>

                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-3">
                    <div class="website-left-section">
                        <a href="#" class="btn btn-primary btn-hight-basic" data-bs-toggle="modal"
                            data-bs-target="#addProductModal">
                            <i class="bi bi-bag-check"></i> Add Products
                        </a>

                    </div>

                </div>
            </div>




            {{-- website builder first step model  --}}
            <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content lc-modal">

                        {{-- Header --}}
                        <div class="modal-header lc-modal__header">
                            <div class="lc-modal__header-inner">
                                <div class="lc-modal__icon-wrap">
                                    <i class="bi bi-bag-check"></i>
                                </div>
                                <div>
                                    <h5 class="modal-title lc-modal__title" id="addProductModalLabel">
                                        {{ __('Add Products') }}
                                    </h5>
                                    <p class="lc-modal__subtitle">
                                        {{ __('Add products that you want to show in youe website') }}
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="lc-modal__close btn-close" data-bs-dismiss="modal"
                                aria-label="{{ __('Close') }}"></button>
                        </div>

                        {{-- Form --}}
                        <form action="{{ route('instructor.website-builder-product.submit', $page->id) }}" method="post"
                            novalidate>
                            @csrf
                            <div class="modal-body lc-modal__body">

                                <div class="row g-3 mb-3">
                                    <div class="col-md-12">
                                        <label for="website_name" class="lc-field__label">
                                            {{ __('Choose Products') }}
                                            <span class="lc-field__required">*</span>
                                        </label>
                                        <select name="product_ids[]" class="select2" id="" multiple>
                                            @foreach ($courses as $c)
                                                <option @selected(in_array($c->id ?? 0, $page->product_ids ?? [])) value="{{ $c->id }}">
                                                    {{ $c->title }}</option>
                                            @endforeach
                                        </select>
                                        <!-- Message -->
                                        <small id="website_name_msg"></small>
                                    </div>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="modal-footer lc-modal__footer">
                                <div class="lc-modal__footer-actions">
                                    <button type="submit" class="lc-btn lc-btn--save" id="save--submit--btn">
                                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                            <path d="M2 6.5l3.5 3.5 5.5-6" stroke="#fff" stroke-width="1.5"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <div class="row">
                <div class="col-md-12 no-website-section">
                    <div class="website-icon">
                        <i class="bi bi-globe-americas"></i>
                    </div>
                    <h3>No Website Yet</h3>
                    <p>Create your first website to start building your online presence and sharing your content with the
                        world.
                    </p>
                    <a href="#" class="btn btn-primary btn-hight-basic" data-bs-toggle="modal"
                        data-bs-target="#websiteBuilderModal">+ Create Your First Website</a>
                </div>
            </div>
        @endif

    </div>







    {{-- website builder first step model  --}}
    <div class="modal fade" id="websiteBuilderModal" tabindex="-1" aria-labelledby="websiteBuilderModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl mt-4">
            <div class="modal-content lc-modal">

                {{-- Header --}}
                <div class="modal-header lc-modal__header">
                    <div class="lc-modal__header-inner">
                        <div class="lc-modal__icon-wrap">
                            <i class="bi bi-globe-americas"></i>
                        </div>
                        <div>
                            <h5 class="modal-title lc-modal__title" id="websiteBuilderModalLabel">
                                {{ __('Website Building') }}
                            </h5>
                            <p class="lc-modal__subtitle">
                                {{ __('Create and launch your website effortlessly') }}
                            </p>
                        </div>
                    </div>
                    <button type="button" class="lc-modal__close btn-close" data-bs-dismiss="modal"
                        aria-label="{{ __('Close') }}"></button>
                </div>

                {{-- Form --}}
                <form action="{{ route('instructor.website-builder.submit') }}" method="post" novalidate>
                    @csrf


                    <div class="modal-body lc-modal__body">
                        <!-- STEP 1: TEMPLATE SELECTION (Phase 2 rewrite — filter chips + search + preview) -->
                        <div id="step-1">
                            <h5 class="mb-1">Pick a template that fits your business</h5>
                            <p class="text-muted mb-3" style="font-size:13px;">Filter by category, search by name, or click <strong>Preview</strong> on a card to see a closer look before you commit.</p>

                            <div class="tpl-toolbar">
                                <div class="tpl-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="tplSearch" class="form-control" placeholder="Search templates…" autocomplete="off">
                                </div>
                                <span class="tpl-results-count" id="tplResultsCount"></span>
                            </div>

                            @php
                                $cats_with_templates = $categories->filter(fn($c) => $c->templates->where('status',1)->count() > 0);
                                $total_templates = $cats_with_templates->sum(fn($c) => $c->templates->where('status',1)->count());
                            @endphp

                            <div class="tpl-chips" id="templateChips">
                                <button type="button" class="tpl-chip active" data-cat="all">
                                    All Templates <span class="tpl-chip-count">{{ $total_templates }}</span>
                                </button>
                                @foreach ($cats_with_templates as $cat)
                                    <button type="button" class="tpl-chip" data-cat="cat{{ $cat->id }}">
                                        {{ $cat->name }}
                                        <span class="tpl-chip-count">{{ $cat->templates->where('status',1)->count() }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <div class="tpl-grid" id="tplGrid">
                                @foreach ($cats_with_templates as $cat)
                                    @foreach ($cat->templates->where('status',1) as $t)
                                        <div class="tpl-card-wrap" data-cat="cat{{ $cat->id }}" data-name="{{ strtolower($t->template_name) }}" data-tplid="{{ $t->id }}">
                                            <div class="tpl-card">
                                                <div class="tpl-card-preview">
                                                    <img src="{{ asset($t->image) }}" class="tpl-card-img" alt="{{ $t->template_name }}" loading="lazy">
                                                    <button type="button" class="tpl-preview-btn" data-id="{{ $t->id }}" data-name="{{ $t->template_name }}" data-img="{{ asset($t->image) }}" data-cat="{{ $cat->name }}" onclick="openTplPreview(event, this)">
                                                        <i class="bi bi-eye"></i> Preview
                                                    </button>
                                                </div>
                                                <div class="tpl-card-body" onclick="selectTemplate(this, '{{ $t->id }}')">
                                                    <div class="tpl-card-title">{{ $t->template_name }}</div>
                                                    <div class="tpl-card-cat">{{ $cat->name }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>

                            <div class="tpl-empty" id="tplEmpty" style="display:none;">
                                <i class="bi bi-search"></i>
                                <p>No templates match your search. Try a different keyword or clear the filter.</p>
                            </div>

                            <input type="hidden" name="template_id" id="template_id">

                            <div class="text-end mt-4">
                                <button type="button" class="btn btn-primary" onclick="goToStep2()" disabled
                                    id="nextBtn">
                                    Next →
                                </button>
                            </div>
                        </div>

                        <!-- STEP 2: FORM -->
                        <div id="step-2" style="display:none;">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="lc-field__label">Name your site *</label>
                                    <input type="text" name="website_name" id="website_name_id"
                                        class="form-control p-2" required>
                                    <!-- Message -->
                                    <small id="website_name_msg"></small>
                                </div>

                                <div class="col-md-6">
                                    <label class="lc-field__label">Choose your domain *</label>
                                    <div class="d-flex">
                                        <input type="text" name="subdomain" id="subdomain_id"
                                            class="form-control" required>
                                        <span class="p-2 border rounded-2">.{{ config('app.coach_domain', 'mbsguru.com') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn btn-secondary" onclick="goToStep1()">← Back</button>
                            </div>
                        </div>

                    </div>




                    {{-- Footer --}}
                    <div class="modal-footer lc-modal__footer">
                        <div class="lc-modal__footer-actions">
                            <button type="submit" class="lc-btn lc-btn--save" id="save--submit--btn" disabled
                                style="display: none">
                                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                    <path d="M2 6.5l3.5 3.5 5.5-6" stroke="#fff" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ __('Save') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Preview sub-modal (Phase 2 — preview-before-select).
         Opened by the "Preview" button on a template card; "Use This
         Template" routes back through selectTemplate() and closes the
         preview so the user lands on the now-selected card in the picker. --}}
    <div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="preview-meta-cat" id="previewMetaCat">—</span>
                        <h5 class="modal-title mb-0 mt-1" id="previewMetaName">Template name</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="preview-frame">
                    <img id="previewMetaImg" src="" alt="">
                </div>
                <div class="modal-footer" style="justify-content:space-between;">
                    <small class="text-muted">Click <strong>Use This Template</strong> to select it and continue to name + subdomain.</small>
                    <button type="button" class="use-tpl-btn" id="useTemplateBtn">
                        <i class="bi bi-check2-circle"></i> Use This Template
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // ─────────────────────────────────────────────────────────────
    // Template picker — Phase 2 (filter chips, search, preview-before-select).
    // Replaces the prior broken nav-tabs + double selectTemplate() shim.
    // ─────────────────────────────────────────────────────────────

    function selectTemplate(el, id) {
        document.querySelectorAll('.tpl-card').forEach(c => c.classList.remove('active'));
        // el may be a child (.tpl-card-body) — walk up to .tpl-card.
        const card = el.closest('.tpl-card') || el;
        card.classList.add('active');
        document.getElementById('template_id').value = id;
        document.getElementById('nextBtn').disabled = false;
    }

    function goToStep2() {
        document.getElementById('step-1').style.display = 'none';
        document.getElementById('step-2').style.display = '';
        document.getElementById('save--submit--btn').style.display = '';
    }

    function goToStep1() {
        document.getElementById('step-2').style.display = 'none';
        document.getElementById('step-1').style.display = '';
        document.getElementById('save--submit--btn').style.display = 'none';
    }

    // ── Filter chips + search ──────────────────────────────────
    function applyTplFilter() {
        const activeChip = document.querySelector('.tpl-chip.active');
        const cat = activeChip ? activeChip.dataset.cat : 'all';
        const q = document.getElementById('tplSearch').value.toLowerCase().trim();
        let visible = 0;
        document.querySelectorAll('.tpl-card-wrap').forEach(w => {
            const matchCat = cat === 'all' || w.dataset.cat === cat;
            const matchQ = !q || w.dataset.name.includes(q);
            const show = matchCat && matchQ;
            w.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('tplResultsCount').textContent =
            visible + ' template' + (visible !== 1 ? 's' : '');
        document.getElementById('tplEmpty').style.display = visible === 0 ? 'block' : 'none';
        document.getElementById('tplGrid').style.display = visible === 0 ? 'none' : 'grid';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.tpl-chip').forEach(chip => {
            chip.addEventListener('click', e => {
                document.querySelectorAll('.tpl-chip').forEach(c => c.classList.remove('active'));
                e.currentTarget.classList.add('active');
                applyTplFilter();
            });
        });
        const search = document.getElementById('tplSearch');
        if (search) search.addEventListener('input', applyTplFilter);
        applyTplFilter();
    });

    // ── Preview-before-select sub-modal ─────────────────────────
    let _pendingPreviewId = null;
    function openTplPreview(evt, btn) {
        evt.stopPropagation();  // don't trigger card-body click-to-select
        _pendingPreviewId = btn.dataset.id;
        document.getElementById('previewMetaName').textContent = btn.dataset.name;
        document.getElementById('previewMetaCat').textContent  = btn.dataset.cat;
        document.getElementById('previewMetaImg').src          = btn.dataset.img;
        const modalEl = document.getElementById('templatePreviewModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('useTemplateBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!_pendingPreviewId) return;
            const wrap = document.querySelector('.tpl-card-wrap[data-tplid="' + _pendingPreviewId + '"]');
            if (wrap) {
                const body = wrap.querySelector('.tpl-card-body');
                if (body) selectTemplate(body, _pendingPreviewId);
                wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            const modalEl = document.getElementById('templatePreviewModal');
            bootstrap.Modal.getInstance(modalEl)?.hide();
        });
    });

    // ── Step-2: subdomain slug + availability check ─────────────
    $(document).ready(function() {
        $('#subdomain_id').on('keyup', function() {
            const v = $(this).val().toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            $(this).val(v);
        });

        $('#website_name_id').on('keyup', function() {
            const website_name = $(this).val();
            if (website_name.length < 3) {
                $('#website_name_msg').html('');
                $('#save--submit--btn').prop('disabled', 'disabled');
                $('#subdomain_id').val('');
                return;
            }
            const slug = website_name.toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            $('#subdomain_id').val(slug);

            $.ajax({
                url: "{{ route('instructor.check.website-builder.name') }}",
                type: 'POST',
                data: { _token: "{{ csrf_token() }}", website_name: website_name },
                success: function(response) {
                    if (response.exists) {
                        $('#save--submit--btn').prop('disabled', 'disabled');
                        $('#website_name_msg').html('<i class="bi bi-x-circle"></i> Not Available').css('color', 'red');
                    } else {
                        $('#save--submit--btn').prop('disabled', '');
                        $('#website_name_msg').html('<i class="bi bi-patch-check"></i> Available').css('color', 'green');
                    }
                }
            });
        });
    });
</script>
