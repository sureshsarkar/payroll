{{-- Lead Form v1 — posts to /coach/landing-form with page_id + section_id --}}
@php
    $c = $content;
    $fields = $c['fields'] ?? [];
@endphp
<section class="cs-leadform cs-pad" id="contact" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container cs-container--narrow">
        <div class="cs-section-head">
            <h2 class="cs-h2">{{ $c['title'] ?? 'Get in touch' }}</h2>
            @if(!empty($c['intro']))
                <p class="cs-lead">{{ $c['intro'] }}</p>
            @endif
        </div>

        <form class="cs-form" method="POST" action="{{ route('publish-landing-page.submit') }}" data-form="lead">
            @csrf
            <input type="hidden" name="page_id" value="{{ $page->id ?? '' }}">
            <input type="hidden" name="section_id" value="{{ $sectionId ?? '' }}">
            {{-- 2026-06-17 — use ?: (not ??) so a BLANK service_label still falls
                 back to a default. With ??, an empty-string label submitted
                 service="" and the server rejected it ("Service is required"),
                 silently losing the lead. trim() guards whitespace-only labels. --}}
            <input type="hidden" name="service" value="{{ trim((string)($c['service_label'] ?? '')) ?: 'General enquiry' }}">

            <div class="cs-form__grid">
                @foreach($fields as $f)
                    @php
                        $key = $f['key'] ?? '';
                        $label = $f['label'] ?? $key;
                        $type = $f['type'] ?? 'text';
                        $required = !empty($f['required']);
                        // Map common keys to the legacy CRM columns so existing
                        // LandingPageEnquiry create() picks them up.
                        $name = in_array($key, ['first_name','last_name','email','phone','message','service'])
                            ? $key
                            : "custom_fields[{$key}]";
                    @endphp
                    <div class="cs-field cs-field--{{ $type }}">
                        <label class="cs-label" for="cs-{{ $key }}">{{ $label }}@if($required) <span class="cs-req">*</span>@endif</label>
                        @if($type === 'textarea')
                            <textarea id="cs-{{ $key }}" name="{{ $name }}" rows="4" @if($required) required @endif></textarea>
                        @elseif($type === 'select')
                            <select id="cs-{{ $key }}" name="{{ $name }}" @if($required) required @endif>
                                <option value="">{{ __('Select…') }}</option>
                                @foreach(($f['options'] ?? []) as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="cs-{{ $key }}" type="{{ $type }}" name="{{ $name }}" @if($required) required @endif>
                        @endif
                    </div>
                @endforeach
            </div>

            <button type="submit" class="cs-btn cs-btn--primary cs-btn--lg">{{ $c['submit_text'] ?? 'Send message' }}</button>
            <p class="cs-form__success" data-success>{{ $c['success_text'] ?? 'Thanks — we will reach out shortly.' }}</p>
        </form>
    </div>
</section>
