<?php

namespace Modules\CertificateBuilder\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // FT-UPLOAD-3 (2026-05-28) — explicit mimes (no svg).
            // Certificate background renders to PDF for every student
            // completing the course — SVG with script could break the
            // PDF renderer (or worse, smuggle js into a webview).
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3000'],
            'title' => ['nullable', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:600'],
            'certificate_style' => ['nullable', 'in:enterprise,classic'],
            // 2026-07-09 — enterprise design variant + optional accent-colour override.
            'certificate_template' => ['nullable', 'in:classic,modern,royal'],
            'accent_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            // 2026-07-09 — typography & layout. Font is a DomPDF-safe key (mapped
            // to a built-in family) so the preview always matches the export.
            'font_family' => ['nullable', 'in:serif,sans,mono'],
            'text_align' => ['nullable', 'in:center,left'],
            'paper_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3000', 'dimensions:max_width=500,min_height=10'],
        ];
    }

function messages(): array{
    
    return [
        'background.image' => __('The background must be an image and cannot be empty, with a maximum size of 3000.'),
        'title.string' => __('The title must be a string with a maximum of 255 characters.'),
        'sub_title.string' => __('The sub title must be a string with a maximum of 255 characters.'),
        'description.string' => __('The description must be a string with a maximum of 600 characters.'),
    ];
}

}
