<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Modules\Frontend\app\Models\Section;

class TermsandConditionController extends Controller
{
    //

    public function terms_and_conditions(){
         $theme_name = Session::has('demo_theme') ? Session::get('demo_theme') : DEFAULT_HOMEPAGE;
         $sections = Section::whereHas("home", function ($q) use ($theme_name) {
            $q->where('slug', $theme_name);
        })->get();

        $aboutSection = $sections->where('name', 'about_section')->first();
        return view('frontend.pages.terms-conditions',compact('aboutSection'));
    }

    public function privacyPolicy(){
        $theme_name = Session::has('demo_theme') ? Session::get('demo_theme') : DEFAULT_HOMEPAGE;
        $sections = Section::whereHas("home",function($q) use($theme_name){
            $q->where('slug',$theme_name);
        })->get();

        $aboutSection = $sections->where('name','about_section')->first();
        return view('frontend.pages.privacy-policy',compact('aboutSection'));
    }   

}
