<?php

namespace Modules\Language\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationController extends Controller
{
    public function translateAll(Request $request)
    {
        try {
            if (checkAdminHasPermission('language.translate')) {
                // FT-PATH-1 fix (2026-05-28) — CRITICAL: arbitrary file
                // read + write via path traversal.
                //
                // Original:
                //     $filePath = base_path('lang/' . $request->code . '.json');
                //     File::get($filePath);  // ← read any .json from disk
                //     File::put($filePath, json_encode(...));  // ← WRITE arbitrary JSON anywhere
                //
                // With `code = ../../etc/passwd` (or any traversal payload)
                // an authenticated admin holding `language.translate` could:
                //   • READ arbitrary .json files from anywhere the web user
                //     can reach (config exports, vendor secrets dumps, etc.),
                //   • WRITE arbitrary JSON content to any path the web
                //     user can write — including overwriting Laravel config
                //     cache, migration state files, or planting JSON
                //     payloads other components consume.
                //
                // The `language.translate` permission is held by Super
                // Admin + a few delegated roles, so this is "admin-side
                // privilege escalation to filesystem write" — bad enough
                // to fix immediately.
                //
                // Fix: validate `code` as a strict language code pattern
                // (letters / digits / hyphen / underscore, 2–10 chars).
                // This blocks every path-traversal payload (which need
                // `/`, `.`, or `\`) and also constrains the surface to
                // values that could plausibly be real locale codes
                // (`en`, `en-US`, `pt_BR`, `zh-Hans`, etc.).
                $request->validate([
                    'code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{2,10}$/'],
                ], [
                    'code.regex' => __('Invalid language code'),
                ]);

                // Defence in depth: also resolve the final path and
                // assert it sits under the lang directory. This catches
                // any future regression of the regex above.
                $langDir  = realpath(base_path('lang')) ?: base_path('lang');
                $filePath = $langDir . DIRECTORY_SEPARATOR . $request->code . '.json';
                $resolved = realpath($filePath) ?: $filePath;
                if (strncmp($resolved, $langDir . DIRECTORY_SEPARATOR, strlen($langDir) + 1) !== 0
                    && $resolved !== $filePath) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Invalid language code'),
                    ], 422);
                }

                if (File::exists($filePath)) {

                    $jsonData = json_decode(File::get($filePath), true);
                    $keys = array_keys($jsonData);
                    $values = array_values($jsonData);

                    $delimiter = "\n\t";
                    $allText = implode($delimiter, $values);

                    // Split the text into chunks
                    $chunks = $this->splitIntoChunksWithDelimiter($allText, 5000, $delimiter);

                    $translatedChunks = $this->translateChunks($chunks, $request->code);
                    // Combine the translated chunks back into a single string
                    $translatedText = implode($delimiter, $translatedChunks);
                    $translatedValues = explode($delimiter, $translatedText);
                    if (count($translatedValues) == count($keys)) {
                        $translatedData = array_combine($keys, $translatedValues);

                        // Save the translated JSON back to the file
                        File::put($filePath, json_encode($translatedData, JSON_PRETTY_PRINT));

                        return response()->json([
                            'success' => true,
                            'message' => __('All texts translated successfully!'),
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => __('Something went wrong while translating the file.'),
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => __('File Not Found!'),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => __('Permission Denied!'),
            ], 403);
        } catch (\Exception $e) {
            logger($e);
            return response()->json([
                'success' => false,
                'message' => __('An error occurred while translating the file.') . ' ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Split the text into chunks of a specified size, ensuring chunks end with a delimiter.
     * @prams $text
     * @prams $maxChunkSize
     * @prams $delimiter
     */
    private function splitIntoChunksWithDelimiter($text, $maxChunkSize, $delimiter)
    {
        $chunks = [];
        $textLength = strlen($text);
        $start = 0;

        while ($start < $textLength) {
            $end = $start + $maxChunkSize;
            if ($end >= $textLength) {
                $chunks[] = substr($text, $start);
                break;
            }

            // Find the last occurrence of the delimiter before the end point
            $end = strrpos(substr($text, $start, $maxChunkSize), $delimiter) + $start + strlen($delimiter);

            if ($end <= $start) {
                // If no delimiter is found, use the maxChunkSize as the end point
                $end = $start + $maxChunkSize;
            }

            $chunks[] = substr($text, $start, $end - $start);
            $start = $end;
        }

        return $chunks;
    }
    // translate chunks
    private function translateChunks($chunks, $lang_code)
    {
        $translatedChunks = [];
        foreach ($chunks as $chunk) {
            $tr = new GoogleTranslate($lang_code);
            $translatedData = $tr->translate($chunk);
            $translatedChunks[] = $translatedData;
        }
        return $translatedChunks;
    }

    public function translateSingleText(Request $request)
    {
        if (checkAdminHasPermission('language.single.translate')) {
            $tr = new GoogleTranslate($request->lang);
            $afterTrans = $tr->translate($request->text);

            return response()->json($afterTrans);
        }

        return response()->json(__('Permission Denied!'), 403);
    }
}
