{{-- Recorded Courses v1 — two source modes:
       (a) auto-from-courses  → coach's Course rows where type=recorded
                                (falls back to ALL coach's courses if none flagged recorded)
       (b) from-youtube       → coach's YouTube channel (24h cached via YouTubeFetcher)
     Both modes render a 60-second preview player with hard-stop + buy overlay.
--}}
@php
    $c = $content;
    $cols     = (int) ($c['columns']         ?? 3);
    $previewS = (int) ($c['preview_seconds'] ?? 60);
    $limit    = (int) ($c['limit']           ?? 6);
    $cta      = $c['cta_text']               ?? 'Add to cart';
    $source   = $c['source']                 ?? 'auto-from-courses';

    // Resolve coach slug for the cart drawer's checkout / view-cart URLs.
    // Without this, the JS dynamically injects href="/checkout" (platform)
    // which would dump the student off the coach's branded site.
    $sectionCoachSlug = null;
    if (isset($coach) && !empty($coach->id)) {
        $sectionCoachSlug = \App\Models\CoachLandingPage::where('added_by', $coach->id)->value('slug');
    }
    // 2026-06-10 — clean root urls (/cart, /checkout) on a coach domain; the
    // /coach/{slug}/... path urls only on the path surface.
    $coachCartUrl     = coachCommerceUrl('cart', $sectionCoachSlug);
    $coachCheckoutUrl = coachCommerceUrl('checkout', $sectionCoachSlug);

    // Card list — normalised structure regardless of source mode:
    //   id, title, thumbnail, price, rating, students,
    //   preview_url, preview_kind ('mp4' | 'youtube' | 'vimeo'),
    //   buy_url
    $cards = collect();

    // Authoritative list of course ids ALREADY in this student's cart, so
    // we can render "In cart ✓" on those buttons instead of "Add to cart"
    // — and only on those buttons, not on every card.
    $cartCourseIds = [];
    try {
        if (auth()->check()) {
            $u = userAuth();
            if ($u && method_exists($u, 'carts')) {
                $cartCourseIds = $u->carts()->pluck('course_id')->map(fn ($x) => (int) $x)->all();
            }
        } else {
            // Anonymous visitor: Cart::content() is keyed by row id; each
            // row's ->id holds the course id we passed at addToCart time.
            $cartCourseIds = \Gloudemans\Shoppingcart\Facades\Cart::content()
                ->pluck('id')
                ->map(fn ($x) => (int) $x)
                ->all();
        }
    } catch (\Throwable $e) {
        // Cart driver not configured in some test envs — fail safe to empty.
        $cartCourseIds = [];
    }

    // Smart preflight: when the coach has set a YouTube channel ID,
    // treat that as an explicit intent to show YouTube videos — even if
    // source is still on the default "auto-from-courses". The linked
    // course (auto-provisioned in CoachSiteController::addSection when
    // they added this section) becomes the Add-to-Cart target. So the
    // student sees: rich YouTube video grid → 60s preview → lock →
    // "Add to Cart" → bundle course → student dashboard. That's the
    // full purchase journey, working out of the box.
    //
    // Coach can revert to course-grid mode by clearing the channel ID
    // (or setting source explicitly to "auto-from-courses" with no
    // channel ID).
    if ($source === 'auto-from-courses' && ! empty($coach) && ! empty($c['youtube_channel_id'])) {
        $source = 'from-youtube';
    }

    // ── Mode A: From coach's courses ────────────────────────────────
    if ($source === 'auto-from-courses' && ! empty($coach)) {
        // SECURITY (2026-06-01) — this is a PUBLIC white-label section, so it
        // must only show sellable courses: approved + active. Without these
        // filters a coach's draft/pending/rejected courses leaked onto their
        // public site. Mirror Course::scopeActive() (is_approved=approved +
        // status=active).
        // 2026-06-12 — a "Recorded Courses" section must ONLY show SELF-PACED
        // courses (type 'recorded' or legacy 'course'). It must NEVER show
        // live/hybrid courses: those require a batch, so adding them here hit
        // CartController's "Please select a batch" guard. The fallback used to
        // load ALL coach courses (incl. live/hybrid) when none were typed
        // exactly 'recorded' — that was the bug. Both queries now constrain to
        // the non-batch types. Global for every coach.
        // 2026-06-15 — the section can now list EITHER catalogue:
        //   course_type='live'       → courses that run Live Classes (live + hybrid)
        //   course_type='self_paced' → recorded / on-demand (no batch) — the default
        $courseType = $c['course_type'] ?? 'self_paced';
        $isLiveMode  = $courseType === 'live';

        $base = fn () => \App\Models\Course::query()
            ->where('instructor_id', $coach->id)
            ->where('coach_soft_delete', 0)
            ->where('is_approved', 'approved')
            ->where('status', 'active')
            ->orderByDesc('id')
            ->limit($limit);

        if ($isLiveMode) {
            // Live-class courses. These need a batch, so their card CTA links to
            // the course page (batch picker) instead of a direct add-to-cart.
            $courses = $base()->whereIn('type', ['live', 'hybrid'])->get();
        } else {
            $selfPacedTypes = ['recorded', 'course'];
            $courses = $base()->where('type', 'recorded')->get();
            if ($courses->isEmpty()) {
                $courses = $base()->whereIn('type', $selfPacedTypes)->get(); // exclude live/hybrid (batch-required)
            }
        }
        foreach ($courses as $course) {
            $price = ($course->discount ?? 0) > 0 ? $course->discount : $course->price;
            $thumb = $course->thumbnail
                ? (str_starts_with($course->thumbnail, 'http') ? $course->thumbnail : asset($course->thumbnail))
                : asset('uploads/website-images/placeholder.jpg');

            $previewUrl = null; $previewKind = null;
            if (! empty($course->demo_video_source)) {
                $src = (string) $course->demo_video_source;
                $storage = $course->demo_video_storage ?? 'upload';
                if ($storage === 'youtube' || str_contains($src, 'youtu')) {
                    preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_\-]{11})/', $src, $m);
                    if (!empty($m[1])) { $previewUrl = $m[1]; $previewKind = 'youtube'; }
                } elseif ($storage === 'vimeo' || str_contains($src, 'vimeo')) {
                    preg_match('/vimeo\.com\/(\d+)/', $src, $m);
                    if (!empty($m[1])) { $previewUrl = $m[1]; $previewKind = 'vimeo'; }
                } else {
                    $previewUrl = str_starts_with($src, 'http') ? $src : asset($src);
                    $previewKind = 'mp4';
                }
            }

            $buyUrl = url('/course/' . $course->slug)
                . '?ref=coach_site&utm_source=coach_site'
                . '&utm_medium=' . urlencode($page->slug ?? 'home')
                . '&utm_campaign=' . ($sectionId ?? '0');

            $cards->push((object) [
                'id'           => $course->id,
                'video_id'     => null,
                'title'        => $course->title,
                'thumbnail'    => $thumb,
                'price'        => $price,
                'rating'       => (float) ($course->rating ?? 0),
                'students'     => $course->enrollments_count ?? null,
                'preview_url'  => $previewUrl,
                'preview_kind' => $previewKind,
                'buy_url'      => $buyUrl,
                'add_to_cart'  => url('/add-to-cart/' . $course->id) . '?ref=coach_site',
                'contact_url'  => url('/') . '#contact',
                'mode'         => 'course',
                'has_course'   => true,
                'is_live'      => $isLiveMode,   // live/hybrid → CTA goes to the course page (batch picker)
                'course_title' => null,
            ]);
        }
    }

    // ── Mode B: From coach's YouTube channel ────────────────────────
    $youtubeDiagnostic = null;
    if ($source === 'from-youtube') {
        $channelId = $c['youtube_channel_id'] ?? null;
        try {
            $renderer = app(\App\Services\Site\SectionRenderer::class);
            $videos = $renderer->resolveYouTube($channelId, $coach);
            $youtubeDiagnostic = $renderer->youtubeDiagnostic($channelId, $coach, $videos);
        } catch (\Throwable $e) { $videos = []; $youtubeDiagnostic = $e->getMessage(); }

        // Resolution strategy:
        //   - linked_course_slug set explicitly → "bundle mode": every card
        //     adds the same course (good for coaches selling one package).
        //   - linked_course_slug empty (the default) → "per-video mode":
        //     each YouTube card gets its own auto-provisioned Course so a
        //     student can buy any specific video independently.
        $bundleCourse  = null;
        $contactAnchor = url('/') . '#contact';

        if (! empty($coach)) {
            $bundleSlug = trim((string) ($c['linked_course_slug'] ?? ''));
            if ($bundleSlug !== '') {
                $bundleCourse = \App\Models\Course::query()
                    ->where('instructor_id', $coach->id)
                    ->where('slug', $bundleSlug)
                    ->where('coach_soft_delete', 0)
                    ->first();
            }
        }

        // Build a card per video. ensureForVideo is idempotent (deterministic
        // slug per video id) so re-renders never duplicate courses.
        $bootstrap = ! empty($coach)
            ? app(\App\Services\Site\CoachCourseBootstrap::class)
            : null;

        foreach (array_slice($videos, 0, $limit) as $v) {
            $videoCourse = $bundleCourse;
            if ($videoCourse === null && $bootstrap !== null) {
                try {
                    $videoCourse = $bootstrap->ensureForVideo(
                        $coach,
                        $v['id'],
                        $v['title'] ?? ('Video ' . $v['id']),
                        $v['thumbnail'] ?? ''
                    );
                } catch (\Throwable $e) {
                    // Provisioning failed (DB issue?) — leave card as
                    // Contact-fallback rather than crashing the page.
                    \Log::warning('video-course-provision-failed', [
                        'coach_id' => $coach->id,
                        'video_id' => $v['id'],
                        'error'    => $e->getMessage(),
                    ]);
                    $videoCourse = null;
                }
            }

            $price = null;
            $cartUrl = null;
            $buyUrl  = null;
            if ($videoCourse) {
                $price = ($videoCourse->discount ?? 0) > 0
                    ? $videoCourse->discount
                    : $videoCourse->price;
                $buyUrl  = url('/course/' . $videoCourse->slug)
                    . '?ref=coach_site&utm_source=coach_site'
                    . '&utm_medium=' . urlencode($page->slug ?? 'home')
                    . '&utm_campaign=' . ($sectionId ?? '0');
                $cartUrl = url('/add-to-cart/' . $videoCourse->id) . '?ref=coach_site';
            }

            $cards->push((object) [
                'id'              => $videoCourse?->id,
                'video_id'        => $v['id'],
                'title'           => $v['title'] ?? '',
                'thumbnail'       => $v['thumbnail'] ?? '',
                'price'           => $price,
                'rating'          => 0,
                'students'        => null,
                'preview_url'     => $v['id'],
                'preview_kind'    => 'youtube',
                'buy_url'         => $buyUrl,
                'add_to_cart'     => $cartUrl,
                'contact_url'     => $contactAnchor,
                'mode'            => 'youtube',
                'has_course'      => $videoCourse !== null,
                // In bundle mode every card shows the bundle title under
                // "Unlocks:"; in per-video mode we hide it because each
                // card's title IS the video title.
                'course_title'    => $bundleCourse?->title,
            ]);
        }
    }
@endphp
<section class="cs-rec cs-pad" id="recorded-courses" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        <div class="cs-section-head">
            <h2 class="cs-h2">{{ $c['title'] ?? (($c['course_type'] ?? 'self_paced') === 'live' ? 'Live Class Sessions' : 'On-demand courses') }}</h2>
            @if(!empty($c['intro']))
                <p class="cs-lead">{{ $c['intro'] }}</p>
            @endif
        </div>

        @if($cards->isEmpty())
            <div class="cs-empty">
                @if($source === 'from-youtube')
                    {{ __('No YouTube videos found. Make sure your YouTube channel is connected in your coach profile.') }}
                @else
                    {{ __('No on-demand courses available yet.') }}
                @endif
            </div>
            @if(($isOwnerPreview ?? false))
                <div class="cs-empty cs-empty--owner-hint" style="margin-top:10px;padding:10px 14px;border-left:3px solid #f0ad4e;background:#fff8e6;color:#664400;font-size:13px;text-align:left;max-width:760px;margin-left:auto;margin-right:auto;line-height:1.5;">
                    @if($source === 'auto-from-courses')
                        <strong>{{ __('Owner tip:') }}</strong>
                        {{ __('This section is set to pull from your courses, not YouTube. To show YouTube videos here, change "Where to pull videos from" to "from-youtube" in the section editor.') }}
                    @elseif(!empty($youtubeDiagnostic))
                        <strong>{{ __('Why is this empty?') }}</strong>
                        {{ $youtubeDiagnostic }}
                    @endif
                </div>
            @endif
        @else
            <div class="cs-grid cs-grid--{{ $cols }} cs-rec__grid">
                @foreach($cards as $card)
                    <article class="cs-rec__card" data-course-id="{{ $card->id ?? '' }}" data-preview-seconds="{{ $previewS }}">
                        <div class="cs-rec__media">
                            {{-- Visible "free preview" chip — appears on every card so the
                                 60s rule is obvious before the user clicks anything. --}}
                            <span class="cs-rec__preview-chip">
                                <i class="fa-solid fa-clock"></i> {{ $previewS }}s {{ __('free preview') }}
                            </span>

                            @if($card->preview_kind === 'mp4' && $card->preview_url)
                                <video class="cs-rec__video"
                                       data-preview-player
                                       poster="{{ $card->thumbnail }}"
                                       preload="metadata"
                                       playsinline muted>
                                    <source src="{{ $card->preview_url }}" type="video/mp4">
                                </video>
                            @elseif($card->preview_kind === 'youtube' && $card->preview_url)
                                {{-- Embed-only YouTube container. The actual iframe is injected
                                     by JS using youtube-nocookie.com + sandbox attributes so
                                     the user CANNOT navigate away to youtube.com. --}}
                                <div class="cs-rec__video cs-rec__video--yt"
                                     data-yt-id="{{ $card->preview_url }}"
                                     data-preview-player>
                                    <img src="{{ $card->thumbnail }}" alt="" class="cs-rec__poster">
                                </div>
                            @elseif($card->preview_kind === 'vimeo' && $card->preview_url)
                                <div class="cs-rec__video cs-rec__video--vimeo"
                                     data-vimeo-id="{{ $card->preview_url }}"
                                     data-preview-player>
                                    <img src="{{ $card->thumbnail }}" alt="" class="cs-rec__poster">
                                </div>
                            @else
                                {{-- No preview source — the thumbnail is just decorative,
                                     never a link off-platform. --}}
                                <div class="cs-rec__thumb-link">
                                    <img src="{{ $card->thumbnail }}" alt="{{ $card->title }}" loading="lazy">
                                </div>
                            @endif

                            @if($card->preview_url)
                                <button type="button" class="cs-rec__play" aria-label="{{ __('Play preview') }}" data-play-trigger>
                                    <i class="fa-solid fa-play"></i>
                                    <span>{{ __('Preview') }}</span>
                                </button>

                                <div class="cs-rec__overlay" data-buy-overlay hidden>
                                    <div class="cs-rec__overlay-content">
                                        <i class="fa-solid fa-lock cs-rec__overlay-ic"></i>
                                        <h3>{{ __('Preview ended') }}</h3>
                                        <p>
                                            @if($card->has_course)
                                                {{ __('Buy this course to continue watching the full content.') }}
                                            @else
                                                {{ __('Get in touch to unlock full access to this content.') }}
                                            @endif
                                        </p>

                                        @if($card->has_course && !empty($card->is_live))
                                            {{-- live-class course → batch picker on the course page --}}
                                            <a href="{{ $card->buy_url }}" class="cs-btn cs-btn--primary">
                                                <i class="fa-solid fa-calendar-check"></i>
                                                <span class="cs-btn__label">{{ __('View & enroll') }}</span>
                                            </a>
                                        @elseif($card->has_course)
                                            @php $inCart = in_array((int) $card->id, $cartCourseIds, true); @endphp
                                            @if($inCart)
                                                <a href="{{ $coachCartUrl }}" class="cs-btn cs-btn--primary">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    <span class="cs-btn__label">{{ __('In cart — view') }}</span>
                                                </a>
                                            @else
                                                <button type="button"
                                                        class="cs-btn cs-btn--primary"
                                                        data-cs-cart
                                                        data-course-id="{{ $card->id }}">
                                                    <i class="fa-solid fa-cart-shopping"></i>
                                                    <span class="cs-btn__label">{{ $cta }}@if($card->price && $card->price > 0) — ₹{{ number_format($card->price, 0) }}@endif</span>
                                                </button>
                                            @endif
                                        @else
                                            <a href="{{ $card->contact_url }}" class="cs-btn cs-btn--primary">
                                                <i class="fa-solid fa-envelope"></i>
                                                {{ __('Contact for full access') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="cs-rec__body">
                            <h3 class="cs-rec__title">{{ $card->title }}</h3>

                            @if($card->mode === 'course')
                                <div class="cs-rec__meta">
                                    @if(!empty($c['show_rating']) && $card->rating > 0)
                                        <span class="cs-rec__rating">
                                            <i class="fa-solid fa-star"></i> {{ number_format($card->rating, 1) }}
                                        </span>
                                    @endif
                                    @if(!empty($c['show_enrollment']) && $card->students !== null)
                                        <span class="cs-rec__students">
                                            <i class="fa-solid fa-users"></i> {{ $card->students }} {{ __('students') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="cs-rec__foot">
                                    <span class="cs-rec__price">{{ $card->price > 0 ? '₹' . number_format($card->price, 0) : __('Free') }}</span>
                                    @if(!empty($card->is_live))
                                        {{-- 2026-06-15 — live-class course: a batch must be chosen, so
                                             send the student to the course page (batch picker) rather than
                                             a direct add-to-cart (which would 422 "Please select a batch"). --}}
                                        <a href="{{ $card->buy_url }}" class="cs-btn cs-btn--primary cs-btn--sm">
                                            <i class="fa-solid fa-calendar-check"></i>
                                            <span class="cs-btn__label">{{ __('View & enroll') }}</span>
                                        </a>
                                    @else
                                        @php $inCart = in_array((int) $card->id, $cartCourseIds, true); @endphp
                                        @if($inCart)
                                            <a href="{{ $coachCartUrl }}" class="cs-btn cs-btn--primary cs-btn--sm">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <span class="cs-btn__label">{{ __('In cart') }}</span>
                                            </a>
                                        @else
                                            <button type="button"
                                                    class="cs-btn cs-btn--primary cs-btn--sm"
                                                    data-cs-cart
                                                    data-course-id="{{ $card->id }}">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                                <span class="cs-btn__label">{{ $cta }}</span>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @else
                                {{-- YouTube-mode card foot. No "YouTube" branding leaks
                                     anywhere — students see the coach's storefront only. --}}
                                @if(!empty($card->course_title))
                                    <p class="cs-rec__yt-course">
                                        <i class="fa-solid fa-graduation-cap"></i>
                                        {{ __('Unlocks') }}: <strong>{{ $card->course_title }}</strong>
                                    </p>
                                @endif
                                <div class="cs-rec__foot">
                                    @if($card->price !== null && $card->price > 0)
                                        <span class="cs-rec__price">₹{{ number_format($card->price, 0) }}</span>
                                    @else
                                        <span class="cs-rec__price cs-rec__price--muted">{{ __('Free preview') }}</span>
                                    @endif

                                    @if($card->has_course)
                                        @php $inCart = in_array((int) $card->id, $cartCourseIds, true); @endphp
                                        @if($inCart)
                                            <a href="{{ $coachCartUrl }}" class="cs-btn cs-btn--primary cs-btn--sm">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <span class="cs-btn__label">{{ __('In cart') }}</span>
                                            </a>
                                        @else
                                            <button type="button"
                                                    class="cs-btn cs-btn--primary cs-btn--sm"
                                                    data-cs-cart
                                                    data-course-id="{{ $card->id }}">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                                <span class="cs-btn__label">{{ $cta }}</span>
                                            </button>
                                        @endif
                                    @else
                                        <a href="{{ $card->contact_url }}" class="cs-btn cs-btn--primary cs-btn--sm">
                                            <i class="fa-solid fa-envelope"></i>
                                            {{ __('Contact') }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
{{-- Inline <script>, NOT @push('scripts'). SectionRenderer renders each
     section in isolation via view()->render(), which causes Laravel to
     flush the view factory's push stack between sections. Anything pushed
     to 'scripts' from inside a section partial NEVER reaches the master
     layout's @stack('scripts'). Emitting the script directly in the
     section's HTML guarantees the browser parses + executes it as soon as
     the section appears in the DOM. --}}
{{-- nonce REQUIRED: the coach-site CSP script-src carries a per-request
     'nonce-…'; once a nonce is present browsers IGNORE 'unsafe-inline', so
     under CSP_ENFORCE this script (and its add-to-cart click handler) is
     blocked without a matching nonce — the add then only reflects after a
     full page refresh. Global for every coach. --}}
<script nonce="{{ csp_nonce() }}">
/* ─────────────────────────────────────────────────────────────────────────
 * Recorded Courses v1 — hard-stop preview player + in-place Add to Cart.
 *
 *   Preview:  user clicks the play overlay → player loads inside the card,
 *             muted by default. At preview_seconds we force-pause AND
 *             force-clear the iframe src as a belt-and-suspenders measure
 *             so a slow IFrame-API event can never let the video keep
 *             playing. Buy overlay then animates in.
 *
 *   Cart:     [data-cs-cart] buttons POST to /add-to-cart/{course_id} with
 *             the CSRF meta token. Updates .mini-cart-count on success,
 *             shows an in-page toast for the result. Zero jQuery / toastr
 *             dependency — coach-site layout doesn't load them.
 *
 *   YouTube:  embedded via youtube-nocookie.com with sandbox attributes
 *             that block top-level navigation, so the in-player YouTube
 *             logo can't navigate the student away. modestbranding/rel=0
 *             keep related-video drawers from leaking off-brand titles.
 * ──────────────────────────────────────────────────────────────────────── */
(function() {
    if (window.__csRecBound) return; window.__csRecBound = true;

    // ── 1. Preview player bindings ─────────────────────────────────────
    document.querySelectorAll('.cs-rec__card').forEach(card => {
        const seconds = parseInt(card.dataset.previewSeconds, 10) || 60;
        const playBtn = card.querySelector('[data-play-trigger]');
        const overlay = card.querySelector('[data-buy-overlay]');
        const player  = card.querySelector('[data-preview-player]');
        if (!playBtn || !player) return;
        playBtn.addEventListener('click', function() {
            playBtn.style.display = 'none';
            startPreview(player, overlay, seconds);
        });
    });

    function startPreview(el, overlay, seconds) {
        if (el.tagName === 'VIDEO') return startHtml5(el, overlay, seconds);
        if (el.dataset.ytId)         return startYouTube(el, overlay, seconds);
        if (el.dataset.vimeoId)      return startVimeo(el, overlay, seconds);
    }

    function lockOverlay(overlay, killer) {
        if (typeof killer === 'function') { try { killer(); } catch (_) {} }
        if (overlay) {
            overlay.hidden = false;
            overlay.classList.add('cs-rec__overlay--show');
        }
    }

    function startHtml5(video, overlay, seconds) {
        video.controls = true; video.style.display = 'block';
        const stop = () => {
            video.pause();
            video.removeAttribute('src');
            video.load();
        };
        const watcher = () => {
            if (video.currentTime >= seconds) {
                video.removeEventListener('timeupdate', watcher);
                lockOverlay(overlay, stop);
            }
        };
        video.addEventListener('timeupdate', watcher);
        video.addEventListener('seeking', () => { if (video.currentTime > seconds) video.currentTime = seconds; });
        video.play().catch(() => {});
        // Wall-clock safety net — pause even if the timeupdate event stalls
        setTimeout(() => lockOverlay(overlay, stop), (seconds + 1) * 1000);
    }

    function startYouTube(wrap, overlay, seconds) {
        const ytId = wrap.dataset.ytId;
        // Visible "Loading…" so the user knows their click was received,
        // even before the iframe paints. Hidden once iframe fires onload.
        wrap.innerHTML = '<div class="cs-rec__player-loading">' +
                         '<i class="fa-solid fa-circle-notch fa-spin"></i> ' +
                         '<span>{{ __('Loading preview…') }}</span></div>';

        // youtube-nocookie embed; modestbranding + rel=0 + iv_load_policy=3
        // suppress related-videos and annotations. NO sandbox attribute on
        // this iframe — earlier testing showed strict sandboxing blocks the
        // postMessage channel the API needs to obey our pauseVideo command
        // on some browsers. The nocookie domain + referrerpolicy already
        // give us the privacy properties we wanted. We still nuke the
        // iframe src at lock-time so navigation away is impossible.
        const params = 'autoplay=1&mute=1&controls=1&rel=0&modestbranding=1&playsinline=1&iv_load_policy=3&disablekb=1&fs=0&enablejsapi=1&origin=' + encodeURIComponent(window.location.origin);
        const src = 'https://www.youtube-nocookie.com/embed/' + ytId + '?' + params;

        const iframe = document.createElement('iframe');
        iframe.className = 'cs-rec__iframe';
        iframe.src = src;
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.setAttribute('allowfullscreen', 'false');

        // Show the iframe + remove loader once the iframe has actually
        // loaded a response. If it never loads (e.g. blocked), show the
        // fallback after 6 seconds.
        let loaded = false;
        iframe.addEventListener('load', () => {
            loaded = true;
            const loader = wrap.querySelector('.cs-rec__player-loading');
            if (loader) loader.remove();
        });
        wrap.appendChild(iframe);
        setTimeout(() => {
            if (!loaded) {
                wrap.innerHTML = '<div class="cs-rec__player-error">' +
                    '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                    '<span>{{ __('Preview unavailable. Click below to view full content.') }}</span></div>';
                lockOverlay(overlay);
            }
        }, 6000);

        const kill = () => { try { iframe.src = 'about:blank'; } catch (_) {} };

        // Hard stop. postMessage the player to pause, then nuke the iframe
        // 250ms later so an in-player overlay can't restart playback.
        let polled = 0;
        const interval = setInterval(() => {
            polled += 250;
            if (polled >= seconds * 1000) {
                try { iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*'); } catch (_) {}
                clearInterval(interval);
                setTimeout(() => lockOverlay(overlay, kill), 250);
            }
        }, 250);
    }

    function startVimeo(wrap, overlay, seconds) {
        if (!window.Vimeo) {
            const tag = document.createElement('script');
            tag.src = 'https://player.vimeo.com/api/player.js';
            tag.onload = () => initVimeo(wrap, overlay, seconds);
            document.head.appendChild(tag);
        } else { initVimeo(wrap, overlay, seconds); }
    }
    function initVimeo(wrap, overlay, seconds) {
        const vid = wrap.dataset.vimeoId;
        wrap.innerHTML = '<iframe src="https://player.vimeo.com/video/' + vid + '?autoplay=1&muted=1" frameborder="0" allow="autoplay; fullscreen" style="width:100%;height:100%;"></iframe>';
        const iframe = wrap.querySelector('iframe');
        const player = new Vimeo.Player(iframe);
        const kill = () => { if (iframe) iframe.src = 'about:blank'; };
        player.setMuted(true).then(() => player.play());
        player.on('timeupdate', function(data) {
            if (data.seconds >= seconds) { player.pause(); lockOverlay(overlay, kill); }
        });
    }

    // ── 2. Add to Cart — vanilla AJAX, no jQuery / no toastr ───────────
    function getCsrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function csToast(message, kind) {
        let host = document.getElementById('cs-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'cs-toast-host';
            host.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
            document.body.appendChild(host);
        }
        const t = document.createElement('div');
        const bg = kind === 'error' ? '#DC2626' : kind === 'info' ? '#0F172A' : '#16A34A';
        t.style.cssText = 'background:' + bg + ';color:#fff;padding:10px 16px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 10px 30px rgba(0,0,0,0.18);max-width:340px;pointer-events:auto;opacity:0;transform:translateY(8px);transition:opacity .18s ease,transform .18s ease;';
        t.textContent = message;
        host.appendChild(t);
        requestAnimationFrame(() => { t.style.opacity = '1'; t.style.transform = 'translateY(0)'; });
        setTimeout(() => {
            t.style.opacity = '0'; t.style.transform = 'translateY(8px)';
            setTimeout(() => t.remove(), 220);
        }, 3200);
    }

    function updateCartBadge(count) {
        // Match every common selector used across the platform's themes
        document.querySelectorAll('.mini-cart-count, [data-cart-count], .cs-cart-count').forEach(el => {
            el.textContent = count;
            if (count > 0) {
                el.classList.remove('is-zero');
            } else {
                el.classList.add('is-zero');
            }
        });
    }

    // After a successful add, briefly open the drawer so the student
    // SEES that the item is in their cart and can hit Checkout. Saves a
    // click and removes the "wait, did it work?" doubt that triggered
    // the earlier complaint about the flow feeling broken.
    function flashCartDrawer() {
        document.body.classList.add('cs-cart-open');
        // Auto-close after 4 seconds in case the student wants to keep browsing
        setTimeout(() => {
            if (document.body.classList.contains('cs-cart-open')) {
                // Only auto-close if they haven't interacted with the drawer
                if (!document.querySelector('.cs-cart-drawer:hover, .cs-cart-drawer:focus-within')) {
                    document.body.classList.remove('cs-cart-open');
                }
            }
        }, 4000);
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-cs-cart]');
        if (!btn) return;
        e.preventDefault();
        const id = btn.dataset.courseId;
        if (!id) {
            csToast('{{ __('No course is linked to this video yet.') }}', 'info');
            return;
        }
        if (btn.disabled) return;

        const labelEl = btn.querySelector('.cs-btn__label');
        const original = labelEl ? labelEl.textContent : null;
        btn.disabled = true;
        if (labelEl) labelEl.textContent = '{{ __('Adding…') }}';

        fetch('{{ url('/add-to-cart') }}/' + encodeURIComponent(id) + '?ref=coach_site', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(r => r.json().catch(() => ({ status: 'error', message: 'Bad response from server' })))
        .then(data => {
            if (data && data.status === 'success') {
                csToast(data.message || '{{ __('Added to cart!') }}', 'success');
                if (typeof data.cart_count !== 'undefined') updateCartBadge(data.cart_count);
                // ORDER MATTERS: inject the drawer item FIRST, while `btn` is
                // still in the DOM. refreshDrawerAfterAdd reads
                // btn.closest('.cs-rec__card') to copy the title/thumb/price;
                // replaceWithViewCart() below detaches `btn` (replaceWith),
                // after which btn.closest() returns null and the drawer would
                // never populate (badge shows 1 but drawer stays "empty").
                refreshDrawerAfterAdd(id, btn, data);
                // Then replace ONLY this button (and the matching button on the
                // same card, if any) with a "View cart" link. Don't touch
                // buttons on other cards — those represent different videos.
                replaceWithViewCart(btn);
                // Open the cart drawer so the student can immediately see
                // the item + click Checkout. No navigation off-brand.
                flashCartDrawer();
            } else if (data && data.message && /already/i.test(data.message)) {
                // Server says the course is already in cart. Replace the
                // button with View cart so the student isn't stuck.
                csToast(data.message, 'info');
                replaceWithViewCart(btn);
                flashCartDrawer();
            } else {
                csToast((data && data.message) || '{{ __('Could not add to cart.') }}', 'error');
                btn.disabled = false;
                if (labelEl) labelEl.textContent = original;
            }
        })
        .catch(() => {
            csToast('{{ __('Network error — please try again.') }}', 'error');
            btn.disabled = false;
            if (labelEl) labelEl.textContent = original;
        });
    });

    // Add the just-added course into the drawer DOM so the user sees it
    // without a full page reload. Reads title/price/thumbnail from the
    // card the user clicked on.
    function refreshDrawerAfterAdd(courseId, btn, data) {
        const drawerBody = document.querySelector('.cs-cart-drawer__body');
        if (!drawerBody) return;

        // Idempotent: never show the same course twice in the drawer. The
        // server already blocks duplicate cart rows (hasCourseInCart /
        // checkItemExist), so if a node for this course is already present
        // (server-rendered OR injected earlier this page-view) we stop here.
        if (drawerBody.querySelector('.cs-cart-drawer__item[data-course-id="' + courseId + '"]')) {
            return;
        }

        const card = btn.closest('.cs-rec__card');
        if (!card) return;

        // Remove the empty-state if it's there
        const emptyEl = drawerBody.querySelector('.cs-cart-drawer__empty');
        if (emptyEl) emptyEl.remove();

        const titleEl = card.querySelector('.cs-rec__title');
        const thumbEl = card.querySelector('img');
        const priceEl = card.querySelector('.cs-rec__price');

        const item = document.createElement('div');
        item.className = 'cs-cart-drawer__item';
        item.dataset.courseId = courseId;
        const thumbSrc = thumbEl ? thumbEl.getAttribute('src') : '';
        const titleText = titleEl ? titleEl.textContent.trim() : 'Course';
        const priceText = priceEl ? priceEl.textContent.trim() : '';
        item.innerHTML =
            (thumbSrc ? '<img src="' + thumbSrc + '" alt="">' : '<div class="cs-cart-drawer__placeholder"><i class="fa-solid fa-play"></i></div>') +
            '<div class="cs-cart-drawer__meta">' +
              '<h4></h4>' +
              '<span class="cs-cart-drawer__price"></span>' +
            '</div>';
        item.querySelector('h4').textContent = titleText.length > 60 ? titleText.slice(0, 60) + '…' : titleText;
        item.querySelector('.cs-cart-drawer__price').textContent = priceText;

        // Give the just-added item a working remove (×) button too — the
        // backend returns the new cart row id + slug so we can build the same
        // /remove-cart-item URL the server-rendered items use. The master
        // layout's delegated handler then removes it via AJAX. (2026-06-11)
        if (data && typeof data.cart_row_id !== 'undefined' && data.cart_row_id !== null) {
            try {
                const removeUrl = '{{ url('/remove-cart-item') }}/' +
                    btoa(String(data.cart_row_id)) + '/' +
                    encodeURIComponent(data.slug || '0');
                const rm = document.createElement('a');
                rm.className = 'cs-cart-drawer__remove';
                rm.setAttribute('href', removeUrl);
                rm.setAttribute('aria-label', 'Remove');
                rm.setAttribute('title', 'Remove');
                rm.innerHTML = '&times;';
                item.appendChild(rm);
            } catch (e) { /* btoa can throw on non-latin1; skip the button */ }
        }
        drawerBody.appendChild(item);

        // Bump the drawer's header count
        const countEl = document.querySelector('.cs-cart-drawer__count');
        if (countEl) {
            const newCount = drawerBody.querySelectorAll('.cs-cart-drawer__item').length;
            countEl.textContent = '(' + newCount + ')';
        }

        // Make sure a footer exists with a Checkout button. If the drawer
        // was empty before, we need to inject one.
        if (!document.querySelector('.cs-cart-drawer__foot')) {
            const drawer = document.querySelector('.cs-cart-drawer');
            if (drawer) {
                const foot = document.createElement('footer');
                foot.className = 'cs-cart-drawer__foot';
                foot.innerHTML =
                    '<div class="cs-cart-drawer__total">' +
                        '<span>{{ __('Total') }}</span>' +
                        '<strong>' + priceText + '</strong>' +
                    '</div>' +
                    '<a href="{{ $coachCheckoutUrl }}" class="cs-btn cs-btn--primary cs-cart-drawer__checkout">' +
                        '<i class="fa-solid fa-lock"></i> {{ __('Proceed to secure checkout') }}' +
                    '</a>' +
                    '<a href="{{ $coachCartUrl }}" class="cs-cart-drawer__view-full">{{ __('View full cart page') }}</a>';
                drawer.appendChild(foot);
            }
        }
    }

    function replaceWithViewCart(btn) {
        // Only buttons WITH THE SAME course id on the same card get flipped.
        // (Each card has 2 buttons: overlay + foot. We update both for the
        // card the user clicked, and leave every other card untouched.)
        const card = btn.closest('.cs-rec__card');
        const id = btn.dataset.courseId;
        const targets = card
            ? card.querySelectorAll('[data-cs-cart][data-course-id="' + CSS.escape(id) + '"]')
            : [btn];
        targets.forEach(b => {
            const a = document.createElement('a');
            a.href = '{{ $coachCartUrl }}';
            a.className = b.className;
            a.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span class="cs-btn__label">{{ __('In cart — view') }}</span>';
            b.replaceWith(a);
        });
    }
})();
</script>
