@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    .gft-page{ max-width:880px; margin:0 auto; }
    .gft-card{ background:#fff; border:1px solid #e8eaf3; border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.04); margin-bottom:20px; }
    .gft-card__head{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:20px 22px; border-bottom:1px solid #f0f1f7; }
    .gft-card__title{ font-size:17px; font-weight:700; color:#171a3a; margin:0; }
    .gft-card__sub{ font-size:13px; color:#7a7f9a; margin:4px 0 0; }
    .gft-card__body{ padding:20px 22px; }
    .gft-grp{ margin-bottom:16px; }
    .gft-grp label{ display:block; font-size:13px; font-weight:600; color:#33374d; margin-bottom:6px; }
    .gft-grp input[type=text], .gft-grp textarea{ width:100%; border:1px solid #e2e5f0; border-radius:10px; padding:10px 12px; font-size:14px; }
    .gft-grp input:focus, .gft-grp textarea:focus{ outline:none; border-color:var(--corp-brand,#5b53ff); box-shadow:0 0 0 3px rgba(91,83,255,.13); }
    .gft-hint{ font-size:11.5px; color:#9aa0bc; margin-top:4px; }
    .gft-switch{ display:inline-flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#33374d; font-size:14px; }
    .gft-switch input{ width:42px; height:24px; appearance:none; background:#cfd3e6; border-radius:99px; position:relative; transition:.2s; cursor:pointer; }
    .gft-switch input:checked{ background:var(--corp-brand,#5b53ff); }
    .gft-switch input::after{ content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; background:#fff; border-radius:50%; transition:.2s; }
    .gft-switch input:checked::after{ left:20px; }
    .gft-group{ border:1px solid #ececf4; border-radius:12px; padding:14px; margin-bottom:12px; background:#fafbff; }
    .gft-group__head{ display:flex; gap:10px; align-items:center; margin-bottom:10px; }
    .gft-group__head input{ flex:1; }
    .gft-link-row{ display:grid; grid-template-columns:1fr 1fr auto; gap:8px; margin-bottom:8px; }
    .gft-social-row{ display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .gft-social-ic{ width:34px; height:34px; flex:0 0 34px; border-radius:9px; background:#f1f2f9; display:flex; align-items:center; justify-content:center; color:#5b6079; font-size:15px; }
    .gft-social-row input{ flex:1; border:1px solid #e2e5f0; border-radius:10px; padding:10px 12px; font-size:14px; }
    .gft-social-row input:focus{ outline:none; border-color:var(--corp-brand,#5b53ff); box-shadow:0 0 0 3px rgba(91,83,255,.13); }
    .gft-btn{ border:none; border-radius:9px; padding:9px 16px; font-size:13px; font-weight:600; cursor:pointer; }
    .gft-btn--primary{ background:var(--corp-brand,#5b53ff); color:#fff; }
    .gft-btn--ghost{ background:#eef0f8; color:#444a68; }
    .gft-btn--danger{ background:#fdecec; color:#d23b3b; }
    .gft-btn--sm{ padding:6px 10px; font-size:12px; }
    .gft-foot{ display:flex; gap:12px; align-items:center; padding:16px 22px; border-top:1px solid #f0f1f7; }
    .gft-status{ font-size:13px; color:#16a34a; font-weight:600; opacity:0; transition:.2s; }
    .gft-status.show{ opacity:1; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .gft-card{ background:#1e293b; border-color:#2a3a55; box-shadow:none; }
html[data-theme="dark"] .gft-card__head{ border-bottom-color:#2a3a55; }
html[data-theme="dark"] .gft-card__title{ color:#e2e8f0; }
html[data-theme="dark"] .gft-card__sub{ color:#94a3b8; }
html[data-theme="dark"] .gft-grp label{ color:#e2e8f0; }
html[data-theme="dark"] .gft-grp input[type=text],
html[data-theme="dark"] .gft-grp textarea{ background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
html[data-theme="dark"] .gft-grp input::placeholder,
html[data-theme="dark"] .gft-grp textarea::placeholder{ color:#64748b; }
html[data-theme="dark"] .gft-hint{ color:#94a3b8; }
html[data-theme="dark"] .gft-switch{ color:#e2e8f0; }
html[data-theme="dark"] .gft-switch input:not(:checked){ background:#3a4a63; }
html[data-theme="dark"] .gft-group{ background:#17233a; border-color:#2a3a55; }
html[data-theme="dark"] .gft-social-ic{ background:#22304a; color:#cbd5e1; }
html[data-theme="dark"] .gft-social-row input{ background:#1e293b; border-color:#2a3a55; color:#e2e8f0; }
html[data-theme="dark"] .gft-social-row input::placeholder{ color:#64748b; }
html[data-theme="dark"] .gft-btn--ghost{ background:#22304a; color:#e2e8f0; }
html[data-theme="dark"] .gft-foot{ border-top-color:#2a3a55; }
</style>

<div class="corp-page gft-page">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <h1 style="font-size:22px;font-weight:800;color:#171a3a;margin:0;">{{ __('Global Footer') }}</h1>
            <p style="color:#7a7f9a;font-size:13.5px;margin:6px 0 0;">{{ __('Design your footer once — it appears on every page of your website automatically.') }}</p>
        </div>
        <a href="{{ route('instructor.web-page.index') }}" class="gft-btn gft-btn--ghost"><i class="fas fa-arrow-left"></i> {{ __('Back to website') }}</a>
    </div>

    <form id="gftForm">
        <div class="gft-card">
            <div class="gft-card__head">
                <div>
                    <h2 class="gft-card__title">{{ __('Footer visibility') }}</h2>
                    <p class="gft-card__sub">{{ __('Turn the global footer on or off across your whole site.') }}
                        @if($optedOut > 0)
                            <br><span style="color:#c2820a;">{{ trans_choice('{1} :n page has opted out of the global footer.|[2,*] :n pages have opted out of the global footer.', $optedOut, ['n' => $optedOut]) }}</span>
                        @endif
                    </p>
                </div>
                <label class="gft-switch">
                    <input type="checkbox" id="gftEnabled" {{ ($footer->is_enabled ?? true) ? 'checked' : '' }}>
                    <span id="gftEnabledLabel">{{ ($footer->is_enabled ?? true) ? __('Enabled') : __('Disabled') }}</span>
                </label>
            </div>
        </div>

        <div class="gft-card">
            <div class="gft-card__head">
                <div>
                    <h2 class="gft-card__title">{{ __('Footer content') }}</h2>
                    <p class="gft-card__sub">{{ __('Tagline, copyright and link columns. Your logo, brand color and social links come from your site settings.') }}</p>
                </div>
            </div>
            <div class="gft-card__body">
                <div class="gft-grp">
                    <label>{{ __('Tagline') }}</label>
                    <input type="text" id="gftTagline" maxlength="200" placeholder="{{ __('A short line under your logo') }}" value="{{ $content['tagline'] ?? '' }}">
                </div>
                <div class="gft-grp">
                    <label>{{ __('Copyright text') }}</label>
                    <input type="text" id="gftCopyright" maxlength="200" placeholder="{{ __('All rights reserved.') }}" value="{{ $content['copyright'] ?? 'All rights reserved.' }}">
                    <p class="gft-hint">{{ __('Shown after "© :year :brand."', ['year' => date('Y'), 'brand' => __('Your brand')]) }}</p>
                </div>
                <div class="gft-grp">
                    <label class="gft-switch">
                        <input type="checkbox" id="gftShowSocial" {{ ($content['show_social'] ?? true) ? 'checked' : '' }}>
                        <span>{{ __('Show social media icons') }}</span>
                    </label>
                    <p class="gft-hint">{{ __('Show or hide the social icons in your footer. Add the links below.') }}</p>
                </div>

                <div class="gft-grp" id="gftSocial">
                    <label>{{ __('Social media links') }}</label>
                    <p class="gft-hint" style="margin:0 0 10px;">{{ __('Add a URL for each platform you use. Only platforms with a link appear in your footer, and every link opens in a new tab.') }}</p>
                    @php
                        $socialFields = [
                            'facebook'  => ['Facebook',    'fa-brands fa-facebook-f',  'https://facebook.com/yourpage'],
                            'instagram' => ['Instagram',   'fa-brands fa-instagram',   'https://instagram.com/yourhandle'],
                            'youtube'   => ['YouTube',     'fa-brands fa-youtube',     'https://youtube.com/@yourchannel'],
                            'twitter'   => ['Twitter / X', 'fa-brands fa-x-twitter',   'https://x.com/yourhandle'],
                            'linkedin'  => ['LinkedIn',    'fa-brands fa-linkedin-in', 'https://linkedin.com/in/you'],
                            'tiktok'    => ['TikTok',      'fa-brands fa-tiktok',      'https://tiktok.com/@yourhandle'],
                            'pinterest' => ['Pinterest',   'fa-brands fa-pinterest-p', 'https://pinterest.com/yourprofile'],
                        ];
                    @endphp
                    @foreach($socialFields as $key => $meta)
                        <div class="gft-social-row">
                            <span class="gft-social-ic" title="{{ $meta[0] }}"><i class="{{ $meta[1] }}"></i></span>
                            <input type="text" data-social="{{ $key }}" maxlength="500" aria-label="{{ $meta[0] }} URL" placeholder="{{ $meta[2] }}" value="{{ $social[$key] ?? '' }}">
                        </div>
                    @endforeach
                </div>

                <div class="gft-grp">
                    <label>{{ __('Link columns') }} <span style="font-weight:400;color:#9aa0bc;">({{ __('up to 4') }})</span></label>
                    <div id="gftGroups"></div>
                    <button type="button" class="gft-btn gft-btn--ghost gft-btn--sm" id="gftAddGroup"><i class="fas fa-plus"></i> {{ __('Add column') }}</button>
                </div>
            </div>
            <div class="gft-foot">
                <button type="submit" class="gft-btn gft-btn--primary"><i class="fas fa-save"></i> {{ __('Save footer') }}</button>
                <span class="gft-status" id="gftStatus"><i class="fas fa-check-circle"></i> {{ __('Saved — live on every page.') }}</span>
            </div>
        </div>
    </form>
</div>

<script nonce="{{ csp_nonce() }}">
(function(){
    var groups = @json($content['link_groups'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!Array.isArray(groups)) groups = [];
    var wrap = document.getElementById('gftGroups');
    var MAX_GROUPS = 4, MAX_LINKS = 8;

    function esc(s){ return (s==null?'':String(s)).replace(/"/g,'&quot;'); }

    function linkRow(l){
        l = l || {};
        return '<div class="gft-link-row">'
            + '<input type="text" class="gft-l-label" maxlength="60" placeholder="{{ __('Label') }}" value="'+esc(l.label)+'">'
            + '<input type="text" class="gft-l-url" maxlength="500" placeholder="https://… {{ __('or') }} /about" value="'+esc(l.url)+'">'
            + '<button type="button" class="gft-btn gft-btn--danger gft-btn--sm gft-l-del" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>'
            + '</div>';
    }

    function groupBlock(g){
        g = g || {};
        var links = Array.isArray(g.links) ? g.links : [];
        var html = '<div class="gft-group">'
            + '<div class="gft-group__head">'
            + '<input type="text" class="gft-g-title" maxlength="60" placeholder="{{ __('Column title (e.g. Company)') }}" value="'+esc(g.title)+'">'
            + '<button type="button" class="gft-btn gft-btn--danger gft-btn--sm gft-g-del"><i class="fas fa-trash"></i></button>'
            + '</div><div class="gft-links">';
        links.forEach(function(l){ html += linkRow(l); });
        html += '</div><button type="button" class="gft-btn gft-btn--ghost gft-btn--sm gft-add-link"><i class="fas fa-plus"></i> {{ __('Add link') }}</button></div>';
        return html;
    }

    function render(){
        wrap.innerHTML = '';
        groups.forEach(function(g){ wrap.insertAdjacentHTML('beforeend', groupBlock(g)); });
        document.getElementById('gftAddGroup').style.display = (wrap.children.length >= MAX_GROUPS) ? 'none' : '';
    }

    // Event delegation for all dynamic controls
    wrap.addEventListener('click', function(e){
        var t = e.target.closest('button'); if(!t) return;
        var groupEl = t.closest('.gft-group');
        if (t.classList.contains('gft-g-del')) { groupEl.remove(); document.getElementById('gftAddGroup').style.display=''; }
        else if (t.classList.contains('gft-add-link')) {
            var box = groupEl.querySelector('.gft-links');
            if (box.children.length < MAX_LINKS) box.insertAdjacentHTML('beforeend', linkRow({}));
        }
        else if (t.classList.contains('gft-l-del')) { t.closest('.gft-link-row').remove(); }
    });
    document.getElementById('gftAddGroup').addEventListener('click', function(){
        if (wrap.children.length >= MAX_GROUPS) return;
        wrap.insertAdjacentHTML('beforeend', groupBlock({title:'', links:[{}]}));
        if (wrap.children.length >= MAX_GROUPS) this.style.display='none';
    });

    function collect(){
        var out = [];
        wrap.querySelectorAll('.gft-group').forEach(function(gEl){
            var title = gEl.querySelector('.gft-g-title').value.trim();
            var links = [];
            gEl.querySelectorAll('.gft-link-row').forEach(function(rEl){
                var label = rEl.querySelector('.gft-l-label').value.trim();
                var url   = rEl.querySelector('.gft-l-url').value.trim();
                if (label && url) links.push({label:label, url:url});
            });
            if (title || links.length) out.push({title:title, links:links});
        });
        return out;
    }

    var enabled = document.getElementById('gftEnabled');
    enabled.addEventListener('change', function(){
        document.getElementById('gftEnabledLabel').textContent = this.checked ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}';
    });

    document.getElementById('gftForm').addEventListener('submit', function(e){
        e.preventDefault();
        var social = {};
        document.querySelectorAll('#gftSocial input[data-social]').forEach(function(i){
            social[i.getAttribute('data-social')] = i.value.trim();
        });
        var payload = {
            is_enabled: enabled.checked,
            content: {
                tagline:     document.getElementById('gftTagline').value.trim(),
                copyright:   document.getElementById('gftCopyright').value.trim() || 'All rights reserved.',
                show_social: document.getElementById('gftShowSocial').checked,
                link_groups: collect()
            },
            social: social
        };
        var btn = this.querySelector('button[type=submit]'); btn.disabled = true;
        fetch('{{ route('instructor.web-page.footer.update') }}', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
            body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json().catch(function(){return{};}); })
        .then(function(d){
            if (d && d.ok) { var s=document.getElementById('gftStatus'); s.classList.add('show'); setTimeout(function(){s.classList.remove('show');},2500); }
            else { alert((d && d.errors) ? Object.values(d.errors).join('\n') : '{{ __('Could not save. Please check your inputs.') }}'); }
        })
        .catch(function(){ alert('{{ __('Network error. Please try again.') }}'); })
        .finally(function(){ btn.disabled = false; });
    });

    render();
})();
</script>
@endsection
