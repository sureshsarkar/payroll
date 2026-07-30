<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-coach site-wide settings — one row per coach. Holds everything
 * that applies across ALL pages of the coach's marketing website:
 *
 *   - Favicon, analytics IDs (GA4, Meta Pixel, GTM)
 *   - Sticky CTA + WhatsApp floating buttons
 *   - Social media URLs
 *   - Custom CSS + custom <head> / <body> scripts (power users)
 *   - SEO defaults (title suffix, description, og image)
 *   - Site-wide footer config (JSON)
 *   - Nav CTA button text + URL
 *
 * Read via SiteSettingsService::for($coachId) which creates a default
 * row on first read (firstOrCreate).
 */
class CoachSiteSettings extends Model
{
    use HasFactory;

    protected $table = 'coach_site_settings';

    protected $fillable = [
        'coach_id',
        'favicon_url',
        'sticky_cta_enabled', 'sticky_cta_text', 'sticky_cta_url',
        'whatsapp_enabled',   'whatsapp_number', 'whatsapp_message',
        'social_facebook', 'social_instagram', 'social_youtube',
        'social_twitter',  'social_linkedin',  'social_tiktok',
        'social_pinterest',
        'analytics_ga4_id', 'analytics_meta_pixel_id', 'analytics_gtm_id',
        'custom_css', 'custom_head_scripts', 'custom_body_scripts',
        'seo_default_title_suffix', 'seo_default_description', 'seo_og_image_default',
        'footer_config',
        'nav_show_cta', 'nav_cta_text', 'nav_cta_url',
        'typography_config',
    ];

    protected $casts = [
        'sticky_cta_enabled' => 'boolean',
        'whatsapp_enabled'   => 'boolean',
        'nav_show_cta'       => 'boolean',
        'footer_config'      => 'array',
        'typography_config'  => 'array',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
