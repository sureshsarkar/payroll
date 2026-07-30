<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CloudStorageController extends Controller {
    public function store(Request $request) {
        $request->validate([
            '_token' => 'required',
            'file'   => [
                'required',
                'file',
                // FT-UPLOAD-1 fix (2026-05-27) — dropped svg (XSS).
                // CloudStorageController serves admin-uploaded assets via
                // public CDN/Wasabi URLs; SVG with <script> would execute
                // in every visitor's browser on the platform.
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,mp4,webm,mp3,m4a,zip',
                'max:51200', // 50 MB
            ],
            'source' => 'required|in:public,wasabi,aws,s3',
        ]);

        if ($request->hasFile('file') && $request->filled('source')) {
            // FT-UPLOAD-1 fix (2026-05-27) — extension allowlist also
            // drops svg to keep parity with the mimes rule above.
            $allowedExt = ['jpg','jpeg','png','webp','gif','pdf','doc','docx','xls','xlsx','ppt','pptx','mp4','webm','mp3','m4a','zip'];
            $extension = strtolower($request->file('file')->getClientOriginalExtension());
            if (!in_array($extension, $allowedExt, true)) {
                return response()->json(['status' => 'failed', 'message' => __('Disallowed file type')], 400);
            }
            $fileName = strtolower(config('app.name')) . date('-Y-m-d-H-i-s-') . rand(999, 9999) . '.' . $extension;

            // Store the file in the 'source' disk
            $path = $request->file('file')->storeAs('uploads', $fileName, $request->source);

            if($path){
                return response()->json([
                    'status'  => 'success',
                    'message' => __('Uploaded successfully.'),
                    'path'    => $path,
                ], 200);
            }

            return response()->json([
                'status'  => 'failed',
                'message' => __('Upload failed'),
            ], 200);
        }

        return response()->json([
            'status'  => 'failed',
            'message' => __('Upload failed'),
        ], 400);
    }
}
