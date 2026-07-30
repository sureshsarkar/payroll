<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Certificate') }}</title>
    {{-- DomPDF-safe: built-in DejaVu serif/sans (no remote fonts), A4 landscape,
         solid colours (no gradients). Brand colour + logo + signature come from
         the owning coach, so the frame/seal/typography are consistent while the
         identity stays white-label. --}}
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: {{ $fontFamily ?? 'serif' }}; color: #1a1a1a; }
        .cert { position: relative; width: 100%; height: 560pt; background: #ffffff; }
        .paper { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: {{ $paperColor ?? '#faf7f0' }}; }
        .frame { position: absolute; top: 16pt; left: 16pt; right: 16pt; bottom: 16pt; border: 2pt solid {{ $brandColor }}; }
        .frame-2 { position: absolute; top: 22pt; left: 22pt; right: 22pt; bottom: 22pt; border: 0.8pt solid {{ $goldColor ?? '#b08d4f' }}; }
        .corner { position: absolute; width: 30pt; height: 30pt; }
        .corner-b { border-color: {{ $goldColor ?? '#b08d4f' }}; }
        .c-tl { top: 12pt; left: 12pt; border-top: 2pt solid {{ $goldColor ?? '#b08d4f' }}; border-left: 2pt solid {{ $goldColor ?? '#b08d4f' }}; }
        .c-tr { top: 12pt; right: 12pt; border-top: 2pt solid {{ $goldColor ?? '#b08d4f' }}; border-right: 2pt solid {{ $goldColor ?? '#b08d4f' }}; }
        .c-bl { bottom: 12pt; left: 12pt; border-bottom: 2pt solid {{ $goldColor ?? '#b08d4f' }}; border-left: 2pt solid {{ $goldColor ?? '#b08d4f' }}; }
        .c-br { bottom: 12pt; right: 12pt; border-bottom: 2pt solid {{ $goldColor ?? '#b08d4f' }}; border-right: 2pt solid {{ $goldColor ?? '#b08d4f' }}; }

        .inner { position: absolute; top: 40pt; left: 60pt; right: 60pt; text-align: center; }
        .brandrow { text-align: center; }
        .logo { height: 34pt; }
        .brandname { font-family: sans-serif; font-size: 13pt; font-weight: bold; letter-spacing: 0.5pt; color: #1a1a1a; margin-top: 5pt; }
        .eyebrow { font-family: sans-serif; font-size: 12pt; letter-spacing: 5pt; color: {{ $brandColor }}; margin-top: 18pt; font-weight: bold; }
        .eyebrow-sub { font-family: sans-serif; font-size: 8pt; letter-spacing: 3pt; color: {{ $goldColor ?? '#b08d4f' }}; margin-top: 4pt; }
        .rule { width: 54pt; border-top: 2pt solid {{ $goldColor ?? '#b08d4f' }}; margin: 12pt auto 0; }
        .lead { font-size: 11pt; font-style: italic; color: #6b6b6b; margin-top: 16pt; }
        .name { font-size: 40pt; color: {{ $brandColor }}; margin-top: 4pt; }
        .nameline { width: 300pt; border-top: 0.8pt solid {{ $goldColor ?? '#b08d4f' }}; margin: 8pt auto 0; }
        .body { font-size: 12pt; color: #2a2a2a; margin-top: 14pt; }
        .course { font-size: 20pt; font-weight: bold; color: #1a1a1a; margin-top: 8pt; }
        .meta { font-family: sans-serif; font-size: 9.5pt; color: #6b6b6b; margin-top: 8pt; letter-spacing: 0.3pt; }

        /* seal */
        .seal { position: absolute; top: 250pt; right: 90pt; width: 78pt; height: 78pt; text-align: center; }
        .seal-o { position: absolute; top: 0; left: 0; width: 78pt; height: 78pt; border: 2pt solid {{ $goldColor ?? '#b08d4f' }}; border-radius: 39pt; }
        .seal-i { position: absolute; top: 12pt; left: 12pt; width: 54pt; height: 54pt; background: {{ $brandColor }}; border-radius: 27pt; border: 1.5pt solid {{ $goldColor ?? '#b08d4f' }}; }
        .seal-m { position: absolute; top: 22pt; left: 0; width: 78pt; text-align: center; color: #ffffff; font-size: 18pt; font-weight: bold; }
        .seal-v { position: absolute; top: 44pt; left: 0; width: 78pt; text-align: center; color: {{ $goldColor ?? '#d8b877' }}; font-family: sans-serif; font-size: 6pt; letter-spacing: 1.5pt; }

        /* footer: 3-column table (DomPDF-friendly) */
        .footer { position: absolute; bottom: 46pt; left: 60pt; right: 60pt; }
        .footer table { width: 100%; }
        .footer td { vertical-align: bottom; text-align: center; }
        .sig-img { height: 26pt; }
        .sig-mark { font-size: 15pt; color: #1a1a1a; height: 26pt; }
        .sig-line { border-top: 0.8pt solid #555; width: 150pt; margin: 4pt auto 0; }
        .sig-name { font-family: sans-serif; font-size: 10pt; font-weight: bold; margin-top: 5pt; }
        .sig-role { font-family: sans-serif; font-size: 7.5pt; letter-spacing: 1pt; color: #6b6b6b; text-transform: uppercase; margin-top: 2pt; }
        .qr { width: 58pt; height: 58pt; }
        .cred-id { font-family: sans-serif; font-size: 7.5pt; color: #555; margin-top: 4pt; }
        .cred-verify { font-family: sans-serif; font-size: 6.5pt; letter-spacing: 0.5pt; color: {{ $brandColor }}; text-transform: uppercase; margin-top: 2pt; }

        /* ── Design variants (share the same structure, restyled) ── */
        .cert__band { display: none; }

        /* Text alignment (default centred; left keeps the seal/footer in place). */
        .cert[data-al="left"] .inner { text-align: left; }
        .cert[data-al="left"] .rule,
        .cert[data-al="left"] .nameline { margin-left: 0; margin-right: 0; }

        /* MODERN — clean, no ornate frame; accent band top. Paper colour is
           resolved in the controller (design default OR the coach's override). */
        .cert[data-tpl="modern"] .frame,
        .cert[data-tpl="modern"] .frame-2,
        .cert[data-tpl="modern"] .corner { display: none; }
        .cert[data-tpl="modern"] .cert__band { display: block; position: absolute; top: 0; left: 0; right: 0; height: 13pt; background: {{ $brandColor }}; }
        .cert[data-tpl="modern"] .inner { top: 62pt; }
        .cert[data-tpl="modern"] .eyebrow { letter-spacing: 6pt; }
        .cert[data-tpl="modern"] .eyebrow-sub { color: #9aa1a8; }
        .cert[data-tpl="modern"] .rule { background: {{ $brandColor }}; width: 40pt; }
        .cert[data-tpl="modern"] .nameline { background: #e3e8ea; }
        .cert[data-tpl="modern"] .seal-o { border-color: {{ $brandColor }}; }
        .cert[data-tpl="modern"] .seal-i { background: #ffffff; border-color: {{ $brandColor }}; }
        .cert[data-tpl="modern"] .seal-m { color: {{ $brandColor }}; }
        .cert[data-tpl="modern"] .seal-v { color: {{ $brandColor }}; }
        .cert[data-tpl="modern"] .sig-line { border-top-color: #cbd2d6; }

        /* ROYAL — ornate: heavier gold frame, larger corners, italic name.
           (Paper colour resolved in the controller.) */
        .cert[data-tpl="royal"] .frame { border-width: 3pt; }
        .cert[data-tpl="royal"] .frame-2 { top: 20pt; left: 20pt; right: 20pt; bottom: 20pt; border-width: 1.5pt; }
        .cert[data-tpl="royal"] .corner { width: 42pt; height: 42pt; }
        .cert[data-tpl="royal"] .eyebrow { letter-spacing: 6pt; }
        .cert[data-tpl="royal"] .name { font-style: italic; }
        .cert[data-tpl="royal"] .seal-o { border-width: 3pt; }
    </style>
</head>
<body>
<div class="cert" data-tpl="{{ $template ?? 'classic' }}" data-al="{{ $textAlign ?? 'center' }}">
    <div class="paper"></div>
    <div class="cert__band"></div>
    <div class="frame"></div>
    <div class="frame-2"></div>
    <div class="corner c-tl"></div><div class="corner c-tr"></div>
    <div class="corner c-bl"></div><div class="corner c-br"></div>

    <div class="inner">
        <div class="brandrow">
            @if(!empty($logoData))
                <img class="logo" src="{{ $logoData }}" alt="">
            @endif
            <div class="brandname">{{ $brandName }}</div>
        </div>

        <div class="eyebrow">CERTIFICATE OF COMPLETION</div>
        <div class="eyebrow-sub">THIS IS PROUDLY PRESENTED TO</div>
        <div class="rule"></div>

        <div class="name">{{ $studentName }}</div>
        <div class="nameline"></div>

        <div class="body">has successfully completed the course</div>
        <div class="course">{{ $courseTitle }}</div>
        <div class="meta">Completed on {{ $dateText }}</div>
    </div>

    <div class="seal">
        <div class="seal-o"></div>
        <div class="seal-i"></div>
        <div class="seal-m">{{ strtoupper(mb_substr($brandName, 0, 1)) }}</div>
        <div class="seal-v">VERIFIED</div>
    </div>

    <div class="footer">
        <table>
            <tr>
                <td style="width:33%;">
                    @if(!empty($signatureData))
                        <img class="sig-img" src="{{ $signatureData }}" alt="">
                    @else
                        <div class="sig-mark">{{ $instructorName }}</div>
                    @endif
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $instructorName }}</div>
                    <div class="sig-role">Instructor</div>
                </td>
                <td style="width:34%;">
                    @if(!empty($qrData))
                        <img class="qr" src="{{ $qrData }}" alt="verify">
                    @endif
                    <div class="cred-id">ID · {{ $credentialUid }}</div>
                    <div class="cred-verify">{{ $verifyLabel }}</div>
                </td>
                <td style="width:33%;">
                    <div class="sig-mark">{{ $brandName }}</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $brandName }}</div>
                    <div class="sig-role">Issuing Academy</div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
