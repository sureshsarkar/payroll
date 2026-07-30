<?php

namespace Modules\Course\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseCategoryUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // FT-UPLOAD-3 (2026-05-28) — mirror store rules.
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'status' => ['nullable', 'boolean'],
            'code' => ['required', 'exists:languages,code'],
        ];
    }
}
