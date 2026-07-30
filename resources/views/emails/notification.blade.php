<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ $title }}</title>
    <style media="all" type="text/css">
        body { font-family: Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; font-size: 15px; line-height: 1.5; margin: 0; padding: 0; background-color: #f4f5f6; color: #1f2937; }
        table { border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; }
        .body { background-color: #f4f5f6; width: 100%; }
        .container { margin: 0 auto !important; max-width: 600px; padding: 0; padding-top: 24px; width: 600px; }
        .content { box-sizing: border-box; display: block; margin: 0 auto; max-width: 600px; padding: 0; }
        .main { background: #ffffff; border: 1px solid #eaebed; border-radius: 16px; width: 100%; overflow: hidden; }
        .logo { text-align: center; padding: 28px 24px 12px; }
        .logo img { max-height: 48px; }
        .icon-wrap { text-align: center; padding: 18px 24px 0; }
        .icon-circle {
            display: inline-block;
            width: 64px; height: 64px; line-height: 64px;
            border-radius: 50%;
            background-color: {{ $iconColor ?? '#10b981' }};
            color: #ffffff;
            text-align: center;
            font-size: 26px;
            font-weight: 700;
            font-family: Helvetica, Arial, sans-serif;
        }
        .heading { padding: 18px 32px 4px; text-align: center; }
        .heading h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #1c1a4a;
            line-height: 1.3;
        }
        .body-text { padding: 8px 32px 24px; text-align: center; }
        .body-text p { color: #4b5563; font-size: 15px; line-height: 1.6; margin: 0; }
        .cta { padding: 8px 32px 32px; text-align: center; }
        .cta a {
            display: inline-block;
            padding: 12px 28px;
            background: {{ $brandColor ?? '#10b981' }};
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        .signature { padding: 4px 32px 24px; text-align: left; color: #4b5563; font-size: 14px; line-height: 1.6; }
        .signature p { margin: 6px 0; }
        .footer { clear: both; padding: 24px 16px; text-align: center; width: 100%; }
        .footer p { color: #9a9ea6; font-size: 12px; margin: 6px 0; line-height: 1.5; }
        .footer a { color: {{ $brandColor ?? '#10b981' }}; text-decoration: none; }
        .greeting { padding: 4px 32px 0; text-align: left; color: #4b5563; font-size: 14px; }
        @media only screen and (max-width: 640px) {
            .container { padding: 0 !important; padding-top: 8px !important; width: 100% !important; }
            .main { border-radius: 0 !important; }
            .heading, .body-text, .cta { padding-left: 18px !important; padding-right: 18px !important; }
        }
    </style>
</head>
<body>
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body">
        <tr>
            <td>&nbsp;</td>
            <td class="container">
                <div class="content">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="main">
                        <tr>
                            <td class="logo">
                                @if (!empty($appLogo))
                                    <img src="{{ $appLogo }}" alt="{{ $appName ?? '' }}">
                                @else
                                    <strong style="font-size:20px; color:#1c1a4a;">{{ $appName ?? 'MBS' }}</strong>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="icon-wrap">
                                <div class="icon-circle">
                                    {{-- Email clients strip Font Awesome — fall back to a single character glyph based on icon class --}}
                                    @php
                                        $glyph = match (true) {
                                            str_contains($icon, 'check')      => '✓',
                                            str_contains($icon, 'xmark'),
                                            str_contains($icon, 'times')      => '✕',
                                            str_contains($icon, 'trophy')     => '★',
                                            str_contains($icon, 'video')      => '▶',
                                            str_contains($icon, 'money'),
                                            str_contains($icon, 'wallet')     => '💰',
                                            str_contains($icon, 'rotate'),
                                            str_contains($icon, 'undo')       => '↺',
                                            str_contains($icon, 'graduate'),
                                            str_contains($icon, 'student')    => '🎓',
                                            str_contains($icon, 'hourglass') => '⧗',
                                            str_contains($icon, 'exclamation'),
                                            str_contains($icon, 'warning')    => '!',
                                            str_contains($icon, 'comment'),
                                            str_contains($icon, 'message')    => '💬',
                                            str_contains($icon, 'clipboard'),
                                            str_contains($icon, 'question')   => '?',
                                            str_contains($icon, 'receipt')    => '🧾',
                                            default                            => '🔔',
                                        };
                                    @endphp
                                    {{ $glyph }}
                                </div>
                            </td>
                        </tr>
                        @if (!empty($recipientName))
                            <tr>
                                <td class="greeting">
                                    Hi {{ $recipientName }},
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="heading">
                                <h1>{{ $title }}</h1>
                            </td>
                        </tr>
                        @if (!empty($bodyHtml))
                            <tr>
                                <td class="body-text" style="text-align:left;">
                                    {!! clean($bodyHtml) !!}
                                </td>
                            </tr>
                        @elseif (!empty($body))
                            <tr>
                                <td class="body-text">
                                    <p>{{ $body }}</p>
                                </td>
                            </tr>
                        @endif
                        @if (!empty($url))
                            <tr>
                                <td class="cta">
                                    <a href="{{ $url }}" target="_blank">{{ $ctaLabel ?? 'View details' }}</a>
                                </td>
                            </tr>
                        @endif
                        @if (!empty($emailSignature))
                            {{-- Coach's brand email signature (admin-editable, sanitized). --}}
                            <tr>
                                <td class="signature">
                                    {!! clean((string) $emailSignature) !!}
                                </td>
                            </tr>
                        @endif
                    </table>
                    <div class="footer">
                        @if (!empty($footerText))
                            <p>{{ $footerText }}</p>
                        @endif
                        @if (!empty($supportEmail))
                            <p>Need help? <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>@if(!empty($supportPhone)) &nbsp;·&nbsp; {{ $supportPhone }}@endif</p>
                        @endif
                        <p>You are receiving this email because you have an account at {{ $appName ?? 'our platform' }}.</p>
                        @if (!empty($preferencesUrl))
                            <p><a href="{{ $preferencesUrl }}">Manage notification preferences</a></p>
                        @endif
                        <p>&copy; {{ date('Y') }} {{ $appName ?? '' }}. All rights reserved.</p>
                    </div>
                </div>
            </td>
            <td>&nbsp;</td>
        </tr>
    </table>
</body>
</html>
