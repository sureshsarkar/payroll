<?php

namespace App\Http\Controllers\Frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class TinymceImageUploadController extends Controller {
    public function upload(Request $request) {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|max:2048',
        ], [
            'file.required' => __('The image is required and must be an image file with a maximum size of 2048 kilobytes (2 MB).'),
            'file.image'    => __('The image must be an image file with a maximum size of 2048 kilobytes (2 MB).'),
            'file.max'      => __('The image must be an image file with a maximum size of 2048 kilobytes (2 MB).'),
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Upload the image using the file_upload helper function
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $url = file_upload($file, 'uploads/forum-images/');

            return response()->json(['location' => asset($url)]);
        }

        return response()->json(['error' => __('Image upload failed')], 422);
    }

    public function destroy(Request $request) {
        $request->validate(['file_path' => 'required|string|max:512']);

        $rel = preg_replace('/^.*\/(uploads\/forum-images\/[A-Za-z0-9_\-\.]+)$/', '$1', $request->input('file_path'));
        if (!preg_match('#^uploads/forum-images/[A-Za-z0-9_\-\.]+\.(jpg|jpeg|png|gif|webp)$#i', $rel)) {
            return response()->json(['error' => 'Invalid path'], 400);
        }

        $fullPath = public_path($rel);
        $real     = realpath($fullPath);
        $allowed  = realpath(public_path('uploads/forum-images'));
        if (!$real || !$allowed || !str_starts_with($real, $allowed)) {
            return response()->json(['error' => 'Path traversal blocked'], 400);
        }

        if (File::exists($fullPath)) {
            File::delete($fullPath);
            return response()->json(['success' => true]);
        }
        return response()->json(['error' => 'File not found'], 404);
    }
}
