<?php

namespace Modules\Language\app\Http\Controllers;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Traits\RedirectHelperTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Modules\Language\app\Enums\AllCountriesDetailsEnum;
use Modules\Language\app\Enums\SyncLanguageType;
use Modules\Language\app\Http\Requests\LanguageRequest;
use Modules\Language\app\Models\Language;
use Modules\Language\app\Traits\LanguageTrait;
use Modules\Language\app\Traits\SyncModelsTrait;

class LanguageController extends Controller
{
    use LanguageTrait, RedirectHelperTrait, SyncModelsTrait;

    public function index(): View
    {
        checkAdminHasPermissionAndThrowException('language.view');
        Paginator::useBootstrap();
        $languages = Language::paginate(15);

        return view('language::index', [
            'languages' => $languages,
        ]);
    }

    public function create(): View
    {
        checkAdminHasPermissionAndThrowException('language.create');
        $all_languages = AllCountriesDetailsEnum::getLanguages();

        return view('language::create', compact('all_languages'));
    }

    public function store(LanguageRequest $request): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('language.store');
        $direction = $this->getTextDirection($request->code);
        $language = Language::create(array_merge(['direction'=>$direction],$request->validated()));

        if ($language) {
            $code = $language->code;
            $parentDir = dirname(app_path());

            $sourcePath = $parentDir.'/lang/en.json';
            $destinationPath = $parentDir."/lang/{$code}.json";

            if (File::exists($sourcePath) && ! File::exists($destinationPath)) {
                $jsonData = File::get($sourcePath);
                File::put($destinationPath, $jsonData);
            }
        }

        // 2026-05-26 (bug-doc A4) — syncModels copies translation rows
        // across many modules. If any one module's translation table is
        // missing/migrated stale, the whole loop throws and the admin
        // sees "Got error to create language" even though the Language
        // row itself saved cleanly. Wrap in try/catch so the language
        // create always succeeds and the sync failure becomes a logged
        // warning instead of a 500.
        try {
            $this->syncModels(SyncLanguageType::CREATE->value, boolval(SyncLanguageType::isQueueable()), $language->code);
        } catch (\Throwable $e) {
            \Log::error('language-sync-models-failed', [
                'language_code' => $language->code,
                'error'         => $e->getMessage(),
                'at'            => $e->getFile() . ':' . $e->getLine(),
            ]);
            return redirect()->route('admin.languages.index')->with([
                'messege'    => __('Language saved, but some translatable models failed to sync. Check storage/logs/laravel.log for details.'),
                'alert-type' => 'warning',
            ]);
        }

        return $this->redirectWithMessage(
            RedirectType::CREATE->value,
            'admin.languages.index',
        );
    }

    public function edit(Language $language): View
    {
        checkAdminHasPermissionAndThrowException('language.edit');
        $all_languages = AllCountriesDetailsEnum::getLanguages();

        return view('language::edit', compact('language', 'all_languages'));
    }

    public function update(LanguageRequest $request, Language $language): RedirectResponse {
        checkAdminHasPermissionAndThrowException('language.update');
        $oldCode = $language->code;
        
        $direction = $this->getTextDirection($request->code);
        $language->update(array_merge(['direction'=>$direction],$request->validated()));

        $code = $language->code;

        if ($language && ($oldCode !== $code) && ($code !== 'en')) {
            $parentDir = dirname(app_path());

            $sourcePath = $parentDir . '/lang/en.json';
            $destinationPath = $parentDir . "/lang/{$code}.json";

            if (File::exists($sourcePath) && !File::exists($destinationPath)) {
                $jsonData = File::get($sourcePath);
                File::put($destinationPath, $jsonData);
            }

            if ($oldCode !== $code && $code !== 'en') {
                $destinationPath = $parentDir . "/lang/{$oldCode}.json";
                try {
                    File::delete($destinationPath);
                } catch (\Exception $ex) {
                    info($ex->getMessage());
                }
            }
        }

        $this->syncModels(SyncLanguageType::UPDATE->value, boolval(SyncLanguageType::isQueueable()), $language->code, $oldCode);

        return $this->redirectWithMessage(
            RedirectType::UPDATE->value,
            'admin.languages.index',
        );
    }

    public function destroy(Language $language): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('language.delete');
        if ($language->id == 1) {
            return $this->redirectWithMessage(
                RedirectType::ERROR->value,
                'admin.languages.index',
            );
        }

        $code = $language->code;
        if ($code == app()->getLocale() || $code == 'en') {
            return $this->redirectWithMessage(
                RedirectType::ERROR->value,
                'admin.languages.index',
            );
        }

        if ($code !== 'en' && $deleted = $language->delete()) {
            $destinationPath = dirname(app_path())."/lang/{$code}.json";
            File::delete($destinationPath);
        }

        if ($deleted) {
            $this->syncModels(SyncLanguageType::DELETE->value, boolval(SyncLanguageType::isQueueable()), $code);
        }

        return $this->redirectWithMessage(
            RedirectType::DELETE->value,
            'admin.languages.index',
        );
    }

    public function updateStatus(Language $language): JsonResponse
    {
        checkAdminHasPermissionAndThrowException('language.update');

        
        if (request('column') == 'is_default') {
            $isDefault = $language->is_default ? 0 : 1;
            if ($isDefault == 0) {
                $languages = Language::where('is_default', 1)->get();
                if ($languages->count() == 1) {
                    return response()->json([
                        'status' => false,
                        'message' => __('Should Have At Least One Default Language!')
                    ]);
                }
            }
            Language::where('is_default', 1)->update(['is_default' => 0]);
            
            $language->is_default = $isDefault;

            if ($language->status == 0) {
                $language->status = 1;
            }

            cache()->forget('defaultLanguage');
            session()->forget('lang');
        } elseif (request('column') == 'status') {
            $status = $language->status ? 0 : 1;

            if ($status == 0) {
                $languages = Language::where('status', 1)->get();
                if ($languages->count() == 1) {
                    return response()->json([
                        'status' => false,
                        'message' => __('Should Have At Least One Active Language!')
                    ]);
                }
                if ($language->code == session('lang')) {
                    session()->put('lang', getDefaultLanguage());
                }
            }
            if ($language->is_default == 1) {
                $language->status = 1;
            } else {
                $language->status = $status;
            }
        }

        $action = $language->save();
        Artisan::call('cache:clear');

        return response()->json([
            'status' => $action,
            'message' => $action ? __('Language Updated Successfully!') : __('Language Updating Failed!'),
        ]);
    }
    protected function getTextDirection($languageCode) {
        $rtlLanguageCodes = ["ar", "arc", "dv", "fa", "ha", "he", "khw", "ks", "ku", "ps", "ur", "yi"];
        if (in_array($languageCode, $rtlLanguageCodes)) {
            return 'rtl';
        }
        return 'ltr';
    }
}
