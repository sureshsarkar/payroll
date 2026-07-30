@extends('frontend.layouts.master')

@section('meta_title', $seo_setting['home_page']['seo_title'])
@section('meta_description', $seo_setting['home_page']['seo_description'])
@section('meta_keywords', '')

@section('contents')


    @if ($sectionSetting?->hero_section)
        <!-- banner-area -->
        {{-- @include('frontend.home.main.sections.banner-area') --}}
        <!-- banner-area-end -->
    @endif
    <style>
        :root {
            --navy: #1a2e5a;
            --navy2: #243f7a;
            --sky: #3a7bd5;
            --sky2: #5b9af5;
            --gold: #e87c2b;
            --gold2: #f5a040;
            --white: #fff;
            --off: #f7f9fc;
            --gray: #6b7a99;
            --lgray: #e4eaf5;
            --green: #27a96c;
            --text: #0d1d3e;
        }





        /* HERO */
        .hero {
            position: relative;
            overflow: hidden;
            padding: 150px 6% 0;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 60px;

            min-height: 700px;
            background: linear-gradient(135deg, #08132a, #163878, #3070c8);
        }

        /* Atmospheric layers */
        .hero-atmo {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 1000px 600px at 50% 120%, rgba(100, 170, 255, 0.28) 0%, transparent 55%),
                radial-gradient(ellipse 700px 400px at 10% 80%, rgba(70, 120, 220, 0.18) 0%, transparent 60%),
                radial-gradient(ellipse 500px 300px at 90% 40%, rgba(232, 124, 43, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse 300px 200px at 50% 45%, rgba(255, 255, 255, 0.04) 0%, transparent 60%);
        }

        /* Cloud blobs */
        .cloud {
            position: absolute;
            border-radius: 50%;
            filter: blur(32px);
            pointer-events: none;
            background: rgba(200, 220, 255, 0.06)
        }

        .c1 {
            width: 380px;
            height: 130px;
            top: 52%;
            left: 0;
            animation: drift 20s ease-in-out infinite
        }

        .c2 {
            width: 280px;
            height: 100px;
            top: 46%;
            right: 2%;
            animation: drift 25s ease-in-out infinite reverse
        }

        .c3 {
            width: 220px;
            height: 80px;
            top: 62%;
            left: 40%;
            animation: drift 17s ease-in-out infinite 4s
        }

        .c4 {
            width: 160px;
            height: 60px;
            top: 40%;
            left: 25%;
            animation: drift 14s ease-in-out infinite 2s;
            background: rgba(255, 255, 255, 0.04)
        }

        @keyframes drift {

            0%,
            100% {
                transform: translateX(0) translateY(0)
            }

            50% {
                transform: translateX(22px) translateY(-8px)
            }
        }

        .hero-content {
            position: relative;
            z-index: 3
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            padding: 7px 20px;
            border-radius: 40px;
            margin-bottom: 24px;
            backdrop-filter: blur(8px);
        }

        .hero-eyebrow .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 8px var(--gold)
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            font-weight: 900;
            color: #fff;
            line-height: 1.13;
            max-width: 880px;
            margin: 0 auto 22px;
            text-shadow: 0 2px 32px rgba(0, 0, 0, 0.3);
        }

        .hero h1 .acc {
            color: var(--gold2)
        }

        .hero-sub {
            color: rgba(255, 255, 255, 0.78);
            font-size: clamp(0.96rem, 1.9vw, 1.14rem);
            max-width: 590px;
            margin: 0 auto 38px;
            line-height: 1.72;
            font-weight: 400;
        }

        .hero-sub em {
            color: #fff;
            font-style: italic;
            font-weight: 600
        }

        .cta-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 9px
        }

        .btn-hero {
            display: inline-block;
            text-decoration: none;
            background: linear-gradient(135deg, var(--gold), var(--gold2));
            color: #fff;
            font-weight: 800;
            0 8px 36px #001b50,
            0 0 0 3px #f5a04033 font-size: 1.1rem;
            padding: 8px 25px;
            border-radius: 14px;
            box-shadow: 0 8px 36px #001b50, 0 0 0 3px #f5a04033;
            transition: all .22s;
            letter-spacing: 0.01em;
        }

        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 48px #001b50, 0 0 0 5px #f5a04033
        }

        .hero-footnote {
            color: rgba(255, 255, 255, 0.42);
            font-size: 0.78rem;
            letter-spacing: 0.04em
        }

        /* HERO STAGE */
        .hero-stage {
            position: relative;
            z-index: 2;
            margin-top: 50px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 0;
        }

        .desk-glow {
            position: absolute;
            bottom: 55px;
            left: 50%;
            transform: translateX(-50%);
            width: 75%;
            height: 80px;
            background: radial-gradient(ellipse, rgba(58, 123, 213, 0.35) 0%, transparent 65%);
            filter: blur(24px);
            pointer-events: none;
        }

        .desk {
            position: absolute;
            bottom: 0;
            left: -15%;
            right: -15%;
            height: 120px;
            background: linear-gradient(180deg, #7a5230 0%, #5a3820 50%, #3a2210 100%);
            border-radius: 60% 60% 0 0 / 30px 30px 0 0;
            box-shadow: inset 0 10px 30px rgba(0, 0, 0, 0.35), 0 -2px 0 rgba(255, 255, 255, 0.05);
        }

        .desk::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: repeating-linear-gradient(90deg, transparent, transparent 70px, rgba(255, 255, 255, 0.02) 70px, rgba(255, 255, 255, 0.02) 72px);
        }

        .desk::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 60% 60% 0 0 / 4px 4px 0 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        }

        /* ── PHONE ── */
        .phone-wrap {
            position: relative;
            z-index: 4;
            transform: rotate(-6deg) translateY(-8px);
            margin-right: -36px;
            flex-shrink: 0;
        }

        .phone {
            width: 192px;
            background: #0a0a0a;
            border-radius: 34px;
            border: 7px solid #1c1c1e;
            box-shadow: 0 0 0 1px #2c2c2e, 0 32px 70px rgba(0, 0, 0, 0.75), inset 0 1px 0 rgba(255, 255, 255, 0.07);
            overflow: hidden;
        }

        .ph-notch {
            height: 24px;
            background: #0a0a0a;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .ph-pill {
            width: 68px;
            height: 9px;
            background: #161618;
            border-radius: 5px
        }

        .ph-scr {
            background: linear-gradient(155deg, #0e1e4a, #1a3a8a, #2456b8);
            padding: 12px 11px 16px;
            min-height: 320px;
        }

        .ph-hdr {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px
        }

        .ph-av {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--gold), var(--gold2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            color: #fff;
            box-shadow: 0 3px 10px rgba(232, 124, 43, 0.4);
        }

        .ph-nm {
            font-size: 0.7rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.35
        }

        .ph-sb {
            font-size: 0.58rem;
            color: rgba(255, 255, 255, 0.55);
            font-weight: 400
        }

        .ph-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 11px;
            margin-bottom: 9px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(12px);
        }

        .ph-thumb {
            border-radius: 10px;
            height: 78px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #15906a, #1280b8);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .ph-thumb-txt {
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.64rem;
            font-weight: 700;
            text-align: center;
            line-height: 1.35;
            z-index: 1;
            padding: 4px;
        }

        .ph-thumb::after {
            content: '▶';
            position: absolute;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
            backdrop-filter: blur(6px);
            border: 1.5px solid rgba(255, 255, 255, 0.3);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ph-t {
            color: #fff;
            font-size: 0.67rem;
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.4
        }

        .ph-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold2));
            color: #fff;
            font-weight: 800;
            font-size: 0.68rem;
            text-align: center;
            padding: 9px;
            border-radius: 9px;
            box-shadow: 0 3px 12px rgba(232, 124, 43, 0.45);
        }

        .ph-mini {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 9px 11px;
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .ph-mini-av {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--sky2), var(--green));
        }

        .ph-mini-txt {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.62rem;
            line-height: 1.55
        }

        .ph-mini-txt b {
            color: #fff;
            font-weight: 700
        }

        /* ── LAPTOP ── */
        .laptop-wrap {
            position: relative;
            z-index: 3;
            flex-shrink: 0
        }

        .laptop {
            width: 570px;
            background: #181818;
            border-radius: 14px 14px 0 0;
            border: 7px solid #242424;
            box-shadow: 0 0 0 1px #333, 0 32px 90px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            overflow: hidden;
        }

        .lp-bezel {
            height: 18px;
            background: #181818;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .lp-cam {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #0f0f0f;
            border: 1px solid #2c2c2c
        }

        .lp-scr {
            background: #f4f6fb;
            overflow: hidden
        }

        .lp-topbar {
            background: var(--navy);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .lp-brand {
            color: #fff;
            font-family: 'Playfair Display', serif;
            font-size: 0.82rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .lp-brand-dot {
            width: 22px;
            height: 22px;
            background: var(--gold);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.55rem;
            font-weight: 900;
            color: #fff;
        }

        .lp-nav-tabs {
            display: flex;
            gap: 5px
        }

        .lp-tab {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 6px;
            padding: 4px 11px;
            font-size: 0.6rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            cursor: pointer;
            transition: all .15s;
        }

        .lp-tab.on {
            background: rgba(255, 255, 255, 0.26);
            color: #fff;
            font-weight: 700
        }

        .lp-enroll {
            background: var(--gold);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 5px 13px;
            border-radius: 7px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(232, 124, 43, 0.4);
        }

        /* Content */
        .lp-body {
            display: flex;
            min-height: 275px
        }

        .lp-side {
            width: 168px;
            flex-shrink: 0;
            background: #fff;
            padding: 18px 14px;
            border-right: 1px solid #e8eef8;
        }

        .lp-sw {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 3px;
            line-height: 1.35
        }

        .lp-ss {
            font-size: 0.58rem;
            color: var(--gray);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 4px
        }

        .lp-ss::before {
            content: '✓';
            color: var(--green);
            font-weight: 800
        }

        .lp-si {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 9px;
            margin-bottom: 3px;
            font-size: 0.63rem;
            color: var(--navy);
            font-weight: 500;
            cursor: pointer;
            transition: all .15s;
        }

        .lp-si:hover,
        .lp-si.on {
            background: #edf2fc;
            color: var(--sky)
        }

        .lp-ico {
            width: 24px;
            height: 24px;
            border-radius: 7px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .ic1 {
            background: #edf2fc
        }

        .ic2 {
            background: #e8f7f1
        }

        .ic3 {
            background: #fff4e8
        }

        .ic4 {
            background: #f3eef8
        }

        .lp-si.on .lp-ico {
            background: linear-gradient(135deg, var(--sky), var(--sky2));
            box-shadow: 0 2px 8px rgba(58, 123, 213, 0.3)
        }

        .lp-main {
            flex: 1;
            padding: 16px;
            background: #f7f9fc
        }

        .lp-banner {
            background: linear-gradient(135deg, var(--navy), var(--sky2));
            border-radius: 12px;
            padding: 14px 16px;
            color: #fff;
            margin-bottom: 11px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .lp-banner::after {
            content: '🎓';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 30px;
            opacity: 0.2;
        }

        .lp-banner h4 {
            font-size: 0.76rem;
            font-weight: 700;
            margin-bottom: 3px;
            color: #fff;
        }

        .lp-banner p {
            font-size: 0.6rem;
            opacity: 0.82;
            line-height: 1.4;
            color: #fff;
        }

        .lp-chip {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.57rem;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.3);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .lp-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 7px;
            margin-bottom: 9px
        }

        .lp-stat {
            background: #fff;
            border-radius: 9px;
            padding: 9px;
            border: 1px solid #e8eef8
        }

        .lp-sn {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--navy);
            line-height: 1
        }

        .lp-sl {
            font-size: 0.54rem;
            color: var(--gray);
            margin-top: 2px
        }

        .lp-su {
            font-size: 0.53rem;
            color: var(--green);
            font-weight: 700;
            margin-top: 2px
        }

        .lp-courses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px
        }

        .lp-cc {
            background: #fff;
            border-radius: 9px;
            overflow: hidden;
            border: 1px solid #e8eef8
        }

        .lp-cc-img {
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.54rem;
            color: #fff;
            font-weight: 600
        }

        .lp-cc-img.ci1 {
            background: linear-gradient(135deg, #18906a, #1a72c0)
        }

        .lp-cc-img.ci2 {
            background: linear-gradient(135deg, #8a28e0, #3a68d0)
        }

        .lp-cc-bd {
            padding: 7px 8px
        }

        .lp-cc-t {
            font-size: 0.58rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 5px;
            line-height: 1.3
        }

        .prog-bg {
            height: 4px;
            background: #e8eef8;
            border-radius: 2px
        }

        .prog {
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(90deg, var(--sky), var(--sky2))
        }

        /* WAVE */
        .wave {
            line-height: 0;
            background: linear-gradient(180deg, #4888d8, #3a78d0)
        }

        .wave svg {
            display: block;
            width: 100%
        }

        /* FEATURES */
        .feats {
            background: #fff;
            padding: 62px 6% 58px
        }

        .feats-in {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            max-width: 1060px;
            margin: 0 auto
        }

        .fi {
            display: flex;
            gap: 18px;
            align-items: flex-start;
            padding-right: 36px
        }

        .fi:not(:last-child) {
            border-right: 1px solid var(--lgray);
            margin-right: 36px
        }

        .fi-icon {
            width: 62px;
            height: 62px;
            border-radius: 15px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.09);
        }

        .ic-b {
            background: linear-gradient(135deg, #3a7bd5, #5b9af5);
            color: #fff
        }

        .ic-o {
            background: linear-gradient(135deg, #e87c2b, #f5a040);
            color: #fff
        }

        .ic-g {
            background: linear-gradient(135deg, #27a96c, #3dd68c);
            color: #fff
        }

        .fi-tt {
            font-size: 1rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 7px;
            line-height: 1.25
        }

        .fi-dd {
            font-size: 0.86rem;
            color: var(--gray);
            line-height: 1.68
        }

        /* PLATFORM */
        .plat {
            background: var(--off);
            padding: 40px 6%
        }

        .sec-hd {
            text-align: center;
            margin-bottom: 54px
        }

        .pill {
            display: inline-block;
            background: linear-gradient(135deg, var(--navy), var(--sky));
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 5px 18px;
            border-radius: 30px;
            margin-bottom: 18px;
        }

        .sec-hd h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3.5vw, 2.6rem);
            font-weight: 900;
            color: var(--navy);
            margin-bottom: 14px;
            line-height: 1.18;
        }

        .sec-hd p {
            color: var(--gray);
            font-size: 1rem;
            max-width: 520px;
            margin: 0 auto;
            line-height: 1.68
        }

        .plat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 26px;
            max-width: 1060px;
            margin: 0 auto
        }

        .pc {
            background: #fff;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 2px 20px rgba(26, 46, 90, 0.06);
            border: 1px solid rgba(26, 46, 90, 0.05);
            transition: transform .25s, box-shadow .25s;
            cursor: pointer;
        }

        .pc:hover {
            transform: translateY(-8px);
            box-shadow: 0 18px 52px rgba(26, 46, 90, 0.14)
        }

        .pc-top {
            height: 215px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .pc-top.bg1 {
            background: linear-gradient(150deg, #d8e8fd, #b8d0fa, #e0ecfe)
        }

        .pc-top.bg2 {
            background: linear-gradient(150deg, #cce8f8, #a8d8f4, #d5eefa)
        }

        .pc-top.bg3 {
            background: linear-gradient(150deg, #fde8c8, #f8d8a0, #fef0d8)
        }

        /* Course mockup */
        .csm {
            width: 84%;
            max-width: 295px;
            background: #fff;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 8px 34px rgba(0, 0, 0, 0.14);
            border: 1px solid rgba(0, 0, 0, 0.04)
        }

        .csm-tp {
            background: var(--navy);
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .csm-lg {
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            font-family: 'Playfair Display', serif
        }

        .csm-ds {
            display: flex;
            gap: 4px
        }

        .csm-ds span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3)
        }

        .csm-bd {
            padding: 10px 12px
        }

        .csm-r {
            height: 9px;
            background: var(--lgray);
            border-radius: 3px;
            margin-bottom: 6px
        }

        .csm-r.w7 {
            width: 70%
        }

        .csm-r.w5 {
            width: 50%
        }

        .csm-r.h1 {
            background: linear-gradient(90deg, var(--sky), var(--sky2))
        }

        .csm-r.h2 {
            background: linear-gradient(90deg, var(--gold), var(--gold2));
            width: 65%
        }

        .csm-cg {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 8px
        }

        .csm-mc {
            background: var(--off);
            border-radius: 7px;
            padding: 7px;
            border: 1px solid var(--lgray)
        }

        .csm-mi {
            height: 28px;
            border-radius: 5px;
            margin-bottom: 5px
        }

        .csm-mi.m1 {
            background: linear-gradient(135deg, #27a96c, #2a7ad5)
        }

        .csm-mi.m2 {
            background: linear-gradient(135deg, #9a3ae8, #4a7ad5)
        }

        .csm-ml {
            height: 5px;
            background: var(--lgray);
            border-radius: 2px;
            margin-bottom: 3px
        }

        .csm-ml.ms {
            width: 60%
        }

        /* Coaching mockup */
        .cmk {
            width: 84%;
            max-width: 275px;
            background: #111;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 8px 34px rgba(0, 0, 0, 0.28)
        }

        .cm-vid {
            height: 135px;
            background: linear-gradient(135deg, #0c1e52, #1a4898, #2460c0);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative
        }

        .cm-vg {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            opacity: 0.45
        }

        .cm-vg div {
            background: linear-gradient(135deg, #162a60, #1a4880)
        }

        .cm-pl {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            z-index: 2;
            background: rgba(255, 255, 255, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: #fff;
            font-size: 15px;
            padding-left: 3px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.35);
        }

        .cm-ctrl {
            background: #1a1a1a;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .cm-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #fff;
            cursor: pointer
        }

        .cb-r {
            background: #e74c3c
        }

        .cb-g {
            background: #27a96c
        }

        .cb-b {
            background: var(--sky)
        }

        .cm-t {
            color: rgba(255, 255, 255, 0.45);
            font-size: 0.57rem;
            margin-left: auto;
            font-weight: 600
        }

        .cm-ppl {
            background: #141414;
            padding: 6px 12px;
            display: flex;
            gap: 5px;
            align-items: center
        }

        .cm-chip {
            background: #222;
            border-radius: 20px;
            padding: 3px 9px;
            font-size: 0.55rem;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 4px
        }

        .cm-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%
        }

        /* Certificate mockup */
        .crt {
            width: 80%;
            max-width: 244px;
            background: #fff;
            border-radius: 13px;
            box-shadow: 0 8px 34px rgba(0, 0, 0, 0.13);
            border: 2px solid #d4a84a;
            padding: 16px
        }

        .crt-in {
            border: 1.5px dashed #c8a040;
            border-radius: 10px;
            padding: 14px;
            text-align: center
        }

        .crt-lbl {
            font-size: 0.59rem;
            color: var(--gold);
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 7px
        }

        .crt-seal {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f5c840, #e8a020);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 9px;
            box-shadow: 0 3px 12px rgba(232, 160, 32, 0.38);
            font-size: 22px
        }

        .crt-tt {
            font-family: 'Playfair Display', serif;
            font-size: 0.74rem;
            color: var(--navy);
            font-weight: 800;
            margin-bottom: 5px;
            line-height: 1.35
        }

        .crt-div {
            height: 1px;
            background: linear-gradient(90deg, transparent, #d4a84a, transparent);
            margin: 8px 0
        }

        .crt-nm {
            font-size: 0.7rem;
            color: var(--navy);
            font-weight: 700;
            margin-bottom: 3px
        }

        .crt-sb {
            font-size: 0.56rem;
            color: var(--gray)
        }

        .crt-sig {
            margin-top: 10px;
            padding-top: 9px;
            border-top: 1px solid #eed8a0;
            font-family: 'Playfair Display', serif;
            font-size: 0.65rem;
            color: var(--navy);
            opacity: 0.5;
            font-style: italic
        }

        .pc-foot {
            padding: 20px 22px 24px;
            text-align: center
        }

        .pc-foot h3 {
            font-size: 1.06rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 8px
        }

        .pc-foot p {
            font-size: 0.85rem;
            color: var(--gray);
            line-height: 1.62
        }

        /* TESTIMONIAL */
        .testi {
            background: #fff;
            padding: 20px 6%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 50px;
            flex-wrap: wrap;
            position: relative;
        }

        .testi--box {
            padding: 10px 30px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 40px;

            max-width: 900px;
            width: 100%;
        }

        .testi::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(ellipse 700px 350px at 25% 50%, rgba(58, 123, 213, 0.04) 0%, transparent 65%);
        }

        .av-wrap {
            flex-shrink: 0;
            position: relative
        }

        .av-wrap img {
            border-radius: 50%;
        }

        .av-ring {
            position: absolute;
            inset: -12px;
            border-radius: 50%;
            border: 1.5px dashed rgba(232, 124, 43, 0.3);
            animation: spin 22s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .av {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e87c2b, #5b9af5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 54px;
            box-shadow: 0 10px 38px rgba(58, 123, 213, 0.2);
            border: 5px solid #fff;
            outline: 3px solid rgba(232, 124, 43, 0.15);
        }

        .tb {
            max-width: 580px;
            position: relative;
            z-index: 1
        }

        .qm {
            font-family: 'Playfair Display', serif;
            font-size: 5.5rem;
            color: var(--gold);
            line-height: 1;
            opacity: 0.45;
            margin-bottom: -22px;
            display: block;
        }

        .qt {
            font-size: clamp(1rem, 2.1vw, 1.1rem);
            color: var(--navy);
            line-height: 1.72;
            font-weight: 500;
            margin-bottom: 22px;
        }

        .qt .hl {
            color: var(--sky);
            font-weight: 700
        }

        .qa {
            display: flex;
            align-items: center;
            gap: 16px
        }

        .qa-stars {
            color: #f5c840;
            font-size: 1rem;
            letter-spacing: 2px
        }

        .qa-info strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--navy)
        }

        .qa-info span {
            font-size: 0.82rem;
            color: var(--gray);
            font-style: italic
        }

        /* TRUST */
        .trust-section {

            padding: 60px 20px;

            background: #102958;

            text-align: center;

        }

        /* title row */

        .trust-title {

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;

            margin-bottom: 40px;

        }

        .line {

            flex: 1;
            height: 1px;
            background: #d9dee7;

            max-width: 300px;

        }

        /* ribbon badge */

        .badge {

            background: linear-gradient(135deg, #243f7a, #3a7bd5);

            color: white;

            padding: 10px 30px;

            border-radius: 30px;

            font-weight: 600;

            font-size: 16px;

            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);

        }

        /* icons */

        .trust-icons {

            display: flex;
            justify-content: center;
            gap: 25px;

            flex-wrap: wrap;

        }

        .icon {

            width: 70px;
            height: 70px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: white;

            border-radius: 15px;

            font-size: 28px;

            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);

            transition: 0.3s;

        }

        .icon:hover {

            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);

        }

        /* ANIMATIONS */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(32px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .hero-content>* {
            animation: fadeUp .75s ease both
        }

        .hero-eyebrow {
            animation-delay: .02s
        }

        .hero h1 {
            animation-delay: .14s
        }

        .hero-sub {
            animation-delay: .26s
        }

        .cta-group {
            animation-delay: .4s
        }

        .hero-stage {
            animation: fadeUp 1s .52s ease both
        }

        .fi,
        .pc {
            opacity: 0;
            transform: translateY(26px);
            transition: opacity .65s ease, transform .65s ease
        }

        .fi.vis,
        .pc.vis {
            opacity: 1;
            transform: translateY(0)
        }

        @media(max-width:960px) {
            .feats-in {
                grid-template-columns: 1fr
            }

            .fi:not(:last-child) {
                border-right: 0;
                border-bottom: 1px solid var(--lgray);
                padding-right: 0;
                padding-bottom: 28px;
                margin-right: 0;
                margin-bottom: 28px
            }

            .plat-grid {
                grid-template-columns: 1fr;
                max-width: 420px;
                margin: 0 auto
            }

            .phone-wrap {
                display: none
            }

            .laptop {
                width: 90vw;
                max-width: 520px
            }

            .nav-mid {
                display: none
            }
        }

        @media(max-width:600px) {
            nav {
                padding: 0 4%
            }

            .hero {
                padding: 54px 4% 0
            }

            .feats,
            .plat,
            .testi {
                padding: 52px 4%
            }
        }
    </style>


    <!-- HERO -->
    <section class="hero">
     <div class="container">
        <div class="row">
         <div class="col-lg-6 col-md-12 col-12">
            <div class="hero-content">
                <div class="hero-content-inn">
            <div class="hero-eyebrow"><span class="dot"></span>#1 Platform
                for Online Educators</div>
            <h1>Your Expertise. Your Brand.<br><span class="acc">Your
                    Digital Platform.</span></h1>
            <p class="hero-sub">Create a premium branded learning experience
                with your own app and website—designed to help you teach,
                sell, and <em>grow with confidence.</em></p>
            <div class="cta-groups">
                {{-- <a class="btn-hero" href="{{ route('login') }}">Get Started Now</a> --}}
                <p class="hero-footnote">Launch Your Platform Today
                    &nbsp;·&nbsp; No credit card required</p>
            </div>
            </div>
        </div>
         </div>
        <div class="col-lg-6 col-md-12 col-12">
            <div class="banner-right-img">
                <img src="{{ asset('frontend/img/banner-new.png') }}">
            </div>
        </div>
        </div>
        </div>

          <div class="wave-bottom">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path d="M0,64 C240,120 480,0 720,64 C960,120 1200,0 1440,64 L1440,120 L0,120 Z"></path>
        </svg>
    </div>

    </section>

    <!-- WAVE -->
    {{-- <div class="wave">
        <svg viewBox="0 0 1440 72" preserveAspectRatio="none" height="72">
            <path d="M0,48 C240,80 480,16 720,48 C960,80 1200,16 1440,48 L1440,72 L0,72 Z" fill="#ffffff" />
        </svg>
    </div> --}}

    <!-- FEATURES -->
    <div class="feats">
        <div class="container">
        <div class="feats-in">
            <div class="fi">
                <div class="fi-icon ic-b">🖥️</div>
                <div>
                    <div class="fi-tt">Build Your Custom App &amp;
                        Website</div>
                    <div class="fi-dd">Create a beautiful, branded platform
                        that's uniquely yours — no coding required. Launch
                        in minutes.</div>
                </div>
            </div>
            <div class="fi">
                <div class="fi-icon ic-o">🛒</div>
                <div>
                    <div class="fi-tt">Sell Courses &amp; Memberships</div>
                    <div class="fi-dd">Monetize your knowledge with ease
                        using integrated payments and flexible subscription
                        plans.</div>
                </div>
            </div>
            <div class="fi">
                <div class="fi-icon ic-g">💬</div>
                <div>
                    <div class="fi-tt">Engage Your Community</div>
                    <div class="fi-dd">Connect with your students through
                        live sessions, discussion forums, and real-time
                        messaging.</div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- PLATFORM -->
    <section class="plat">
        <div class="sec-hd">
            <div class="pill">All-in-One Tools</div>
            <h2>Your Platform, Your Way</h2>
            <p>All the tools you need to teach and grow your business — in
                one powerful, branded place.</p>
        </div>
        <div class="plat-grid">

            <div class="pc">
                <div class="pc-top bg1">
                    <div class="csm">
                        <div class="csm-tp">
                            <div class="csm-lg">MBSGuru</div>
                            <div class="csm-ds"><span></span><span></span><span></span></div>
                        </div>
                        <div class="csm-bd">
                            <div class="csm-r h1"></div>
                            <div class="csm-r w7"></div>
                            <div class="csm-cg">
                                <div class="csm-mc">
                                    <div class="csm-mi m1"></div>
                                    <div class="csm-ml"></div>
                                    <div class="csm-ml ms"></div>
                                </div>
                                <div class="csm-mc">
                                    <div class="csm-mi m2"></div>
                                    <div class="csm-ml"></div>
                                    <div class="csm-ml ms"></div>
                                </div>
                            </div>
                            <div class="csm-r h2" style="margin-top:8px"></div>
                            <div class="csm-r w5" style="margin-top:5px"></div>
                        </div>
                    </div>
                </div>
                <div class="pc-foot">
                    <h3>Online Courses</h3>
                    <p>Upload
                        videos, quizzes, and resources. Students learn at
                        their own pace, on any device.</p>
                </div>
            </div>

            <div class="pc">
                <div class="pc-top bg2">
                    <div class="cmk">
                        <div class="cm-vid">
                            <div class="cm-vg">
                                <div></div>
                                <div></div>
                                <div></div>
                                <div></div>
                            </div>
                            <div class="cm-pl">▶</div>
                        </div>
                        <div class="cm-ctrl">
                            <div class="cm-btn cb-r">●</div>
                            <div class="cm-btn cb-g">🎤</div>
                            <div class="cm-btn cb-b">📷</div>
                            <div class="cm-t">42:18 · LIVE</div>
                        </div>
                        <div class="cm-ppl">
                            <div class="cm-chip"><span class="cm-dot" style="background:#e87c2b"></span>Sarah</div>
                            <div class="cm-chip"><span class="cm-dot" style="background:#27a96c"></span>James</div>
                            <div class="cm-chip"><span class="cm-dot" style="background:#5b9af5"></span>+22</div>
                        </div>
                    </div>
                </div>
                <div class="pc-foot">
                    <h3>Live Coaching</h3>
                    <p>Host real-time
                        sessions with built-in video, chat, and recordings
                        accessible anytime.</p>
                </div>
            </div>

            <div class="pc">
                <div class="pc-top bg3">
                    <div class="crt">
                        <div class="crt-in">
                            <div class="crt-lbl">Certificate of
                                Achievement</div>
                            <div class="crt-seal">🏅</div>
                            <div class="crt-tt">Certificate
                                of<br>Completion</div>
                            <div class="crt-div"></div>
                            <div class="crt-nm">Jessica R. Williams</div>
                            <div class="crt-sb">Mastering Productivity ·
                                2026</div>
                            <div class="crt-sig">MBSGuru Academy</div>
                        </div>
                    </div>
                </div>
                <div class="pc-foot">
                    <h3>Quizzes &amp;
                        Certificates</h3>
                    <p>Auto-graded quizzes and branded
                        certificates your students will proudly
                        share.</p>
                </div>
            </div>

        </div>
    </section>

    <!-- TESTIMONIAL -->
    <section class="testi">
        <div class="testi--box">
            <div class="av-wrap">
                <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="User">
            </div>
            <div class="tb">
                
                <p class="qt"> This platform has transformed my business. I
                    can now teach and <span class="hl">reach my audience
                        like never before!</span> The branding tools alone
                    are worth it.</p>
                <div class="qa">
                    <div class="qa-stars">★★★★★</div>
                    <div class="qa-info">
                        <strong>Jessica R.</strong>
                        <span>Wellness Coach &amp; Course Creator</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TRUST -->
    <div class="trust-section">

        <div class="trust-title">

            <div class="line"></div>

            <div class="badge">
                Trusted by Educators Worldwide
            </div>

            <div class="line"></div>

        </div>

        <div class="trust-icons">

            <div class="icon">🛡️</div>
            <div class="icon">💬</div>
            <div class="icon">💳</div>
            <div class="icon">🎓</div>
            <div class="icon">🌐</div>
            <div class="icon">•••</div>

        </div>

    </div>





    <script>
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e, i) => {
                if (e.isIntersecting) {
                    setTimeout(() => e.target.classList.add('vis'), i * 130);
                    io.unobserve(e.target);
                }
            });
        }, {
            threshold: 0.12
        });
        document.querySelectorAll('.fi,.pc').forEach(el => io.observe(el));
    </script>
@endsection
