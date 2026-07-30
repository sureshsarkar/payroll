<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachLandingPage;
use App\Models\Course;
use App\Models\LandingPageEnquiry;
use App\Models\User;
use App\Models\YoutubeCredential;
use App\Traits\MailSenderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ContactMessage\app\Emails\ContactMessageMail;
use Modules\ContactMessage\app\Jobs\ContactMessageSendJob;
use Modules\GlobalSetting\app\Models\EmailTemplate;
use Modules\PageTemplateBuilder\app\Models\PageTemplateBuilder;
use Modules\PageTemplateBuilder\app\Models\PageTemplateCategory;

class LandingPageController extends Controller
{
    use MailSenderTrait;

    protected $pageName;

    public function __construct(CoachLandingPage $model)
    {
        $this->model = $model;
        $this->admin_base_url = 'instructor.landing-page.index';
        $this->admin_view = 'frontend.instructor-dashboard.landing-page';
        $this->admin_error_view = 'errors.403';
        $this->pageName = 'landing-page-builder';
    }

    public function index()
    {

        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            $coachId = (userAuth()->role == 'instructor') ? userAuth()->id : userAuth()->coach_id;
            $page = CoachLandingPage::where('added_by', $coachId)->first();
            $templates = PageTemplateBuilder::where('status', 1)->get();
            $categories = PageTemplateCategory::active()
            ->with(['templates' => function($q){
                $q->where('status',1);
            }])
            ->get();

            $courses = Course::where(function ($query) use ($coachId) {
                $query->where('added_by', userAuth()->id)
                    ->orWhere('instructor_id', $coachId);
            })
                ->where('coach_soft_delete', 0)->get(['id', 'title']);

            return view('frontend.instructor-dashboard.landing-page.index', compact('page', 'courses', 'templates','categories'));
        } else {
            return view($this->admin_error_view);
        }
    }

    public function storeName(Request $request)
    {
        // FT-IDOR-13 fix (2026-05-28) — was completely ungated even
        // though it creates a CoachLandingPage row and toggles other
        // pages' is_published flag. Every read method in this
        // controller calls checkPermission; the write methods were
        // missed. Same gate convention.
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // ✅ Validation Rules
        $rules = [
            'template_id' => ['required', 'integer'],
            'website_name' => ['required', 'string', 'max:50', 'unique:coach_landing_pages,website_name'],
            // FT-VAL-1 (extension) — added `alpha_dash`. Subdomain
            // gets concatenated with '.' . config('app.coach_domain')
            // a few lines down; allowing dots or other special
            // characters in the input would yield a malformed FQDN
            // and break the Path-B subdomain router. Letters / digits
            // / hyphens / underscores only.
            'subdomain' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:coach_landing_pages,subdomain'],
        ];

        $messages = [
            'website_name.required' => __('Name is required'),
            'template_id.required' => __('Template is required'),
            'website_name.unique' => __('This name is already taken'),
            'subdomain.required' => __('Domain name is required'),
            'subdomain.unique' => __('This domain name is already taken'),
        ];
        // ✅ Store validated data
        $validator = \Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $coachId = (userAuth()->role == 'instructor') ? userAuth()->id : userAuth()->coach_id;
        $slug = Str::slug($request->website_name);

        // Phase 8 — one-active-page-per-coach. Before activating the new
        // page, unpublish any others this coach owns so the public-facing
        // subdomain routing never has to disambiguate. The DB still keeps
        // the unpublished rows for history, and the coach can re-publish
        // any of them via /instructor/web-page if they want to switch back.
        CoachLandingPage::where('added_by', $coachId)
            ->where('is_published', 1)
            ->update(['is_published' => 0]);

        // ✅ Save Landing page data

        CoachLandingPage::create([
            'website_name' => $request->website_name,
            'template_id' => $request->template_id,
            'subdomain' => $request->subdomain.'.'.config('app.coach_domain'),
            'added_by' => $coachId,
            'slug' => $request->subdomain,
            'is_published' => 1,
            'title' => $request->website_name.' Landing Page',
        ]);

        return redirect()->route('instructor.landing-page-builder.index')->with('success', 'message sent successfully');

    }

    public function storeProduct(Request $request, $id)
    {
        // FT-IDOR-13 fix — same gate as storeName(). IDOR is already
        // covered by findOwnedPageOrFail below; this adds the
        // within-tenant least-privilege check.
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // ✅ Validation Rules
        // SECURITY (2026-06-01) — each product_id MUST be a course owned by
        // THIS coach. The original rules only checked integer/distinct, so a
        // coach could attach ANOTHER coach's course id to their landing page
        // (cross-coach leak on the public render).
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $rules = [
            'product_ids' => ['required', 'array'],
            'product_ids.*' => [
                'required', 'integer', 'distinct',
                \Illuminate\Validation\Rule::exists('courses', 'id')->where(function ($q) use ($coachId) {
                    $q->where(function ($q2) use ($coachId) {
                        $q2->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
                    });
                }),
            ],
        ];

        $messages = [
            'product_ids.required' => 'Please select at least one product',
            'product_ids.array' => 'Invalid product format',
            'product_ids.*.integer' => 'Each product must be a valid ID',
            'product_ids.*.distinct' => 'Duplicate products are not allowed',
            'product_ids.*.exists' => 'You can only add your own courses',
        ];
        // ✅ Store validated data
        $validator = \Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        $pagedata = $this->findOwnedPageOrFail($id);

        // ✅ Save Landing page data

        $pagedata->update([
            'product_ids' => $request->product_ids,
        ]);

        return redirect()->route('instructor.website-builder.index')->with('success', 'message sent successfully');

    }

    /**
     * Find a coach landing page by id, scoped to the current coach (or their staff's coach).
     * Aborts 404 if it doesn't belong to them — prevents IDOR on website edits.
     */
    private function findOwnedPageOrFail($id): CoachLandingPage
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        return CoachLandingPage::where('id', $id)->where('added_by', $coachId)->firstOrFail();
    }

    public function builder()
    {
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            $coachId = (userAuth()->role == 'instructor') ? userAuth()->id : userAuth()->coach_id;
            $page = CoachLandingPage::where('added_by', $coachId)->first();
            $template_file = PageTemplateBuilder::where('id', $page->template_id)->first();

            return view('frontend.instructor-dashboard.landing-page.create', compact('page', 'template_file'));
        } else {
            return view($this->admin_error_view);
        }
    }

    public function saveBuilder(Request $request, $id)
    {

        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            $page = CoachLandingPage::where('id', $id)
                ->where('added_by', auth()->id())
                ->firstOrFail();

            $payload = json_decode($request->getContent(), true);

            // FT-VAL-14 fix (2026-05-28) — cap html/css body sizes and
            // defensively handle null payloads.
            //
            // Pre-fix issues:
            //  (a) html/css had no `max:` — a coach could POST a 100MB
            //      page body → DB bloat in coach_landing_pages.html_content.
            //  (b) Line `count($request->json['styles'])` assumes
            //      $request->json is an array with 'styles' key. When
            //      json is null or missing 'styles', count() throws
            //      TypeError on PHP 8.
            //  (c) $payload['html'] dereferences null when getContent()
            //      isn't valid JSON.
            $request->validate([
                'html' => 'nullable|string|max:2000000',  // 2MB raw HTML
                'css'  => 'nullable|string|max:500000',   // 500KB CSS
                'json' => 'nullable|array',
                // 'full_html' => 'nullable|string',
            ]);
            $stylesArray = $request->input('json.styles', []);
            $jsonData    = is_array($stylesArray) && count($stylesArray) > 0
                ? json_encode($request->json)
                : null;
            $htmlPayload = is_array($payload) ? ($payload['html'] ?? null) : null;

            $page->update([
                'html_content' => $htmlPayload,
                'css_content' => $request->css,
                'json_content' => $jsonData,
                // 'full_html' => $payload['full_html'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Landing page saved successfully',
                // 'full_html' => $payload['full_html'],
            ]);

        } else {
            return view($this->admin_error_view);
        }
    }

    public function mediaUpload(Request $request)
    {
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            if (! $request->hasFile('file')) {
                return response()->json(['error' => 'No file uploaded'], 400);
            }

            // FT-UPLOAD-2 (LandingPageController, 2026-05-28) — pre-fix
            // accepted any file type with no size cap. Files stored
            // under public/storage/landing-media/ are publicly served,
            // so SVG/PHP uploads would XSS/RCE every visitor. Same
            // threat model as the addLesson fix in FT-UPLOAD-2.
            $request->validate([
                'file'   => ['required_without:file.*'],
                'file.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,pdf', 'max:10240'],
            ]);
            // Single-file shape: re-validate the same rule.
            if (! is_array($request->file('file'))) {
                $request->validate([
                    'file' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,pdf', 'max:10240'],
                ]);
            }

            $uploadedFiles = $request->file('file');
            $urls = [];

            if (is_array($uploadedFiles)) {
                foreach ($uploadedFiles as $file) {
                    $path = $file->store('landing-media', 'public');

                    $urls[] = url('/uploads/store/'.$path);// ['src' => Storage::url($path)];
                }
            } else {
                $path = $uploadedFiles->store('landing-media', 'public');
                $urls[] = url('/uploads/store/'.$path);// ['src' => Storage::url($path)];
            }

            return response()->json(['data' => $urls]);
        } else {
            return view($this->admin_error_view);
        }
    }

    private function generateUniqueSlug($slug)
    {
        $original = $slug;
        $count = 1;

        while (CoachLandingPage::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$count++;
        }

        return $slug;
    }

    /**
     * Show the public landing page by slug.
     * Route: GET /coach/{slug}   (no auth required)
     */
    public function publish_landing_page($slug)
    {

        //  echo $slug;die;
        // $flag = checkPermission($this->pageName);
        // if ($flag == 1) {

        $page = CoachLandingPage::where('slug', $slug)
            ->where('is_published', 1)
            ->firstOrFail();
            // pre($page->slug);die;
        $products = Course::whereIn('id', $page->product_ids ?? [])->get();
        $product_temp = view('frontend.instructor-dashboard.landing-page.product-template', compact('products','page'))->render();
        
       $youtube_credentials =  YoutubeCredential::where('instructor_id',$page->added_by)->first();

        $apiKey = $youtube_credentials->api_key??"";
        $channelId = $youtube_credentials->channel_id??"";
        

        if($apiKey !=""){ 
            $youtubeVideos = $this->fetchYoutubeVideos($apiKey,$channelId);
            }else{
                $youtubeVideos = null;
            }
//  pre($youtubeVideos);die;
            if(isset($youtubeVideos->items[0])){
                $youtube_temp = view('frontend.instructor-dashboard.landing-page.youtube-template', compact('youtubeVideos','page'))->render();
                }else{
                    $youtube_temp = null;
                    }
           

        return view('frontend.instructor-dashboard.landing-page.publish', compact('page', 'product_temp','youtube_temp'));

    }

    public function fetchYoutubeVideos($apiKey,$channelId){ 

        // $url = "https://www.googleapis.com/youtube/v3/search?key=".$apiKey."&channelId=".$channelId."&part=snippet,id&order=date&maxResults=6";

        // $response = file_get_contents($url);
        // return json_decode($response); 


         $response = Http::get('https://www.googleapis.com/youtube/v3/search', [
        'key' => $apiKey,
        'channelId' => $channelId,
        'part' => 'snippet,id',
        'order' => 'date',
        'maxResults' => 6
    ]);

    if ($response->failed()) {
      return   $response->json(); // debug error
    }

    return $response->object();


    }

    public function submit_landing_page(Request $request)
    {
        // 2026-06-17 — never lose a real lead to a blank "service" category.
        // The contact form auto-fills `service` from a hidden field; if a coach
        // left service_label blank it arrived empty and validation rejected the
        // whole submission ("Service is required"). `service` is a CRM
        // categorisation, not data the visitor types — so default an
        // empty/whitespace value instead of failing. Provided values are
        // untouched. Applies to every coach form (global, no hardcoding).
        $request->merge([
            'service' => trim((string) $request->input('service')) ?: 'General enquiry',
        ]);

        // ✅ Validation Rules
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required'],
            'service' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];

        $messages = [
            'first_name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'phone.required' => __('Phone is required.'),
            'phone.numeric' => __('Phone must be numeric.'),
            'service.required' => __('Service is required'),
        ];

        // ✅ Store validated data
        $validated = $request->validate($rules, $messages);

        $sourceUrl  = (string) ($request->headers->get('referer') ?? '');
        $requestUrl = rtrim(preg_replace('#^https?://#', '', $sourceUrl), '/');

        // Phase 6 — parse UTM tags from the referrer query string so the
        // CRM can attribute leads to specific ad campaigns. Lengths are
        // capped to the column width (100) to defend against pathological
        // referers; missing tags stay NULL (not '') so reports can filter
        // by IS NOT NULL accurately.
        $utm = $this->parseUtmTags($sourceUrl);

        // Phase 3 — resolve the originating landing page so the lead carries
        // template + business-vertical context into the CRM. Two-step lookup
        // (subdomain first, then path-slug from /coach/{slug}) covers both the
        // production subdomain routing and the localhost path-based routing.
        $page = CoachLandingPage::with(['getTemplate.categoryname'])
            ->where('subdomain', $requestUrl)
            ->first();

        if (!$page && preg_match('#/coach/([a-z0-9\-]+)#i', $sourceUrl, $m)) {
            $page = CoachLandingPage::with(['getTemplate.categoryname'])
                ->where('slug', $m[1])
                ->first();
        }

        // Phase 5 — duplicate-lead guard. If the same coach received an
        // enquiry from the same email/phone within the past 7 days, mark
        // the new one as 'spam' so it doesn't crowd the coach's inbox,
        // and drop a note on the ORIGINAL row so the coach knows the
        // visitor came back. We never silently drop the row — every
        // submission is preserved so the coach can audit later.
        $duplicate = $this->findRecentDuplicate(
            $page?->added_by, $request->email, $request->phone
        );

        // Coach Marketing Website (2026-05-25) — capture page_id + section_id
        // from the new section-based builder if the form posts them.
        $coachPageId = (int) $request->input('page_id', 0) ?: null;
        $sectionId   = (int) $request->input('section_id', 0) ?: null;

        // If form came from a CoachPage section but $page (legacy CoachLandingPage)
        // resolution failed, try to recover coach_id from coach_pages.
        if (! $page && $coachPageId) {
            $coachIdFromPage = \DB::table('coach_pages')->where('id', $coachPageId)->value('coach_id');
        } else {
            $coachIdFromPage = null;
        }

        // Capture any extra typed fields from the new lead_form_v1 section
        $customFields = $request->input('custom_fields', []);
        if (! is_array($customFields)) {
            $customFields = [];
        }

        // Defensive defaults — the DB columns first_name / last_name / email
        // are all NOT NULL but lead_form_v1 templates only ship with a
        // single "Name" field by default. Split a full name on first space
        // so we always have non-null first + last, and never crash the
        // SQL insert.
        $rawFirst = trim((string) $request->input('first_name', ''));
        $rawLast  = trim((string) $request->input('last_name', ''));
        if ($rawFirst !== '' && $rawLast === '' && str_contains($rawFirst, ' ')) {
            // Split "Sales Team" → first="Sales", last="Team"
            [$rawFirst, $rawLast] = array_pad(explode(' ', $rawFirst, 2), 2, '');
        }

        $enquiry = LandingPageEnquiry::create([
            'coach_id'           => $page?->added_by ?? $coachIdFromPage,
            'landing_page_id'    => $page?->id,
            'page_id'            => $coachPageId,
            'section_id'         => $sectionId,
            'template_id'        => $page?->template_id,
            'business_category'  => $page?->getTemplate?->categoryname?->name,
            'first_name'         => $rawFirst !== '' ? $rawFirst : '-',
            'last_name'          => $rawLast !== '' ? $rawLast : '-',
            'email'              => trim((string) $request->input('email', '')) ?: 'no-email@example.com',
            'phone'              => $request->phone,
            'service'            => $request->service,
            'message'            => $request->message,
            'enquiry_type'       => 'contact',
            'source'             => 'landing_page',
            'source_url'         => substr($sourceUrl, 0, 500) ?: null,
            'utm_source'         => $utm['source'],
            'utm_medium'         => $utm['medium'],
            'utm_campaign'       => $utm['campaign'],
            'custom_fields'      => $customFields ?: null,
            // landing_page_enquiries.status is NOT NULL — passing null
            // bypassed the column default and crashed the insert. Use
            // STATUS_NEW for normal leads, STATUS_SPAM for duplicates.
            'status'             => $duplicate
                ? \App\Models\LandingPageEnquiry::STATUS_SPAM
                : \App\Models\LandingPageEnquiry::STATUS_NEW,
        ]);

        if ($duplicate) {
            $this->recordDuplicateNote($duplicate, $enquiry);
            // Don't notify on duplicates — the coach already knows about
            // this lead. Notifying again would feel like spam to them too.
            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
            ], 200);
        }

        // Phase 4 — notify the coach directly (bell + Pusher + email).
        // Pre-Phase-4 the form only emailed the platform admin, so the coach
        // had to refresh the CRM to find new leads. Wrapped in try/catch so
        // notification failures never break the public form for the visitor.
        if ($page && $page->coach) {
            try {
                $page->coach->notify(new \App\Notifications\NewLandingPageEnquiryToCoach($enquiry));
            } catch (\Throwable $e) {
                \Log::warning('Notify NewLandingPageEnquiryToCoach failed: ' . $e->getMessage());
            }
        }

        // ✅ Mail Config
        self::setMailConfig();

        $template = EmailTemplate::where('name', 'landing_mail')->first();

        $subject = $template->subject;
        $message = $template->message;

        $message = str_replace('{{name}}', $request->first_name.' '.$request->last_name, $message);
        $message = str_replace('{{email}}', $request->email, $message);
        $message = str_replace('{{phone}}', $request->phone, $message);
        $message = str_replace('{{service}}', $request->service, $message);
        $message = str_replace('{{message}}', $request->message, $message);

        $email_setting = Cache::get('setting');

        if (self::isQueable()) {
            ContactMessageSendJob::dispatch($validated);
        } else {
            Mail::to($email_setting->contact_message_receiver_mail)
                ->send(new ContactMessageMail($subject, $message));
        }

        return response()->json(['success' => true, 'message' => 'Message sent successfully'], 200);

    }

    public function checkWebsiteName(Request $request)
    {
        $exists = CoachLandingPage::where('website_name', $request->website_name)->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }

    public function submit_service_page(Request $request)
    {

        // FT-IDOR-14 fix (2026-05-28) — `coach_id` was validated
        // `exists:users,id` (any user). The public service form is
        // submitted by an unauthenticated visitor, who can edit the
        // hidden `coach_id` input via the browser dev-tools. An
        // attacker could attribute leads to a student or admin
        // account (or to another coach) by forging the field — lead
        // poisoning / cross-coach inbox spoofing.
        //
        // Constrain the exists rule to users whose `role` is
        // 'instructor' so only legitimate coach accounts can be the
        // target of a service-form submission. Visitors who
        // legitimately fill in the form via the rendered page send
        // the correct coach_id (the page templating fills it from
        // the active CoachLandingPage row); forged values now fail
        // validation.
        //
        // ✅ Validation Rules
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'integer', 'exists:courses,id'],
            'coach_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'instructor'),
            ],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];

        $messages = [
            'name.required' => __('Name is required'),
            'product_id.required' => __('Product is required'),
            'coach_id.required' => __('Coach is required'),
            'email.required' => __('Email is required'),
            'phone.required' => __('Phone is required.'),
            'phone.numeric' => __('Phone must be numeric.'),
        ];

        // ✅ Store validated data
        $validated = $request->validate($rules, $messages);
        $productName = Course::where('id', $request->product_id)->value('title') ?? '';

        $coachid = User::where('id', $request->coach_id)->value('id');

        // Phase 3 — service-form leads also originate on a landing page; pull
        // template + vertical context from that coach's active page so the
        // CRM can attribute the lead correctly.
        $sourceUrl = (string) ($request->headers->get('referer') ?? '');
        $utm = $this->parseUtmTags($sourceUrl);  // Phase 6 — UTM attribution
        $page = CoachLandingPage::with(['getTemplate.categoryname'])
            ->where('added_by', $coachid)
            ->where('is_published', 1)
            ->orderByDesc('id')
            ->first();

        // Phase 5 — same duplicate-lead guard as submit_landing_page (see that
        // method's docstring for the 7-day-window rationale).
        $duplicate = $this->findRecentDuplicate($coachid, $request->email, $request->phone);

        $enquiry = LandingPageEnquiry::create([
            'coach_id'          => $coachid ?? null,
            'landing_page_id'   => $page?->id,
            'template_id'       => $page?->template_id,
            'business_category' => $page?->getTemplate?->categoryname?->name,
            'product_id'        => $request->product_id,
            'first_name'        => $request->name,
            // last_name is NOT NULL with no DB default — supply '' so the
            // insert can't fail under MySQL strict mode (2026-06-12).
            'last_name'         => '',
            'email'             => $request->email,
            'phone'             => $request->phone,
            'enquiry_type'      => 'product',
            'message'           => $request->message,
            'source'            => 'service_form',
            'source_url'        => substr($sourceUrl, 0, 500) ?: null,
            'utm_source'        => $utm['source'],
            'utm_medium'        => $utm['medium'],
            'utm_campaign'      => $utm['campaign'],
            // landing_page_enquiries.status is NOT NULL — see submit_landing_page().
            'status'            => $duplicate
                ? \App\Models\LandingPageEnquiry::STATUS_SPAM
                : \App\Models\LandingPageEnquiry::STATUS_NEW,
        ]);

        if ($duplicate) {
            $this->recordDuplicateNote($duplicate, $enquiry);
            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
            ], 200);
        }

        // Phase 4 — notify the coach (bell + Pusher + email). Same try/catch
        // safety as submit_landing_page so notification failures never block
        // the visitor's form submission.
        $coach = $coachid ? User::find($coachid) : null;
        if ($coach) {
            try {
                $coach->notify(new \App\Notifications\NewLandingPageEnquiryToCoach($enquiry));
            } catch (\Throwable $e) {
                \Log::warning('Notify NewLandingPageEnquiryToCoach (service_form) failed: ' . $e->getMessage());
            }
        }

        // ✅ Mail Config

        self::setMailConfig();

        $template = EmailTemplate::where('name', 'landing_mail')->first();

        $subject = $template->subject;
        $message = $template->message;

        $message = str_replace('{{name}}', $request->name, $message);
        $message = str_replace('{{email}}', $request->email, $message);
        $message = str_replace('{{phone}}', $request->phone, $message);
        $message = str_replace('{{service}}', $productName, $message);
        $message = str_replace('{{message}}', $request->message, $message);

        $email_setting = Cache::get('setting');

        if (self::isQueable()) {
            ContactMessageSendJob::dispatch($validated);
        } else {
            Mail::to($email_setting->contact_message_receiver_mail)
                ->send(new ContactMessageMail($subject, $message));
        }

        return response()->json(['success' => true, 'message' => 'Message sent successfully'], 200);

    }

    public function publishWebsite($id, $status)
    {
        // FT-IDOR-13 fix — gate + status whitelist.
        // The route is GET /publish-website/{id}/{status}; without
        // the whitelist any string was cast to int at line
        // `$data->is_published = ($status == 1) ? 1 : 0;` so a
        // garbage value silently mapped to "unpublish". Tighten by
        // requiring "0" or "1" literally — predictable behaviour
        // and prevents future refactors from relying on the
        // coercion. (Note: the GET-vs-POST CSRF concern stays —
        // toggle-by-GET is a separate route-level issue tracked
        // elsewhere.)
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }
        abort_unless(in_array((string) $status, ['0', '1'], true), 400, 'Invalid status');

        $data = $this->findOwnedPageOrFail($id);

        if ($data->json_content == null) {
            return redirect()->route('instructor.landing-page-builder.index')->with('success', 'Design Your debsite');
        }

        // Phase 8 — one-active-page-per-coach enforcement. When publishing,
        // unpublish any sibling pages this coach owns so the subdomain
        // routing always resolves deterministically. Unpublishing doesn't
        // need the same treatment (going to 0 → no siblings to clear).
        if ((int) $status === 1) {
            CoachLandingPage::where('added_by', $data->added_by)
                ->where('id', '!=', $data->id)
                ->where('is_published', 1)
                ->update(['is_published' => 0]);
        }

        $data->is_published = ($status == 1) ? 1 : 0;
        $data->save();

        return redirect()->route('instructor.website-builder.index')->with('success', 'Website Published');
    }

    public function deleteWebsite($id)
    {
        // FT-IDOR-13 fix — gate. IDOR covered by findOwnedPageOrFail.
        // Destroy path uses the (pageName, methodName) overload for
        // the role-delete permission slug, matching the convention
        // in CoachStaffController + CoachStaffRoleController.
        $methodName = request()->route()->getActionMethod();
        $flag = checkPermission($this->pageName, $methodName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        $exist = $this->findOwnedPageOrFail($id);
        $exist->delete();

        return redirect()->route('instructor.website-builder.index')->with('success', 'Website Deleted');
    }

    /**
     * Phase 5 duplicate-lead guard. Returns the existing non-spam enquiry
     * within the past 7 days for the same coach + email OR phone, or null
     * if this is a fresh lead.
     *
     * Logic:
     *   - Email match is the primary signal — duplicate by phone alone is
     *     more error-prone (shared family/work phones) so we still require
     *     a coach+phone+recent window combo.
     *   - We exclude prior duplicates (status='spam') from the search so a
     *     scripted bot hitting the form 50x doesn't reset the window from
     *     the most recent spam row.
     *   - Recent = 7 days. Long enough to catch enthusiastic resubmitters,
     *     short enough that a genuine returning lead (months later) gets
     *     a fresh CRM row.
     */
    private function findRecentDuplicate(?int $coachId, ?string $email, ?string $phone): ?LandingPageEnquiry
    {
        if (!$coachId || (!$email && !$phone)) {
            return null;
        }
        $cutoff = now()->subDays(7);
        return LandingPageEnquiry::where('coach_id', $coachId)
            ->where('created_at', '>=', $cutoff)
            ->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->where('email', $email);
                }
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', LandingPageEnquiry::STATUS_SPAM);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Phase 6 — extract utm_source / utm_medium / utm_campaign from a
     * referrer URL's query string. Returns three keys ('source', 'medium',
     * 'campaign') with either the truncated tag value or null. Defensive
     * against malformed URLs and overlong values; the caller can splat
     * the result straight into the create() array.
     */
    private function parseUtmTags(string $referer): array
    {
        $out = ['source' => null, 'medium' => null, 'campaign' => null];
        if ($referer === '') {
            return $out;
        }
        $query = (string) parse_url($referer, PHP_URL_QUERY);
        if ($query === '') {
            return $out;
        }
        parse_str($query, $params);
        $cap = fn ($v) => is_string($v) && $v !== '' ? substr($v, 0, 100) : null;
        $out['source']   = $cap($params['utm_source']   ?? null);
        $out['medium']   = $cap($params['utm_medium']   ?? null);
        $out['campaign'] = $cap($params['utm_campaign'] ?? null);
        return $out;
    }

    /**
     * Record a "duplicate received" note on the original enquiry so the
     * coach can see at a glance that the lead came back. Wrapped in
     * try/catch — the duplicate guard must never block the public form
     * even if the notes table is in an unexpected state.
     */
    private function recordDuplicateNote(LandingPageEnquiry $original, LandingPageEnquiry $duplicate): void
    {
        try {
            \App\Models\LeadNote::create([
                'enquiry_id' => $original->id,
                'author_id'  => null,
                'body'       => 'Duplicate submission received ' . now()->format('Y-m-d H:i')
                    . ' (lead #' . $duplicate->id . '). Same email/phone within 7 days.',
            ]);
            $original->increment('notes_count');
        } catch (\Throwable $e) {
            \Log::warning('recordDuplicateNote failed: ' . $e->getMessage());
        }
    }
}
