<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\RedirectMessage;
use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\StudentBioUpdateRequest;
use App\Http\Requests\Frontend\StudentEducationStoreRequest;
use App\Http\Requests\Frontend\StudentExperienceStoreRequest;
use App\Http\Requests\Frontend\StudentPasswordUpdateRequest;
use App\Http\Requests\Frontend\StudentProfileUpdateRequest;
use App\Http\Requests\Frontend\StudentProfileAddressUpdateRequest;
use App\Http\Requests\Frontend\StudentProfileSocialUpdateRequest;
use App\Models\UserEducation;
use App\Models\UserExperience;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Modules\Location\app\Models\City;
use Modules\Location\app\Models\State;

class InstructorProfileSettingController extends Controller
{
    use RedirectHelperTrait;

    function index(): View
    {
        // set profile tab
        if (!session('profile_tab')) setFormTabStep('profile_tab', 'profile');

        $user = Auth::user();
        $experiences = UserExperience::where('user_id', $user->id)->get();
        $educations = UserEducation::where('user_id', $user->id)->get();
        $states = State::where(['country_id' => $user->country_id, 'status' => 1])->get();
        $cities = City::where(['state_id' => $user->state_id, 'status' => 1])->get();
        // LMS removal phase 2 (2026-08-27) — dropped $payoutInfo,
        // $instructorRequestSetting, $withdrawMethods and $instructorRequest.
        // They fed the Payout tab (coach withdrawal account for course-sale
        // earnings) and came from the deleted InstructorRequest and
        // PaymentWithdraw modules; the tab itself is gone from the view.
        return view('frontend.instructor-dashboard.profile.index', compact('user', 'experiences', 'educations', 'states', 'cities'));
    }

    function updateProfile(StudentProfileUpdateRequest $request): RedirectResponse
    {

        $user = Auth::user();
        // handle image files
        if ($request->hasFile('avatar')) {
            $imagePath = file_upload(file: $request->avatar, optimize: true);
            $user->image = $imagePath;
        }
        if ($request->hasFile('cover')) {
            $imagePath = file_upload(file: $request->cover, optimize: true);
            $user->cover = $imagePath;
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->age = $request->age;
        $user->gender = $request->gender;
        $user->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.setting.index');
    }

    function updateBio(StudentBioUpdateRequest $request): RedirectResponse
    {

        $user = Auth::user();
        $user->job_title = $request->designation;
        $user->bio = $request->bio;
        $user->short_bio = $request->short_bio;
        $user->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.setting.index');
    }

    function updatePassword(StudentPasswordUpdateRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.setting.index');
    }

    function showExperienceModal(): string
    {
        return view('frontend.instructor-dashboard.profile.modals.experience-modal')->render();
    }

    function storeExperience(StudentExperienceStoreRequest $request): RedirectResponse
    {
        $experience = new UserExperience();
        $experience->user_id = Auth::user()->id;
        $experience->company = $request->company;
        $experience->position = $request->position;
        $experience->start_date = $request->start_date;
        $experience->end_date = $request->end_date;
        $experience->current = $request->current;
        $experience->save();

        return $this->redirectWithMessage(RedirectType::CREATE->value, 'instructor.setting.index');
    }

    function editExperienceModal(string $id): string
    {
        // SECURITY (audit 2026-05-22) — sibling updateExperience()
        // already scopes via whereUserId(Auth::user()->id); this modal
        // method was missed and leaked any user's experience row.
        $experience = UserExperience::whereUserId(Auth::user()->id)
            ->whereId($id)
            ->firstOrFail();
        return view('frontend.instructor-dashboard.profile.modals.edit-experience-modal', compact('experience'))->render();
    }

    function updateExperience(StudentExperienceStoreRequest $request, string $id): RedirectResponse
    {
        $experience = UserExperience::whereUserId(Auth::user()->id)->whereId($id)->firstOrFail();
        $experience->company = $request->company;
        $experience->position = $request->position;
        $experience->start_date = $request->start_date;
        $experience->end_date = $request->end_date;
        $experience->current = $request->current;
        $experience->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.setting.index');
    }

    function destroyExperience(string $id): JsonResponse
    {
        setFormTabStep('profile_tab', 'education');

        $experience = UserExperience::whereUserId(Auth::user()->id)->whereId($id)->firstOrFail();
        $experience->delete();
        return response()->json([
            'status' => 'success',
            'message' => RedirectMessage::DELETE->value,
        ]);
    }

    function addEducationModal(): string
    {
        return view('frontend.instructor-dashboard.profile.modals.add-education-modal')->render();
    }

    function storeEducation(StudentEducationStoreRequest $request): RedirectResponse
    {
        $education = new UserEducation();
        $education->user_id = Auth::user()->id;
        $education->organization = $request->organization; 
        $education->degree = $request->degree; 
        $education->start_date = $request->start_date; 
        $education->end_date = $request->end_date; 
        $education->current = $request->current; 
        $education->save();

        return $this->redirectWithMessage(RedirectType::CREATE->value, 'instructor.setting.index');
    }

    function editEducationModal(): string
    {
        // SECURITY (audit 2026-05-22) — same fix as editExperienceModal
        // above. updateEducation() correctly scopes via Auth::user()->id;
        // this read path was missed.
        $education = UserEducation::whereUserId(Auth::user()->id)
            ->whereId(request('id'))
            ->firstOrFail();
        return view('frontend.instructor-dashboard.profile.modals.edit-education-modal', compact('education'))->render();
    }

    function updateEducation(StudentEducationStoreRequest $request, string $id): RedirectResponse
    {
        $education = UserEducation::whereUserId(Auth::user()->id)->whereId($id)->firstOrFail();
        $education->organization = $request->organization; 
        $education->degree = $request->degree; 
        $education->start_date = $request->start_date; 
        $education->end_date = $request->end_date; 
        $education->current = $request->current; 
        $education->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.setting.index');
    }

    function destroyEducation(string $id): JsonResponse
    {
        setFormTabStep('profile_tab', 'education');

        $education = UserEducation::whereUserId(Auth::user()->id)->whereId($id)->firstOrFail();
        $education->delete();
        return response()->json([
            'status' => 'success',
            'message' => RedirectMessage::DELETE->value,
        ]);
    }

    function updateAddress(StudentProfileAddressUpdateRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $user->address = $request->address;
        $user->city = $request->city;
        $user->state = $request->state;
        $user->country_id = $request->country;
        $user->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.setting.index');
    }

    function updateSocials(StudentProfileSocialUpdateRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $user->facebook = $request->facebook;
        $user->twitter = $request->twitter;
        $user->website = $request->website;
        $user->linkedin = $request->linkedin;
        $user->github = $request->github;
        $user->save();

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.setting.index');
    }

    /* LMS removal phase 2 (2026-08-27) — removed updatePayout(), which saved the
     * coach's withdrawal account onto their InstructorRequest row. Its route
     * (instructor.setting.payout.update) and its form section are both gone. */
}
