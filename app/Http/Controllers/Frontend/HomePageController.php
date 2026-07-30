<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\ThemeList;
use App\Http\Controllers\Controller;
use App\Jobs\DefaultMailJob;
use App\Mail\DefaultMail;
use App\Models\Course;
use App\Models\User;
use App\Models\UserEducation;
use App\Models\UserExperience;
use App\Rules\CustomRecaptcha;
use App\Traits\MailSenderTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Modules\Badges\app\Models\Badge;
use Modules\Blog\app\Models\Blog;
use Modules\Brand\app\Models\Brand;
use Modules\Course\app\Models\CourseCategory;
use Modules\Faq\app\Models\Faq;
use Modules\Frontend\app\Models\FeaturedCourseSection;
use Modules\Frontend\app\Models\FeaturedInstructor;
use Modules\Frontend\app\Models\Section;
use Modules\GlobalSetting\app\Models\EmailTemplate;
use Modules\Location\app\Models\City;
use Modules\Location\app\Models\Country;
use Modules\Location\app\Models\State;
use Modules\Marquee\app\Models\Marquee;
use Modules\PageBuilder\app\Models\CustomPage; 
use Modules\SiteAppearance\app\Models\SectionSetting;
use Modules\Testimonial\app\Models\Testimonial;

class HomePageController extends Controller {
    use MailSenderTrait;

    function index(): \Illuminate\View\View|\Symfony\Component\HttpFoundation\Response {

        // 2026-06-04 (White-Label G1) — on a VERIFIED coach custom domain, serve
        // that coach's marketing website at the root instead of the platform
        // home. Gated on resolved_coach_id, which ResolveCoachByDomain stamps
        // ONLY for hosts matched in coach_domains (verified). The platform's own
        // domain has no stamp, so this branch is skipped and behaviour is
        // completely unchanged there.
        $resolvedCoachId = (int) request()->attributes->get('resolved_coach_id');
        if ($resolvedCoachId > 0) {
            return app(CoachSitePublicController::class)->showOnDomain('home');
        }

        $theme_name = Session::has('demo_theme') ? Session::get('demo_theme') : DEFAULT_HOMEPAGE;
       
// echo $theme_name;die;
        $sections = Section::whereHas("home", function ($q) use ($theme_name) {
            $q->where('slug', $theme_name);
        })->get();


        $hero = $sections->where('name', 'hero_section')->first();

        $slider = $sections->where('name', 'slider_section')->first();
        $aboutSection = $sections->where('name', 'about_section')->first();
        $why_choose_us_section = $sections->where('name', 'why_choose_section')->first();
        $certificate_section = $sections->where('name', 'certificate_section')->first();
        // pre($certificate_section);die;
        $newsletterSection = $sections->where('name', 'newsletter_section')->first();
        $counter = $sections->where('name', 'counter_section')->first();
        $ourFeatures = $sections->where('name', 'our_features_section')->first();
        $bannerSection = $sections->where('name', 'banner_section')->first();
        $faqSection = $sections->where('name', 'faq_section')->first();

        $coursesList = Course::where('status','active')->take(5)->get();

        // echo '<pre>',print_r($coursesList);
        // exit;



        $faqs = Faq::with('translation')->where('status', 1)->get();

        $trendingCategories = CourseCategory::with(['translation:id,name,course_category_id', 'subCategories' => function ($query) {
            $query->withCount(['courses' => function ($query) {
                $query->where('status', 'active');
            }]);
        }])->withCount(['subCategories as active_sub_categories_count' => function ($query) {
            $query->whereHas('courses', function ($query) {
                $query->where('status', 'active');
            });
        }])->whereNull('parent_id')
            ->where('status', 1)
            ->where('show_at_trending', 1)
            ->get();

        // echo '<pre>',print_r($trendingCategories);
        // exit;


        $brands = Brand::where('status', 1)->get();

        $featuredCourse = FeaturedCourseSection::first();

        $marquees_up = Marquee::where(['status' => 1,'type' => 1])->get();
        $marquees_down = Marquee::where(['status' => 1,'type' => 2])->get();

        $featuredInstructorSection = FeaturedInstructor::first();
        $instructorIds = json_decode($featuredInstructorSection->instructor_ids ?? '[]');

        $selectedInstructors = User::whereIn('id', $instructorIds)
            ->with(['courses' => function ($query) {
                $query->withCount(['reviews as avg_rating' => function ($query) {
                    $query->select(DB::raw('coalesce(avg(rating),0)'));
                }]);
            }])
            ->get();

      

       
        $testimonials = Testimonial::all();

        $featuredBlogs = Blog::with(['translation', 'author'])
            ->whereHas('category', function ($q) {$q->where('status', 1);})
            ->where(['show_homepage' => 1, 'status' => 1])->orderBy('created_at', 'desc')->limit(4)->get();

        $sectionSetting = SectionSetting::first();

        

        return view('frontend.home.' . $theme_name . '.index', compact(
            'hero',
            'marquees_up',
            'marquees_down',
            'coursesList',
            'slider',
            'trendingCategories',
            'brands',
            'aboutSection',
            'why_choose_us_section',
            'certificate_section',
            'featuredCourse',
            'newsletterSection',
            'featuredInstructorSection',
            'selectedInstructors',
            'counter',
            'faqSection',
            'faqs',
            'testimonials',
            'ourFeatures',
            'bannerSection',
            'featuredBlogs',
            'sectionSetting'
        ));
    }

    function countries(): JsonResponse {
        $countries = Country::where('status', 1)->get();
        return response()->json($countries);
    }

    function states(string $id): JsonResponse {
        $states = State::where(['country_id' => $id, 'status' => 1])->get();
        return response()->json($states);
    }

    function cities(string $id): JsonResponse {
        $cities = City::where(['state_id' => $id, 'status' => 1])->get();
        return response()->json($cities);
    }

    public function setCurrency() {
        $currency = allCurrencies()->where('currency_code', request('currency'))->first();
        if (session()->has('currency_code')) {
            session()->forget('currency_code');
            session()->forget('currency_position');
            session()->forget('currency_icon');
            session()->forget('currency_rate');
        }
        if ($currency) {
            session()->put('currency_code', $currency->currency_code);
            session()->put('currency_position', $currency->currency_position);
            session()->put('currency_icon', $currency->currency_icon);
            session()->put('currency_rate', $currency->currency_rate);

            $notification = __('Currency Changed Successfully');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];

            return redirect()->back()->with($notification);
        }
        getSessionCurrency();
        $notification = __('Currency Changed Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    function instructorDetails(string $id) {
        User::where(['status' => 'active', 'is_banned' => 0, 'id' => $id])->first();
        $instructor = User::where(['status' => 'active', 'is_banned' => 0, 'id' => $id])->with(['courses' => function ($query) {
            // 2026-06-01 (audit) — public profile: only show sellable courses
            // (approved + active + not coach-soft-deleted), not drafts/pending.
            $query->where('is_approved', 'approved')->where('status', 'active')->where('coach_soft_delete', 0)
                ->withCount(['reviews as avg_rating' => function ($query) {
                    $query->select(DB::raw('coalesce(avg(rating),0)'));
                }]);
        }])
            ->firstOrFail();
        $experiences = UserExperience::where(['user_id' => $id])->get();
        $educations = UserEducation::where(['user_id' => $id])->get();
        $courses = Course::active()->where(['instructor_id' => $id])->orderBy('id', 'desc')->get();
        $badges = Badge::where(['status' => 1])->get()->groupBy('key');
        return view('frontend.pages.instructor-details', compact('instructor', 'experiences', 'educations', 'courses', 'badges'));
    }

    function allInstructors() {
        // 2026-06-01 (audit) — public listing: count + show only sellable
        // courses (approved + active + not coach-soft-deleted), not drafts.
        $sellable = function ($query) {
            $query->where('is_approved', 'approved')->where('status', 'active')->where('coach_soft_delete', 0);
        };
        $instructors = User::where(['status' => 'active', 'is_banned' => 0, 'role' => 'instructor'])
            ->withCount(['courses as course_count' => $sellable])
            ->with(['courses' => function ($query) use ($sellable) {
                $sellable($query);
                $query->withCount(['reviews as avg_rating' => function ($query) {
                    $query->select(DB::raw('coalesce(avg(rating),0)'));
                }]);
            }])
            ->orderByDesc('course_count')
            ->paginate(18);

        return view('frontend.pages.all-instructors', compact('instructors'));
    }

    function quickConnect(Request $request, string $id) {
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'string', 'email', 'max:255'],
            'subject'              => ['required', 'string', 'max:255'],
            'message'              => ['required', 'string', 'max:1000'],
            'g-recaptcha-response' => Cache::get('setting')->recaptcha_status == 'active' ? ['required', new CustomRecaptcha()] : 'nullable',
        ]);

        $settings = cache()->get('setting');
        $marketingSettings = cache()->get('marketing_setting');
        if ($settings->google_tagmanager_status == 'active' && $marketingSettings->instructor_contact) {
            $instructor_contact = [
                'name'    => $request->name,
                'email'   => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
            ];
            session()->put('instructorQuickContact', $instructor_contact);
        }

        $this->handleMailSending($validated);
        return redirect()->back()->with(['messege' => __('Message sent successfully'), 'alert-type' => 'success']);
    }

    function handleMailSending(array $mailData) {
        self::setMailConfig();

        // Get email template
        $template = EmailTemplate::where('name', 'instructor_quick_contact')->firstOrFail();

        // Prepare email content
        $message = str_replace('{{name}}', $mailData['name'], $template->message);
        $message = str_replace('{{email}}', $mailData['email'], $message);
        $message = str_replace('{{subject}}', $mailData['subject'], $message);
        $message = str_replace('{{message}}', $mailData['message'], $message);

        if (self::isQueable()) {
            DefaultMailJob::dispatch($mailData['email'], $mailData, $message);
        } else {
            Mail::to($mailData['email'])->send(new DefaultMail($mailData, $message));
        }
    }

    function customPage(string $slug) {
        $page = CustomPage::where('slug', $slug)->firstOrFail();
        return view('frontend.pages.custom-page', compact('page'));
    }

    function changeTheme(string $theme) {
        if (Cache::get('setting')?->show_all_homepage != 1) {
            abort(404);
        }

        foreach (ThemeList::cases() as $enumTheme) {
            if ($theme == $enumTheme->value) {
                Session::put('demo_theme', $enumTheme->value);
                break;
            }
        }
        return redirect('/');
    }
}
