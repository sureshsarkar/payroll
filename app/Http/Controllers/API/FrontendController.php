<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\CourseCategoryResource;
use App\Http\Resources\API\CourseDetailsCollection;
use App\Http\Resources\API\CourseLanguageResource;
use App\Http\Resources\API\CourseLevelResource;
use App\Http\Resources\API\CourseListResource;
use App\Http\Resources\API\CourseReviewsResource;
use App\Http\Resources\API\CustomPageResource;
use App\Http\Resources\API\FaqResource;
use App\Http\Resources\API\LanguageResource;
use App\Http\Resources\API\LessonResource;
use App\Http\Resources\API\MultiCurrencyResource;
use App\Http\Resources\API\OnBoardingScreenResource;
use App\Http\Resources\API\SocialLinkResource;
use App\Models\Course;
use App\Models\CourseChapterLesson;
use App\Models\CourseReview;
use App\Services\MailSenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\ContactMessage\app\Jobs\ContactMessageSendJob;
use Modules\ContactMessage\app\Models\ContactMessage;
use Modules\Course\app\Models\CourseCategory;
use Modules\Course\app\Models\CourseLanguage;
use Modules\Course\app\Models\CourseLevel;
use Modules\Currency\app\Models\MultiCurrency;
use Modules\Faq\app\Models\Faq;
use Modules\GlobalSetting\app\Models\Setting;
use Modules\Language\app\Models\Language;
use Modules\Location\app\Models\Country;
use Modules\NewsLetter\app\Models\NewsLetter;
use Modules\PageBuilder\app\Models\CustomPage; 
use Modules\SocialLink\app\Models\SocialLink;

class FrontendController extends Controller
{
    public function settings(): JsonResponse
    {
        $setting_list = ['app_name', 'logo', 'timezone', 'primary_color', 'secondary_color'];
        $settings = Setting::whereIn('key', $setting_list)->pluck('value', 'key');

        $data = [
            'app_name' => (string) $settings['app_name'],
            'logo' => (string) $settings['logo'],
            'timezone' => (string) $settings['timezone'],
            'primary_color' => (string) $settings['primary_color'],
            'secondary_color' => (string) $settings['secondary_color'],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200);
    }

    public function allLanguages(): JsonResponse
    {
        $allLanguages = Language::select('code', 'name', 'direction', 'is_default', 'status')->get();
        if ($allLanguages->isNotEmpty()) {
            $data = LanguageResource::collection($allLanguages);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function allCurrency(): JsonResponse
    {
        $allCurrency = MultiCurrency::all();
        if ($allCurrency->isNotEmpty()) {
            $data = MultiCurrencyResource::collection($allCurrency);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function getLanguageFile($code = 'en'): JsonResponse
    {
        $filePath = base_path('lang/'.$code.'.json');
        if (File::exists($filePath)) {
            $data = json_decode(File::get($filePath), true);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!', 'code' => $code], 404);
    }

    public function course_languages(Request $request): JsonResponse
    {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : -1;
        $languages = CourseLanguage::select('id', 'name')->orderBy('name')->where('status', 1)->latest()->take($limit)->get();
        if ($languages->isNotEmpty()) {
            $data = CourseLanguageResource::collection($languages);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function course_levels(Request $request): JsonResponse
    {
        $code = strtolower(request()->query('language', 'en'));
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : -1;

        $levels = CourseLevel::select('id', 'slug')->with(['translations' => function ($q) use ($code) {
            $q->where('lang_code', $code)->select('course_level_id', 'name');
        }])->orderBy('slug')->where('status', 1)->latest()->take($limit)->get();

        if ($levels->isNotEmpty()) {
            $data = CourseLevelResource::collection($levels);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function main_categories(Request $request): JsonResponse
    {
        $code = strtolower(request()->query('language', 'en'));
        $categories = CourseCategory::select('id', 'slug', 'icon', 'show_at_trending')->with(['translations' => function ($q) use ($code) {
            $q->where('lang_code', $code)->select('course_category_id', 'name');
        }])->where('parent_id', null)->orderBy('slug')->where('status', 1);

        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : -1;
        $categories = $categories->latest()->take($limit)->get();

        if ($categories->isNotEmpty()) {
            $data = CourseCategoryResource::collection($categories);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function sub_categories(Request $request, string $slug): JsonResponse
    {
        $code = strtolower(request()->query('language', 'en'));
        $categories = CourseCategory::select('id', 'slug')->with(['translations' => function ($q) use ($code) {
            $q->where('lang_code', $code)->select('course_category_id', 'name');
        }])->whereHas('parentCategory', function ($q) use ($slug) {
            $q->where('slug', $slug);
        })->whereNotNull('parent_id')->orderBy('slug')->where('status', 1);

        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : -1;
        $categories = $categories->latest()->take($limit)->get();

        if ($categories->isNotEmpty()) {
            $data = CourseCategoryResource::collection($categories);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function popular_courses(Request $request): JsonResponse
    {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 2;

        $courses = Course::select('slug', 'title', 'instructor_id', 'thumbnail', 'price', 'discount')->active()->with(['instructor:id,name,image'])
            // ->whereHas('category.parentCategory', fn ($q) => $q->where('status', 1))
            ->whereHas('category', fn ($q) => $q->where('status', 1))
            ->withCount(['reviews as average_rating' => function ($q) {
                $q->select(DB::raw('coalesce(avg(rating), 0) as average_rating'))->where('status', 1);
            }, 'enrollments'])->orderByDesc('enrollments_count')->orderByDesc('average_rating')->take($limit)->get();

        if ($courses->isNotEmpty()) {
            $data = CourseListResource::collection($courses);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function fresh_courses(Request $request): JsonResponse
    {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 2;

        $courses = Course::select('slug', 'title', 'instructor_id', 'thumbnail', 'price', 'discount')->active()->with(['instructor:id,name,image'])
            // ->whereHas('category.parentCategory', fn ($q) => $q->where('status', 1))
            ->whereHas('category', fn ($q) => $q->where('status', 1))
            ->withCount(['reviews as average_rating' => function ($q) {
                $q->select(DB::raw('coalesce(avg(rating), 0) as average_rating'))->where('status', 1);
            }, 'enrollments'])->latest()->take($limit)->get();

        if ($courses->isNotEmpty()) {
            $data = CourseListResource::collection($courses);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

 

    public function search_courses(Request $request): JsonResponse
    {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 6;

        $query = Course::select('id', 'slug', 'title', 'instructor_id', 'thumbnail', 'price', 'discount')
            ->active()->with(['instructor:id,name,image'])
            // ->whereHas('category.parentCategory', fn ($q) => $q->where('status', 1))
            // ->whereHas('category', fn ($q) => $q->where('status', 1))
            ->withCount(['reviews as average_rating' => fn ($q) => $q->select(DB::raw('coalesce(avg(rating), 0)'))->where('status', 1), 'enrollments']);

        $query->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
            $q->where('title', 'like', "%{$request->search}%")
                ->orWhere('slug', 'like', "%{$request->search}%")
                ->orWhere('type', 'like', "%{$request->search}%")
                ->orWhere('seo_description', 'like', "%{$request->search}%")
                ->orWhere('description', 'like', "%{$request->search}%")
                ->orWhere('price', 'like', "%{$request->search}%")
                ->orWhere('discount', 'like', "%{$request->search}%");
        }));

        $query->when($request->filled('main_category'), function ($q) use ($request) {
            $main_category_slugs = explode(',', $request->main_category);
            $q->whereHas('category', function ($q) use ($main_category_slugs) {
                $q->whereIn('slug', $main_category_slugs);
            }); 
        });

        $query->when($request->filled('sub_category'), function ($q) use ($request) {
            $sub_category_slugs = explode(',', $request->sub_category);
            $q->whereHas('subcategory', function ($q) use ($sub_category_slugs) {
                $q->whereIn('slug', $sub_category_slugs);
            });
        });

        $query->when($request->filled('languages'), function ($q) use ($request) {
            $languages_names = explode(',', $request->languages);
            $q->whereHas('languages.language', function ($q) use ($languages_names) {
                $q->whereIn('name', $languages_names);
            });
        });

        $query->when($request->filled('levels'), function ($q) use ($request) {
            $levelSlugs = explode(',', $request->levels);
            $q->whereHas('levels.level', function ($q) use ($levelSlugs) {
                $q->whereIn('slug', $levelSlugs);
            });
        });

        $query->when($request->filled('price'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                if ($request->price == 'paid') {
                    $q->where('price', '>', 0);
                } elseif ($request->price == 'free') {
                    $q->where('price', 0)->orWhereNull('price');
                }
            });
        });

        $query->when($request->filled('rating'), function ($q) use ($request) {
            $rating = (int) $request->rating;
            $q->having('average_rating', '>=', $rating);
        });

        $courses = $query->latest()->paginate($limit);

        if ($courses->isNotEmpty()) {
            $data = CourseListResource::collection($courses);

            return response()->json(['status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $courses->currentPage(),
                    'per_page' => $courses->perPage(),
                    'total' => $courses->total(),
                    'last_page' => $courses->lastPage(),
                    'links' => [
                        'first' => $courses->url(1),
                        'prev' => $courses->previousPageUrl(),
                        'next' => $courses->nextPageUrl(),
                        'last' => $courses->url($courses->lastPage()),
                    ],
                ],
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function course_details(string $slug): JsonResponse
    {
        $user_id = request('user_id', 0);
        $course = Course::active()->where('slug', $slug)->select('id', 'instructor_id', 'demo_video_source', 'demo_video_storage', 'thumbnail', 'title', 'slug', 'price', 'discount', 'description', 'updated_at')->with([
            'instructor:id,name,image',
            'chapters' => function ($query) {
                $query->where('status', 'active')->select('id', 'course_id', 'title')->orderBy('order', 'asc')->with([
                    'chapterItems:id,chapter_id,type',
                    'chapterItems.quiz' => fn ($q) => $q->select('id', 'chapter_item_id', 'title')->where('status', 'active'),
                    'chapterItems.lesson' => fn ($q) => $q->select('id', 'chapter_item_id', 'title', 'file_path', 'storage', 'file_type', 'duration', 'is_free')->where('status', 'active'),
                ]);
            },
            'languages:id,course_id,language_id' => ['language:id,name'],
        ])
        // ->whereHas('category.parentCategory', fn ($q) => $q->where('status', 1))
            ->whereHas('category', fn ($q) => $q->where('status', 1))->withCount([
                'reviews as average_rating' => fn ($q) => $q->select(DB::raw('coalesce(avg(rating), 0)'))->where('status', 1),
                'reviews' => fn ($q) => $q->where('status', 1),
                'lessons', 'quizzes', 'enrollments',
                'favoriteBy as is_wishlist' => function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                },
            ])->first();

        if ($course) {
            $data = new CourseDetailsCollection($course);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function get_lesson_info(int $lesson_id): JsonResponse
    {
        // Fetch lesson details
        $lesson = CourseChapterLesson::where('is_free', 1)->select('id', 'course_id', 'chapter_id', 'chapter_item_id', 'title', 'description', 'downloadable', 'file_path', 'storage', 'file_type', 'duration')->find($lesson_id);

        if (! $lesson) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $data = new LessonResource($lesson);

        return response()->json(['status' => 'success', 'data' => $data], 200);

    }

    public function course_reviews(Request $request, string $slug): JsonResponse
    {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 5;

        $reviews = CourseReview::whereHas('course', fn ($q) => $q->where('slug', $slug))->where('status', 1)
            ->whereHas('user')
            ->with('user')->orderBy('created_at', 'desc')->paginate($limit);

        if ($reviews) {
            $data = CourseReviewsResource::collection($reviews);

            return response()->json(['status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'last_page' => $reviews->lastPage(),
                    'links' => [
                        'first' => $reviews->url(1),
                        'prev' => $reviews->previousPageUrl(),
                        'next' => $reviews->nextPageUrl(),
                        'last' => $reviews->url($reviews->lastPage()),
                    ],
                ],

            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function privacy_policy(): JsonResponse
    {
        $code = strtolower(request()->query('language', 'en'));

        $page = CustomPage::select('id', 'slug')->whereSlug('privacy-policy')->with(['translations' => function ($q) use ($code) {
            $q->where('lang_code', $code)->select('custom_page_id', 'name', 'content');
        }])->first();

        if ($page) {
            $data = new CustomPageResource($page);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function terms_and_conditions(): JsonResponse
    {
        $code = strtolower(request()->query('language', 'en'));
        $page = CustomPage::select('id', 'slug')->whereSlug('terms-and-conditions')->with(['translations' => function ($q) use ($code) {
            $q->where('lang_code', $code)->select('custom_page_id', 'name', 'content');
        }])->first();
        if ($page) {
            $data = new CustomPageResource($page);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function faqs(Request $request): JsonResponse
    {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 4;
        $code = strtolower(request()->query('language', 'en'));
        
        $faqs = Faq::select('id')->with(['translations' => function ($q) use ($code) {
            $q->where('lang_code', $code)->select('faq_id', 'question', 'answer');
            }])->latest()->paginate($limit); 
        if ($faqs->isNotEmpty()) {
            $data = FaqResource::collection($faqs);

            return response()->json(['status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $faqs->currentPage(),
                    'per_page' => $faqs->perPage(),
                    'total' => $faqs->total(),
                    'last_page' => $faqs->lastPage(),
                    'links' => [
                        'first' => $faqs->url(1),
                        'prev' => $faqs->previousPageUrl(),
                        'next' => $faqs->nextPageUrl(),
                        'last' => $faqs->url($faqs->lastPage()),
                    ],
                ],
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function on_boarding_screen(Request $request): JsonResponse
    {
        
         // ✅ Get header value
    $clientName = $request->header('client-name');

    // Optional: check if header exists
   /* if (!$clientName) {
        return response()->json([
            'status' => 'error',
            'message' => 'Client name header missing'
        ], 400);
    }*/
    
    if($clientName=='photongear'){
         $screens = [
            [
                'title' => 'Welcome to photongear',
                'description' => 'Discover a world of knowledge and unlock your potential with our curated courses.',
            ],
            [
                'title' => 'Learn at Your Pace',
                'description' => 'Access courses anytime, anywhere, and track your progress as you grow.',
            ],
            [
                'title' => 'Showcase Your Skills',
                'description' => 'Complete courses to earn certificates and take your career to new heights.',
            ],
        ];
        
    }elseif($clientName=='yoga'){
         $screens = [
            [
                'title' => 'Welcome to VIRENDRA STRENGTH YOGA',
                'description' => 'Discover a world of knowledge and unlock your potential with our curated courses.',
            ],
            [
                'title' => 'Learn at Your Pace',
                'description' => 'Access courses anytime, anywhere, and track your progress as you grow.',
            ],
            [
                'title' => 'Showcase Your Skills',
                'description' => 'Complete courses to earn certificates and take your career to new heights.',
            ],
        ];
    }else{
        
        $screens = [
            [
                'title' => 'Welcome to MBS Guru',
                'description' => 'Discover a world of knowledge and unlock your potential with our curated courses.',
            ],
            [
                'title' => 'Learn at Your Pace',
                'description' => 'Access courses anytime, anywhere, and track your progress as you grow.',
            ],
            [
                'title' => 'Showcase Your Skills',
                'description' => 'Complete courses to earn certificates and take your career to new heights.',
            ],
        ];
        
    }
        $screensCollection = collect($screens);

        if ($screensCollection) {
            $data = OnBoardingScreenResource::collection($screensCollection);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function country_list(): JsonResponse
    {
        $country_list = Country::select('id', 'name')->where('status', 1)->get();
        if ($country_list->isNotEmpty()) {
            return response()->json(['status' => 'success', 'data' => $country_list], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    // extra
    public function socialLinks(): JsonResponse
    {
        $socialLinks = SocialLink::select('icon', 'link')->get();
        if ($socialLinks->isNotEmpty()) {
            $data = SocialLinkResource::collection($socialLinks);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function contactUs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required',
            'subject' => 'required',
            'message' => 'required',
        ], [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'subject.required' => 'Subject is required',
            'message.required' => 'Message is required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $new_message = new ContactMessage;
        $new_message->name = $request->name;
        $new_message->email = $request->email;
        $new_message->subject = $request->subject;
        $new_message->message = $request->message;
        $new_message->phone = $request->phone;
        $new_message->save();

        dispatch(new ContactMessageSendJob($new_message));

        return response()->json(['status' => 'success', 'message' => 'Message sent successfully'], 200);
    }

    public function newsletter_request(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|unique:news_letters',
        ], [
            'email.required' => 'Email is required',
            'email.unique' => 'Email already exist',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $newsletter = new NewsLetter;
        $newsletter->email = $request->email;
        $newsletter->verify_token = Str::random(100);
        $newsletter->save();

        (new MailSenderService)->sendVerifyMailToNewsletterFromTrait($newsletter);

        return response()->json(['status' => 'success', 'message' => 'A verification link has been send to your email, please verify it and getting our newsletter'], 200);
    }
}
