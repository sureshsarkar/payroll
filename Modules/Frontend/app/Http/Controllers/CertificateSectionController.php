<?php

namespace Modules\Frontend\app\Http\Controllers;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\Request;
use Modules\Frontend\app\Models\Section;
use Modules\Frontend\app\Models\SectionTranslation;
use Modules\Frontend\app\Traits\UpdateSectionTraits;
use Modules\Language\app\Enums\TranslationModels;
use Modules\Language\app\Models\Language;
use Modules\Language\app\Traits\GenerateTranslationTrait;

class CertificateSectionController extends Controller {
    use GenerateTranslationTrait, RedirectHelperTrait, UpdateSectionTraits;

    /**
     * Display a listing of the resource.
     */
    public function index() {
        checkAdminHasPermissionAndThrowException('section.management');
        $code = request('code') ?? getSessionLanguage();
        if (!Language::where('code', $code)->exists()) {
            abort(404);
        }
        $languages = allLanguages();
        $certificateSection = Section::getByName('certificate_section');

        return view('frontend::' . DEFAULT_HOMEPAGE . '.certificate-section', compact('languages', 'code', 'certificateSection'));
    }

    public  function uploadFile($file, $path = 'uploads')
    {
        // 2026-06-25 (security, FT-UPLOAD-3) — never build the stored name from the
        // client filename (a "x.phtml" polyglot would land executable in the web
        // root). Derive the extension from the server-detected MIME, clamp to an
        // image allowlist, and use a random basename.
        $ext = strtolower($file->extension() ?: 'png');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            $ext = 'png';
        }
        $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $ext;
        $file->move(public_path($path), $filename);

        return $path . '/' . $filename;
    }


    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request)
{
    checkAdminHasPermissionAndThrowException('section.management');

    $request->validate([    
        // FT-UPLOAD-1 fix (2026-05-27) — dropped svg (XSS).
        'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ], [ 
        'images.*.image' => __('The image is not valid.'),
        'images.*.max'   => __('The image is too large.'),
    ]);

    $section = Section::getByName('certificate_section');

    // ✅ ALWAYS convert to array safely
    $global_content = (array) $section->global_content ?? [];
    // pre($global_content);die;
    // ✅ Handle multiple image upload
    if ($request->hasFile('images')) {

        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            $path = $this->uploadFile($image, 'uploads/section');
            $global_content['images'][] = $path;
        }

        // 🔥 OPTION 1: Replace
        // $global_content['images'] = $uploadedImages;
 
    }

    // ✅ Save as JSON (only once)
    $section->update([
        'global_content' => $global_content
    ]);

    // ✅ Translation logic (unchanged)
    $content = $this->updateSectionContent(
        $section?->content,
        $request,
        ['short_title', 'title', 'description', 'total_languages']
    );

    $translation = SectionTranslation::where('section_id', $section->id)->exists();

    if (!$translation) {
        $this->generateTranslations(TranslationModels::Section, $section, 'section_id', $request);
    }

    return $this->redirectWithMessage(RedirectType::UPDATE->value);
}

 

    
}
