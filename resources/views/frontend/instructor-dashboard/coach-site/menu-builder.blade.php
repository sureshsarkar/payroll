@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    .mnb-page{ max-width:920px; margin:0 auto; }
    .mnb-card{ background:#fff; border:1px solid #e8eaf3; border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.04); margin-bottom:20px; }
    .mnb-card__head{ display:flex; align-items:center; justify-content:space-between; gap:16px; padding:18px 22px; border-bottom:1px solid #f0f1f7; }
    .mnb-card__title{ font-size:16px; font-weight:700; color:#171a3a; margin:0; }
    .mnb-card__sub{ font-size:12.5px; color:#7a7f9a; margin:3px 0 0; }
    .mnb-card__body{ padding:16px 22px 22px; }
    .mnb-switch{ display:inline-flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#33374d; font-size:14px; }
    .mnb-switch input{ width:42px; height:24px; appearance:none; background:#cfd3e6; border-radius:99px; position:relative; transition:.2s; cursor:pointer; }
    .mnb-switch input:checked{ background:var(--corp-brand,#5b53ff); }
    .mnb-switch input::after{ content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; background:#fff; border-radius:50%; transition:.2s; }
    .mnb-switch input:checked::after{ left:20px; }
    .mnb-btn{ border:none; border-radius:9px; padding:9px 15px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
    .mnb-btn--primary{ background:var(--corp-brand,#5b53ff); color:#fff; }
    .mnb-btn--ghost{ background:#eef0f8; color:#444a68; }
    .mnb-btn--danger{ background:#fdecec; color:#d23b3b; }
    .mnb-btn--sm{ padding:5px 9px; font-size:12px; }
    .mnb-list{ list-style:none; margin:0; padding:0; }
    .mnb-item{ display:flex; align-items:center; gap:10px; background:#fafbff; border:1px solid #ececf4; border-radius:11px; padding:10px 12px; margin-bottom:8px; }
    .mnb-item.is-child{ margin-left:34px; background:#fff; border-style:dashed; }
    .mnb-item.is-child-2{ margin-left:64px; background:#fff; border-style:dotted; }
    .mnb-item__mega{ font-size:10px; font-weight:700; letter-spacing:.05em; color:#fff; background:var(--corp-brand,#5b53ff); padding:2px 7px; border-radius:99px; }
    .mnb-item.dragging{ opacity:.45; }
    .mnb-item__handle{ cursor:grab; color:#b6bbd4; }
    .mnb-item__label{ font-weight:600; color:#23264a; font-size:14px; }
    .mnb-item__type{ font-size:11px; color:#8b90ad; background:#eef0f8; padding:2px 8px; border-radius:99px; }
    .mnb-item__hidden{ font-size:11px; color:#c2820a; }
    .mnb-item__actions{ margin-left:auto; display:flex; gap:6px; }
    .mnb-empty{ text-align:center; padding:30px; color:#9aa0bc; }
    dialog.mnb-dialog{ border:none; border-radius:16px; padding:0; width:min(520px,94vw); box-shadow:0 24px 60px rgba(16,24,40,.22); }
    dialog.mnb-dialog::backdrop{ background:rgba(16,18,40,.45); }
    .mnb-dlg__head{ padding:18px 22px; border-bottom:1px solid #f0f1f7; font-weight:700; color:#171a3a; }
    .mnb-dlg__body{ padding:18px 22px; }
    .mnb-dlg__foot{ padding:14px 22px; border-top:1px solid #f0f1f7; display:flex; gap:10px; justify-content:flex-end; }
    .mnb-grp{ margin-bottom:14px; }
    .mnb-grp label{ display:block; font-size:13px; font-weight:600; color:#33374d; margin-bottom:5px; }
    .mnb-grp input[type=text], .mnb-grp select{ width:100%; border:1px solid #e2e5f0; border-radius:10px; padding:9px 12px; font-size:14px; }
    .mnb-status{ font-size:13px; color:#16a34a; font-weight:600; opacity:0; transition:.2s; }
    .mnb-status.show{ opacity:1; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .mnb-card{ background:#1e293b; border-color:#2a3a55; box-shadow:none; }
html[data-theme="dark"] .mnb-card__head{ border-bottom-color:#2a3a55; }
html[data-theme="dark"] .mnb-card__title{ color:#e2e8f0; }
html[data-theme="dark"] .mnb-card__sub{ color:#94a3b8; }
html[data-theme="dark"] .mnb-switch{ color:#e2e8f0; }
html[data-theme="dark"] .mnb-switch input:not(:checked){ background:#3a4a63; }
html[data-theme="dark"] .mnb-btn--ghost{ background:#22304a; color:#e2e8f0; }
html[data-theme="dark"] .mnb-item{ background:#17233a; border-color:#2a3a55; }
html[data-theme="dark"] .mnb-item.is-child,
html[data-theme="dark"] .mnb-item.is-child-2{ background:#1e293b; }
html[data-theme="dark"] .mnb-item__handle{ color:#94a3b8; }
html[data-theme="dark"] .mnb-item__label{ color:#e2e8f0; }
html[data-theme="dark"] .mnb-item__type{ background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .mnb-empty{ color:#94a3b8; }
html[data-theme="dark"] dialog.mnb-dialog{ background:#1e293b; color:#e2e8f0; }
html[data-theme="dark"] .mnb-dlg__head{ border-bottom-color:#2a3a55; color:#e2e8f0; }
html[data-theme="dark"] .mnb-dlg__foot{ border-top-color:#2a3a55; }
html[data-theme="dark"] .mnb-grp label{ color:#e2e8f0; }
html[data-theme="dark"] .mnb-grp input[type=text],
html[data-theme="dark"] .mnb-grp select{ background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
</style>

@php
    $routeChoices = [
        // Common coach-site link targets (safe, public, brand-aware).
        'instructor.dashboard' => __('My account / dashboard'),
    ];
@endphp

<div class="corp-page mnb-page">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <h1 style="font-size:22px;font-weight:800;color:#171a3a;margin:0;">{{ __('Navigation Menu') }}</h1>
            <p style="color:#7a7f9a;font-size:13.5px;margin:6px 0 0;">{{ __('Build your website menu — drag to reorder, indent to create dropdowns. Same menu on every page.') }}</p>
        </div>
        <a href="{{ route('instructor.web-page.index') }}" class="mnb-btn mnb-btn--ghost"><i class="fas fa-arrow-left"></i> {{ __('Back to website') }}</a>
    </div>

    <div class="mnb-card">
        <div class="mnb-card__head">
            <div>
                <h2 class="mnb-card__title">{{ __('Use custom menu') }}</h2>
                <p class="mnb-card__sub">{{ __('When off, your menu is built automatically from your published pages.') }}</p>
            </div>
            <label class="mnb-switch">
                <input type="checkbox" id="mnbActive" {{ ($menu->is_active ?? true) ? 'checked' : '' }}>
                <span id="mnbActiveLabel">{{ ($menu->is_active ?? true) ? __('On') : __('Off') }}</span>
            </label>
        </div>
    </div>

    <div class="mnb-card">
        <div class="mnb-card__head">
            <div>
                <h2 class="mnb-card__title">{{ __('Menu items') }}</h2>
                <p class="mnb-card__sub">{{ __('Drag the handle to reorder. Use ⇥ to make an item a dropdown child of the one above it.') }}</p>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="mnb-status" id="mnbStatus"><i class="fas fa-check-circle"></i> {{ __('Saved') }}</span>
                <button type="button" class="mnb-btn mnb-btn--primary" id="mnbAdd"><i class="fas fa-plus"></i> {{ __('Add item') }}</button>
            </div>
        </div>
        <div class="mnb-card__body">
            <ul class="mnb-list" id="mnbList">
                @foreach($tree as $node)
                    @include('frontend.instructor-dashboard.coach-site._menu-item', ['item' => $node['item'], 'depth' => $node['depth']])
                @endforeach
            </ul>
            <div class="mnb-empty" id="mnbEmpty" style="{{ $tree->isEmpty() ? '' : 'display:none;' }}">
                <i class="fas fa-list" style="font-size:26px;opacity:.4;"></i>
                <p>{{ __('No menu items yet. Click “Add item” to start, or keep the automatic page menu.') }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Add / edit dialog --}}
<dialog class="mnb-dialog" id="mnbDialog">
    <form id="mnbForm" method="dialog">
        <div class="mnb-dlg__head" id="mnbDlgTitle">{{ __('Add menu item') }}</div>
        <div class="mnb-dlg__body">
            <input type="hidden" id="mnbItemId" value="">
            <div class="mnb-grp">
                <label>{{ __('Label') }}</label>
                <input type="text" id="mnbLabel" maxlength="80" placeholder="{{ __('e.g. About us') }}">
            </div>
            <div class="mnb-grp">
                <label>{{ __('Links to') }}</label>
                <select id="mnbLinkType">
                    <option value="page">{{ __('A page on my site') }}</option>
                    <option value="section">{{ __('A section on a page') }}</option>
                    <option value="url">{{ __('A custom / external URL') }}</option>
                    <option value="home">{{ __('Site home') }}</option>
                    <option value="route">{{ __('App route (advanced)') }}</option>
                    <option value="none">{{ __('Heading / no link (mega column title)') }}</option>
                </select>
            </div>
            <div class="mnb-grp" id="mnbLayoutGrp">
                <label>{{ __('Top-level display') }}</label>
                <select id="mnbLayout">
                    <option value="dropdown">{{ __('Dropdown (single column)') }}</option>
                    <option value="mega">{{ __('Mega menu (wide multi-column panel)') }}</option>
                </select>
                <p style="font-size:11.5px;color:#9aa0bc;margin:5px 0 0;">{{ __('Mega: indent items below this one to make columns, then indent links under each column.') }}</p>
            </div>
            <div class="mnb-grp" data-when="page section">
                <label>{{ __('Page') }}</label>
                <select id="mnbPage">
                    @foreach($pages as $p)
                        <option value="{{ $p->id }}">{{ $p->title }} ({{ $p->page_type === 'home' ? '/' : '/'.$p->slug }})</option>
                    @endforeach
                </select>
            </div>
            <div class="mnb-grp" data-when="section">
                <label>{{ __('Section anchor') }}</label>
                <input type="text" id="mnbAnchor" maxlength="120" placeholder="{{ __('e.g. contact (without #)') }}">
            </div>
            <div class="mnb-grp" data-when="url">
                <label>{{ __('URL') }}</label>
                <input type="text" id="mnbUrl" maxlength="600" placeholder="https://example.com">
            </div>
            <div class="mnb-grp" data-when="route">
                <label>{{ __('Route name') }}</label>
                <select id="mnbRoute">
                    @foreach($routeChoices as $rn => $lbl)
                        <option value="{{ $rn }}">{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mnb-grp">
                <label class="mnb-switch" style="font-weight:600;">
                    <input type="checkbox" id="mnbBlank"> <span>{{ __('Open in a new tab') }}</span>
                </label>
            </div>
        </div>
        <div class="mnb-dlg__foot">
            <button type="button" class="mnb-btn mnb-btn--ghost" id="mnbCancel">{{ __('Cancel') }}</button>
            <button type="button" class="mnb-btn mnb-btn--primary" id="mnbSave">{{ __('Save item') }}</button>
        </div>
    </form>
</dialog>

<script nonce="{{ csp_nonce() }}">
(function(){
    var CSRF = '{{ csrf_token() }}';
    var URLs = {
        store:   '{{ route('instructor.web-page.menu.item.store') }}',
        update:  '{{ url('instructor/web-page/menu/items') }}',   // + /{id}
        del:     '{{ url('instructor/web-page/menu/items') }}',   // + /{id}
        reorder: '{{ route('instructor.web-page.menu.reorder') }}',
        toggle:  '{{ route('instructor.web-page.menu.toggle') }}',
    };
    var list   = document.getElementById('mnbList');
    var dialog = document.getElementById('mnbDialog');
    var statusEl = document.getElementById('mnbStatus');

    function flashSaved(){ statusEl.classList.add('show'); setTimeout(function(){ statusEl.classList.remove('show'); }, 2000); }
    function api(url, method, body){
        return fetch(url, {
            method: method,
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body: body ? JSON.stringify(body) : null
        }).then(function(r){ return r.json().catch(function(){return{ok:false};}); });
    }

    // ---- dialog field visibility ----
    var linkType = document.getElementById('mnbLinkType');
    function syncFields(){
        var t = linkType.value;
        dialog.querySelectorAll('[data-when]').forEach(function(el){
            el.style.display = el.getAttribute('data-when').split(' ').indexOf(t) !== -1 ? '' : 'none';
        });
    }
    linkType.addEventListener('change', syncFields);

    function openDialog(item, depth){
        depth = depth || 1;
        document.getElementById('mnbItemId').value = item ? item.id : '';
        document.getElementById('mnbDlgTitle').textContent = item ? '{{ __('Edit menu item') }}' : '{{ __('Add menu item') }}';
        document.getElementById('mnbLabel').value = item ? (item.label||'') : '';
        linkType.value = item ? (item.link_type||'page') : 'page';
        document.getElementById('mnbPage').value = item && item.page_id ? item.page_id : (document.getElementById('mnbPage').options[0]||{}).value;
        document.getElementById('mnbAnchor').value = item ? (item.section_anchor||'') : '';
        document.getElementById('mnbUrl').value = item ? (item.url||'') : '';
        if (item && item.route_name) document.getElementById('mnbRoute').value = item.route_name;
        document.getElementById('mnbBlank').checked = item ? (item.target === '_blank') : false;
        document.getElementById('mnbLayout').value = item ? (item.layout||'dropdown') : 'dropdown';
        // "Mega vs dropdown" only applies to TOP-LEVEL items.
        document.getElementById('mnbLayoutGrp').style.display = (depth === 1) ? '' : 'none';
        syncFields();
        dialog.showModal();
    }
    document.getElementById('mnbAdd').addEventListener('click', function(){ openDialog(null); });
    document.getElementById('mnbCancel').addEventListener('click', function(){ dialog.close(); });

    document.getElementById('mnbSave').addEventListener('click', function(){
        var id = document.getElementById('mnbItemId').value;
        var payload = {
            label:          document.getElementById('mnbLabel').value.trim(),
            link_type:      linkType.value,
            page_id:        (['page','section'].indexOf(linkType.value)!==-1) ? document.getElementById('mnbPage').value : null,
            section_anchor: linkType.value==='section' ? document.getElementById('mnbAnchor').value.trim() : null,
            url:            linkType.value==='url' ? document.getElementById('mnbUrl').value.trim() : null,
            route_name:     linkType.value==='route' ? document.getElementById('mnbRoute').value : null,
            target:         document.getElementById('mnbBlank').checked ? '_blank' : '_self',
            layout:         document.getElementById('mnbLayout').value,
            is_visible:     true
        };
        if (!payload.label){ alert('{{ __('Please enter a label.') }}'); return; }
        var req = id ? api(URLs.update + '/' + id, 'PUT', payload) : api(URLs.store, 'POST', payload);
        req.then(function(d){
            if (d && d.ok){ window.location.reload(); }
            else { alert((d && d.message) || '{{ __('Could not save the item.') }}'); }
        });
    });

    // ---- active toggle ----
    var active = document.getElementById('mnbActive');
    active.addEventListener('change', function(){
        document.getElementById('mnbActiveLabel').textContent = this.checked ? '{{ __('On') }}' : '{{ __('Off') }}';
        api(URLs.toggle, 'POST', {is_active: this.checked}).then(flashSaved);
    });

    // ---- row actions (edit / delete / indent / outdent) ----
    list.addEventListener('click', function(e){
        var btn = e.target.closest('button'); if(!btn) return;
        var row = btn.closest('.mnb-item'); if(!row) return;
        var id = row.getAttribute('data-id');
        if (btn.classList.contains('mnb-edit')){
            openDialog(JSON.parse(row.getAttribute('data-json')), depthOf(row));
        } else if (btn.classList.contains('mnb-del')){
            if (!confirm('{{ __('Delete this menu item?') }}')) return;
            api(URLs.del + '/' + id, 'DELETE').then(function(d){ if(d&&d.ok) window.location.reload(); });
        } else if (btn.classList.contains('mnb-indent')){
            // l1 → l2 → l3 (cap at 3)
            if (!row.classList.contains('is-child')) row.classList.add('is-child');
            else if (!row.classList.contains('is-child-2')) row.classList.add('is-child-2');
            persist();
        } else if (btn.classList.contains('mnb-outdent')){
            // l3 → l2 → l1
            if (row.classList.contains('is-child-2')) row.classList.remove('is-child-2');
            else row.classList.remove('is-child');
            persist();
        }
    });
    function depthOf(row){ return row.classList.contains('is-child-2') ? 3 : (row.classList.contains('is-child') ? 2 : 1); }

    // ---- HTML5 drag reorder (same pattern as the section editor) ----
    var dragEl = null;
    list.addEventListener('dragstart', function(e){
        var row = e.target.closest('.mnb-item'); if(!row) return;
        dragEl = row; row.classList.add('dragging');
    });
    list.addEventListener('dragend', function(){
        if (dragEl){ dragEl.classList.remove('dragging'); dragEl = null; persist(); }
    });
    list.addEventListener('dragover', function(e){
        e.preventDefault();
        if (!dragEl) return;
        var after = getAfter(list, e.clientY);
        if (after == null) list.appendChild(dragEl);
        else list.insertBefore(dragEl, after);
    });
    function getAfter(container, y){
        var rows = [].slice.call(container.querySelectorAll('.mnb-item:not(.dragging)'));
        return rows.reduce(function(closest, child){
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height/2;
            if (offset < 0 && offset > closest.offset) return {offset:offset, element:child};
            return closest;
        }, {offset:-Infinity}).element || null;
    }

    // ---- serialize DOM → 3-level tree (top → column → link) and save ----
    function serialize(){
        var tree = [], curL1 = null, curL2 = null;
        [].forEach.call(list.children, function(row){
            if (!row.classList || !row.classList.contains('mnb-item')) return;
            var lvl = depthOf(row);
            var node = { id: parseInt(row.getAttribute('data-id'),10), children: [] };

            if (lvl === 1 || !curL1){
                // top-level (or an orphan promoted because nothing precedes it)
                row.classList.remove('is-child', 'is-child-2');
                curL1 = node; curL2 = null; tree.push(node);
            } else if (lvl === 2){
                curL1.children.push(node); curL2 = node;
            } else { // lvl 3
                if (curL2){
                    curL2.children.push(node);
                } else {
                    // orphan level-3 with no column above → promote to level-2
                    row.classList.remove('is-child-2');
                    curL1.children.push(node); curL2 = node;
                }
            }
        });
        return tree;
    }
    function persist(){
        api(URLs.reorder, 'POST', { items: serialize() }).then(flashSaved);
    }
})();
</script>
@endsection
