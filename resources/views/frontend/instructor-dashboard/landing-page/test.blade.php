<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coachpanel Landing Page</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
    </script>
</head>
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
    }

    *,
    ::before,
    ::after {
        box-sizing: border-box;
        margin-top: 0px;
        margin-right: 0px;
        margin-bottom: 0px;
        margin-left: 0px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    :root {
        --ink: #0d0d0d;
        --cream: #f5f0e8;
        --gold: #c8973a;
        --gold-light: #e8b85a;
        --white: #ffffff;
        --grey: #6b6b6b;
        --light-grey: #f0ede8;
        --section-pad: 100px 0;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: "DM Sans", sans-serif;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(245, 240, 232);
        color: rgb(13, 13, 13);
        overflow-x: hidden;
    }

    nav {
        position: fixed;
        top: 0px;
        left: 0px;
        width: 100%;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 22px;
        padding-right: 60px;
        padding-bottom: 22px;
        padding-left: 60px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgba(245, 240, 232, 0.92);
        backdrop-filter: blur(12px);
        border-bottom-width: 1px;
        border-bottom-style: solid;
        border-bottom-color: rgba(200, 151, 58, 0.2);
        transition-behavior: normal;
        transition-duration: 0.3s;
        transition-timing-function: ease;
        transition-delay: 0s;
        transition-property: box-shadow;
    }

    nav.scrolled {
        box-shadow: rgba(0, 0, 0, 0.08) 0px 4px 30px;
    }

    .logo {
        font-family: "Playfair Display", serif;
        font-size: 1.7rem;
        font-weight: 900;
        color: rgb(13, 13, 13);
        text-decoration-line: none;
        text-decoration-thickness: initial;
        text-decoration-style: initial;
        text-decoration-color: initial;
        letter-spacing: -0.5px;
    }

    .logo span {
        color: rgb(200, 151, 58);
    }

    .nav-links {
        display: flex;
        row-gap: 36px;
        column-gap: 36px;
        list-style-position: initial;
        list-style-image: initial;
        list-style-type: none;
    }

    .nav-links a {
        font-size: 0.88rem;
        font-weight: 500;
        text-decoration-line: none;
        text-decoration-thickness: initial;
        text-decoration-style: initial;
        text-decoration-color: initial;
        color: rgb(13, 13, 13);
        letter-spacing: 0.5px;
        text-transform: uppercase;
        position: relative;
        transition-behavior: normal;
        transition-duration: 0.3s;
        transition-timing-function: ease;
        transition-delay: 0s;
        transition-property: color;
    }

    .nav-links a::after {
        content: "";
        position: absolute;
        bottom: -3px;
        left: 0px;
        width: 0px;
        height: 2px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(200, 151, 58);
        transition-behavior: normal;
        transition-duration: 0.3s;
        transition-timing-function: ease;
        transition-delay: 0s;
        transition-property: width;
    }

    .nav-links a:hover {
        color: rgb(200, 151, 58);
    }

    .nav-links a:hover::after {
        width: 100%;
    }

    .nav-cta {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(13, 13, 13);
        padding-top: 10px;
        padding-right: 24px;
        padding-bottom: 10px;
        padding-left: 24px;
        border-top-left-radius: 2px;
        border-top-right-radius: 2px;
        border-bottom-right-radius: 2px;
        border-bottom-left-radius: 2px;
        color: rgb(255, 255, 255) !important;
        transition-behavior: normal !important;
        transition-duration: 0.3s !important;
        transition-timing-function: ease !important;
        transition-delay: 0s !important;
        transition-property: background !important;
    }

    .nav-cta:hover {
        background-image: initial !important;
        background-position-x: initial !important;
        background-position-y: initial !important;
        background-size: initial !important;
        background-repeat: initial !important;
        background-attachment: initial !important;
        background-origin: initial !important;
        background-clip: initial !important;
        background-color: rgb(200, 151, 58) !important;
    }

    .nav-cta::after {
        display: none !important;
    }

    #home {
        min-height: 100vh;
        display: flex;
        align-items: center;
        padding-top: 120px;
        padding-right: 60px;
        padding-bottom: 60px;
        padding-left: 60px;
        position: relative;
        overflow-x: hidden;
        overflow-y: hidden;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(13, 13, 13);
    }

    .banner-bg {
        position: absolute;
        top: 0px;
        right: 0px;
        bottom: 0px;
        left: 0px;
        background-image: radial-gradient(at 70% 50%, rgb(26, 20, 8) 0%, rgb(13, 13, 13) 70%);
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: initial;
    }

    .banner-grid {
        position: absolute;
        top: 0px;
        right: 0px;
        bottom: 0px;
        left: 0px;
        opacity: 0.07;
        background-image: linear-gradient(rgb(200, 151, 58) 1px, transparent 1px), linear-gradient(90deg, rgb(200, 151, 58) 1px, transparent 1px);
        background-size: 60px 60px;
    }

    .banner-circle {
        position: absolute;
        right: -100px;
        top: 50%;
        transform: translateY(-50%);
        width: 700px;
        height: 700px;
        border-top-left-radius: 50%;
        border-top-right-radius: 50%;
        border-bottom-right-radius: 50%;
        border-bottom-left-radius: 50%;
        background-image: radial-gradient(circle, rgba(200, 151, 58, 0.12) 0%, transparent 70%);
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: initial;
        animation-duration: 6s;
        animation-timing-function: ease-in-out;
        animation-delay: 0s;
        animation-iteration-count: infinite;
        animation-direction: normal;
        animation-fill-mode: none;
        animation-play-state: running;
        animation-name: pulse;
        animation-timeline: auto;
        animation-range-start: normal;
        animation-range-end: normal;
    }

    .banner-content {
        position: relative;
        z-index: 2;
        max-width: 700px;
    }

    .banner-tag {
        display: inline-block;
        font-size: 0.78rem;
        font-weight: 500;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: rgb(200, 151, 58);
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgba(200, 151, 58, 0.5);
        border-right-color: rgba(200, 151, 58, 0.5);
        border-bottom-color: rgba(200, 151, 58, 0.5);
        border-left-color: rgba(200, 151, 58, 0.5);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        padding-top: 8px;
        padding-right: 18px;
        padding-bottom: 8px;
        padding-left: 18px;
        border-top-left-radius: 2px;
        border-top-right-radius: 2px;
        border-bottom-right-radius: 2px;
        border-bottom-left-radius: 2px;
        margin-bottom: 32px;
        animation-duration: 0.8s;
        animation-timing-function: ease;
        animation-delay: 0s;
        animation-iteration-count: 1;
        animation-direction: normal;
        animation-fill-mode: forwards;
        animation-play-state: running;
        animation-name: fadeUp;
        animation-timeline: auto;
        animation-range-start: normal;
        animation-range-end: normal;
    }

    .banner-title {
        font-family: "Playfair Display", serif;
        font-size: clamp(3rem, 6vw, 5.5rem);
        font-weight: 900;
        line-height: 1.05;
        color: rgb(255, 255, 255);
        margin-bottom: 28px;
        animation-duration: 0.8s;
        animation-timing-function: ease;
        animation-delay: 0.2s;
        animation-iteration-count: 1;
        animation-direction: normal;
        animation-fill-mode: both;
        animation-play-state: running;
        animation-name: fadeUp;
        animation-timeline: auto;
        animation-range-start: normal;
        animation-range-end: normal;
    }

    .banner-title em {
        color: rgb(200, 151, 58);
        font-style: normal;
    }

    .banner-desc {
        font-size: 1.1rem;
        font-weight: 300;
        line-height: 1.8;
        color: rgba(255, 255, 255, 0.65);
        max-width: 520px;
        margin-bottom: 48px;
        animation-duration: 0.8s;
        animation-timing-function: ease;
        animation-delay: 0.4s;
        animation-iteration-count: 1;
        animation-direction: normal;
        animation-fill-mode: both;
        animation-play-state: running;
        animation-name: fadeUp;
        animation-timeline: auto;
        animation-range-start: normal;
        animation-range-end: normal;
    }

    .banner-btns {
        display: flex;
        row-gap: 16px;
        column-gap: 16px;
        animation-duration: 0.8s;
        animation-timing-function: ease;
        animation-delay: 0.6s;
        animation-iteration-count: 1;
        animation-direction: normal;
        animation-fill-mode: both;
        animation-play-state: running;
        animation-name: fadeUp;
        animation-timeline: auto;
        animation-range-start: normal;
        animation-range-end: normal;
    }

    .btn-primary-custom {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(200, 151, 58);
        color: rgb(13, 13, 13);
        padding-top: 16px;
        padding-right: 36px;
        padding-bottom: 16px;
        padding-left: 36px;
        font-size: 0.9rem;
        font-weight: 500;
        border-top-width: initial;
        border-right-width: initial;
        border-bottom-width: initial;
        border-left-width: initial;
        border-top-style: none;
        border-right-style: none;
        border-bottom-style: none;
        border-left-style: none;
        border-top-color: initial;
        border-right-color: initial;
        border-bottom-color: initial;
        border-left-color: initial;
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        cursor: pointer;
        border-top-left-radius: 2px;
        border-top-right-radius: 2px;
        border-bottom-right-radius: 2px;
        border-bottom-left-radius: 2px;
        text-decoration-line: none;
        text-decoration-thickness: initial;
        text-decoration-style: initial;
        text-decoration-color: initial;
        letter-spacing: 0.5px;
        transition-behavior: normal, normal;
        transition-duration: 0.3s, 0.2s;
        transition-timing-function: ease, ease;
        transition-delay: 0s, 0s;
        transition-property: background, transform;
    }

    .btn-primary-custom:hover {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(230, 172, 64);
        transform: translateY(-2px);
    }

    .btn-outline {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: transparent;
        color: rgb(255, 255, 255);
        padding-top: 16px;
        padding-right: 36px;
        padding-bottom: 16px;
        padding-left: 36px;
        font-size: 0.9rem;
        font-weight: 500;
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgba(255, 255, 255, 0.3);
        border-right-color: rgba(255, 255, 255, 0.3);
        border-bottom-color: rgba(255, 255, 255, 0.3);
        border-left-color: rgba(255, 255, 255, 0.3);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        cursor: pointer;
        border-top-left-radius: 2px;
        border-top-right-radius: 2px;
        border-bottom-right-radius: 2px;
        border-bottom-left-radius: 2px;
        text-decoration-line: none;
        text-decoration-thickness: initial;
        text-decoration-style: initial;
        text-decoration-color: initial;
        letter-spacing: 0.5px;
        transition-behavior: normal, normal;
        transition-duration: 0.3s, 0.3s;
        transition-timing-function: ease, ease;
        transition-delay: 0s, 0s;
        transition-property: border-color, color;
    }

    .btn-outline:hover {
        border-top-color: rgb(200, 151, 58);
        border-right-color: rgb(200, 151, 58);
        border-bottom-color: rgb(200, 151, 58);
        border-left-color: rgb(200, 151, 58);
        color: rgb(200, 151, 58);
    }

    .banner-stats {
        position: absolute;
        bottom: 60px;
        right: 60px;
        z-index: 2;
        display: flex;
        row-gap: 48px;
        column-gap: 48px;
        animation-duration: 0.8s;
        animation-timing-function: ease;
        animation-delay: 0.8s;
        animation-iteration-count: 1;
        animation-direction: normal;
        animation-fill-mode: both;
        animation-play-state: running;
        animation-name: fadeUp;
        animation-timeline: auto;
        animation-range-start: normal;
        animation-range-end: normal;
    }

    .stat {
        text-align: right;
    }

    .stat-num {
        font-family: "Playfair Display", serif;
        font-size: 2.4rem;
        font-weight: 700;
        color: rgb(255, 255, 255);
    }

    .stat-num span {
        color: rgb(200, 151, 58);
    }

    .stat-label {
        font-size: 0.78rem;
        color: rgba(255, 255, 255, 0.5);
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    #about {
        padding-top: 100px;
        padding-bottom: 100px;
        padding-left: 60px;
        padding-right: 60px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(245, 240, 232);
    }

    .about-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        row-gap: 80px;
        column-gap: 80px;
        align-items: center;
        max-width: 1200px;
        margin-top: 0px;
        margin-right: auto;
        margin-bottom: 0px;
        margin-left: auto;
    }

    .about-img-wrap {
        position: relative;
    }

    .about-img-wrap::before {
        content: "";
        position: absolute;
        top: -20px;
        left: -20px;
        right: 20px;
        bottom: 20px;
        border-top-width: 2px;
        border-right-width: 2px;
        border-bottom-width: 2px;
        border-left-width: 2px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgb(200, 151, 58);
        border-right-color: rgb(200, 151, 58);
        border-bottom-color: rgb(200, 151, 58);
        border-left-color: rgb(200, 151, 58);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-bottom-left-radius: 4px;
        z-index: 0;
    }

    .about-img-wrap img {
        width: 100%;
        height: 500px;
        object-fit: cover;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-bottom-left-radius: 4px;
        position: relative;
        z-index: 1;
        display: block;
    }

    .about-img-placeholder {
        width: 100%;
        height: 500px;
        background-image: linear-gradient(135deg, rgb(26, 20, 8) 0%, rgb(42, 32, 16) 50%, rgb(13, 13, 13) 100%);
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: initial;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-bottom-left-radius: 4px;
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow-x: hidden;
        overflow-y: hidden;
    }

    .about-img-placeholder::after {
        content: "ABOUT US";
        font-family: "Playfair Display", serif;
        font-size: 3rem;
        font-weight: 900;
        color: rgba(200, 151, 58, 0.2);
        letter-spacing: 8px;
    }

    .about-img-accent {
        position: absolute;
        bottom: -10px;
        right: -10px;
        z-index: 2;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(200, 151, 58);
        color: rgb(13, 13, 13);
        padding-top: 24px;
        padding-right: 28px;
        padding-bottom: 24px;
        padding-left: 28px;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-bottom-left-radius: 4px;
        text-align: center;
    }

    .about-img-accent .num {
        font-family: "Playfair Display", serif;
        font-size: 2.2rem;
        font-weight: 900;
        line-height: 1;
    }

    .about-img-accent .txt {
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 1px;
    }

    .section-tag {
        font-size: 0.78rem;
        font-weight: 500;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: rgb(200, 151, 58);
        margin-bottom: 16px;
        display: block;
    }

    .section-title {
        font-family: "Playfair Display", serif;
        font-size: clamp(2rem, 3.5vw, 2.8rem);
        font-weight: 700;
        line-height: 1.2;
        color: rgb(13, 13, 13);
        margin-bottom: 24px;
    }

    .section-text {
        font-size: 1rem;
        line-height: 1.85;
        color: rgb(107, 107, 107);
        margin-bottom: 32px;
    }

    .about-features {
        display: flex;
        flex-direction: column;
        row-gap: 20px;
        column-gap: 20px;
        margin-bottom: 36px;
    }

    .about-feature {
        display: flex;
        align-items: flex-start;
        row-gap: 16px;
        column-gap: 16px;
    }

    .about-feature-icon {
        width: 44px;
        height: 44px;
        border-top-left-radius: 50%;
        border-top-right-radius: 50%;
        border-bottom-right-radius: 50%;
        border-bottom-left-radius: 50%;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgba(200, 151, 58, 0.1);
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgba(200, 151, 58, 0.3);
        border-right-color: rgba(200, 151, 58, 0.3);
        border-bottom-color: rgba(200, 151, 58, 0.3);
        border-left-color: rgba(200, 151, 58, 0.3);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.2rem;
    }

    .about-feature-text strong {
        display: block;
        font-size: 0.95rem;
        margin-bottom: 4px;
    }

    .about-feature-text span {
        font-size: 0.875rem;
        color: rgb(107, 107, 107);
    }

    #services {
        padding-top: 100px;
        padding-bottom: 100px;
        padding-left: 60px;
        padding-right: 60px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(13, 13, 13);
    }

    .services-header {
        text-align: center;
        max-width: 600px;
        margin-top: 0px;
        margin-right: auto;
        margin-bottom: 70px;
        margin-left: auto;
    }

    .services-header .section-title {
        color: rgb(255, 255, 255);
    }

    .services-header .section-text {
        color: rgba(255, 255, 255, 0.55);
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        row-gap: 1px;
        column-gap: 1px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgba(200, 151, 58, 0.15);
        max-width: 1200px;
        margin-top: 0px;
        margin-right: auto;
        margin-bottom: 0px;
        margin-left: auto;
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgba(200, 151, 58, 0.15);
        border-right-color: rgba(200, 151, 58, 0.15);
        border-bottom-color: rgba(200, 151, 58, 0.15);
        border-left-color: rgba(200, 151, 58, 0.15);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
    }

    .service-card {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(17, 17, 17);
        padding-top: 48px;
        padding-right: 40px;
        padding-bottom: 48px;
        padding-left: 40px;
        transition-behavior: normal;
        transition-duration: 0.3s;
        transition-timing-function: ease;
        transition-delay: 0s;
        transition-property: background;
        cursor: default;
        position: relative;
        overflow-x: hidden;
        overflow-y: hidden;
    }

    .service-card::before {
        content: "";
        position: absolute;
        bottom: 0px;
        left: 0px;
        width: 100%;
        height: 3px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(200, 151, 58);
        transform: scaleX(0);
        transform-origin: left center;
        transition-behavior: normal;
        transition-duration: 0.4s;
        transition-timing-function: ease;
        transition-delay: 0s;
        transition-property: transform;
    }

    .service-card:hover {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(22, 18, 8);
    }

    .service-card:hover::before {
        transform: scaleX(1);
    }

    .service-icon {
        font-size: 2.5rem;
        margin-bottom: 24px;
        display: block;
    }

    .service-num {
        position: absolute;
        top: 24px;
        right: 28px;
        font-family: "Playfair Display", serif;
        font-size: 3.5rem;
        font-weight: 900;
        color: rgba(200, 151, 58, 0.06);
        line-height: 1;
    }

    .service-card h3 {
        font-family: "Playfair Display", serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: rgb(255, 255, 255);
        margin-bottom: 14px;
    }

    .service-card p {
        font-size: 0.9rem;
        line-height: 1.75;
        color: rgba(255, 255, 255, 0.5);
    }

    .service-link {
        padding: 8px 14px;
        font-weight: 500;
        border: none;
        margin-top: 15px;
    }

    .service-link:hover {
        row-gap: 14px;
        column-gap: 14px;
        background: #dddcdc;
        font-weight: 600;
    }

    #testimonials-custom {
        padding-top: 100px;
        padding-bottom: 100px;
        padding-left: 60px;
        padding-right: 60px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(245, 240, 232);
    }

    .testimonials-header {
        text-align: center;
        max-width: 600px;
        margin-top: 0px;
        margin-right: auto;
        margin-bottom: 70px;
        margin-left: auto;
    }

    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        row-gap: 30px;
        column-gap: 30px;
        max-width: 1200px;
        margin-top: 0px;
        margin-right: auto;
        margin-bottom: 0px;
        margin-left: auto;
    }

    .testimonial-card {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(255, 255, 255);
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        border-bottom-right-radius: 6px;
        border-bottom-left-radius: 6px;
        padding-top: 40px;
        padding-right: 36px;
        padding-bottom: 40px;
        padding-left: 36px;
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgba(0, 0, 0, 0.06);
        border-right-color: rgba(0, 0, 0, 0.06);
        border-bottom-color: rgba(0, 0, 0, 0.06);
        border-left-color: rgba(0, 0, 0, 0.06);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        position: relative;
        transition-behavior: normal, normal;
        transition-duration: 0.3s, 0.3s;
        transition-timing-function: ease, ease;
        transition-delay: 0s, 0s;
        transition-property: transform, box-shadow;
    }

    .testimonial-card:hover {
        transform: translateY(-6px);
        box-shadow: rgba(0, 0, 0, 0.1) 0px 20px 60px;
    }

    .testimonial-card::before {
        content: "\"";
        font-family: "Playfair Display", serif;
        font-size: 6rem;
        font-weight: 900;
        color: rgb(200, 151, 58);
        opacity: 0.15;
        position: absolute;
        top: -10px;
        left: 28px;
        line-height: 1;
    }

    .stars {
        color: rgb(200, 151, 58);
        font-size: 0.85rem;
        margin-bottom: 20px;
        letter-spacing: 2px;
    }

    .testimonial-text {
        font-size: 0.95rem;
        line-height: 1.8;
        color: rgb(107, 107, 107);
        font-style: italic;
        margin-bottom: 28px;
    }

    .testimonial-author {
        display: flex;
        align-items: center;
        row-gap: 14px;
        column-gap: 14px;
    }

    .author-avatar {
        width: 48px;
        height: 48px;
        border-top-left-radius: 50%;
        border-top-right-radius: 50%;
        border-bottom-right-radius: 50%;
        border-bottom-left-radius: 50%;
        background-image: linear-gradient(135deg, rgb(200, 151, 58), rgb(139, 105, 20));
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: initial;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: "Playfair Display", serif;
        font-size: 1.1rem;
        font-weight: 700;
        color: rgb(255, 255, 255);
        flex-shrink: 0;
    }

    .author-name {
        font-weight: 500;
        font-size: 0.95rem;
    }

    .author-role {
        font-size: 0.8rem;
        color: rgb(107, 107, 107);
    }

    #brands {
        padding-top: 70px;
        padding-right: 60px;
        padding-bottom: 70px;
        padding-left: 60px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(13, 13, 13);
        border-top-width: 1px;
        border-top-style: solid;
        border-top-color: rgba(200, 151, 58, 0.1);
    }

    .brands-header {
        text-align: center;
        margin-bottom: 50px;
    }

    .brands-header p {
        font-size: 0.8rem;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.35);
    }

    .brands-track {
        display: flex;
        align-items: center;
        justify-content: center;
        row-gap: 60px;
        column-gap: 60px;
        flex-wrap: wrap;
        max-width: 1200px;
        margin-top: 0px;
        margin-right: auto;
        margin-bottom: 0px;
        margin-left: auto;
    }

    .brand-item {
        font-family: "Playfair Display", serif;
        font-size: 1.2rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.2);
        letter-spacing: 2px;
        text-transform: uppercase;
        transition-behavior: normal;
        transition-duration: 0.3s;
        transition-timing-function: ease;
        transition-delay: 0s;
        transition-property: color;
        cursor: default;
    }

    .brand-item:hover {
        color: rgb(200, 151, 58);
    }

    #contact {
        padding-top: 100px;
        padding-bottom: 100px;
        padding-left: 60px;
        padding-right: 60px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(245, 240, 232);
    }

    .contact-wrap {
        max-width: 1200px;
        margin-top: 0px;
        margin-right: auto;
        margin-bottom: 0px;
        margin-left: auto;
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        row-gap: 80px;
        column-gap: 80px;
        align-items: start;
    }

    .contact-info .section-title {
        margin-bottom: 20px;
    }

    .contact-info .section-text {
        margin-bottom: 40px;
    }

    .contact-details {
        display: flex;
        flex-direction: column;
        row-gap: 24px;
        column-gap: 24px;
    }

    .contact-detail {
        display: flex;
        align-items: flex-start;
        row-gap: 16px;
        column-gap: 16px;
    }

    .contact-detail-icon {
        width: 48px;
        height: 48px;
        border-top-left-radius: 50%;
        border-top-right-radius: 50%;
        border-bottom-right-radius: 50%;
        border-bottom-left-radius: 50%;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(13, 13, 13);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .contact-detail strong {
        display: block;
        font-size: 0.85rem;
        margin-bottom: 3px;
    }

    .contact-detail span {
        font-size: 0.9rem;
        color: rgb(107, 107, 107);
    }

    .contact-form-custom {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(255, 255, 255);
        padding-top: 50px;
        padding-right: 48px;
        padding-bottom: 50px;
        padding-left: 48px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        border-bottom-right-radius: 6px;
        border-bottom-left-radius: 6px;
        box-shadow: rgba(0, 0, 0, 0.06) 0px 8px 40px;
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgba(0, 0, 0, 0.05);
        border-right-color: rgba(0, 0, 0, 0.05);
        border-bottom-color: rgba(0, 0, 0, 0.05);
        border-left-color: rgba(0, 0, 0, 0.05);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
    }

    .contact-form-custom h3 {
        font-family: "Playfair Display", serif;
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 32px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        row-gap: 20px;
        column-gap: 20px;
    }

    .form-group {
        margin-bottom: 22px;
    }

    .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 500;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        color: rgb(13, 13, 13);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding-top: 14px;
        padding-right: 18px;
        padding-bottom: 14px;
        padding-left: 18px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(240, 237, 232);
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: transparent;
        border-right-color: transparent;
        border-bottom-color: transparent;
        border-left-color: transparent;
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-bottom-left-radius: 4px;
        font-family: "DM Sans", sans-serif;
        font-size: 0.9rem;
        color: rgb(13, 13, 13);
        transition-behavior: normal, normal;
        transition-duration: 0.3s, 0.3s;
        transition-timing-function: ease, ease;
        transition-delay: 0s, 0s;
        transition-property: border-color, background;
        outline-color: initial;
        outline-style: none;
        outline-width: initial;
        appearance: none;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-top-color: rgb(200, 151, 58);
        border-right-color: rgb(200, 151, 58);
        border-bottom-color: rgb(200, 151, 58);
        border-left-color: rgb(200, 151, 58);
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(255, 255, 255);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 130px;
    }

    .form-submit {
        width: 100%;
        padding-top: 16px;
        padding-right: 16px;
        padding-bottom: 16px;
        padding-left: 16px;
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(13, 13, 13);
        color: rgb(255, 255, 255);
        border-top-width: initial;
        border-right-width: initial;
        border-bottom-width: initial;
        border-left-width: initial;
        border-top-style: none;
        border-right-style: none;
        border-bottom-style: none;
        border-left-style: none;
        border-top-color: initial;
        border-right-color: initial;
        border-bottom-color: initial;
        border-left-color: initial;
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-bottom-left-radius: 4px;
        font-family: "DM Sans", sans-serif;
        font-size: 0.95rem;
        font-weight: 500;
        letter-spacing: 1px;
        text-transform: uppercase;
        cursor: pointer;
        transition-behavior: normal, normal;
        transition-duration: 0.3s, 0.2s;
        transition-timing-function: ease, ease;
        transition-delay: 0s, 0s;
        transition-property: background, transform;
    }

    .form-submit:hover {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(200, 151, 58);
        transform: translateY(-2px);
    }

    footer {
        background-image: initial;
        background-position-x: initial;
        background-position-y: initial;
        background-size: initial;
        background-repeat: initial;
        background-attachment: initial;
        background-origin: initial;
        background-clip: initial;
        background-color: rgb(7, 7, 7);
        padding-top: 80px;
        padding-right: 60px;
        padding-bottom: 40px;
        padding-left: 60px;
        color: rgba(255, 255, 255, 0.5);
    }

    .footer-top {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr 1fr;
        row-gap: 60px;
        column-gap: 60px;
        margin-bottom: 60px;
        padding-bottom: 60px;
        border-bottom-width: 1px;
        border-bottom-style: solid;
        border-bottom-color: rgba(255, 255, 255, 0.06);
    }

    .footer-brand .logo {
        color: rgb(255, 255, 255);
        display: block;
        margin-bottom: 18px;
    }

    .footer-brand p {
        font-size: 0.88rem;
        line-height: 1.8;
        max-width: 260px;
    }

    .footer-socials {
        display: flex;
        row-gap: 12px;
        column-gap: 12px;
        margin-top: 28px;
    }

    .social-btn {
        width: 40px;
        height: 40px;
        border-top-left-radius: 50%;
        border-top-right-radius: 50%;
        border-bottom-right-radius: 50%;
        border-bottom-left-radius: 50%;
        border-top-width: 1px;
        border-right-width: 1px;
        border-bottom-width: 1px;
        border-left-width: 1px;
        border-top-style: solid;
        border-right-style: solid;
        border-bottom-style: solid;
        border-left-style: solid;
        border-top-color: rgba(255, 255, 255, 0.15);
        border-right-color: rgba(255, 255, 255, 0.15);
        border-bottom-color: rgba(255, 255, 255, 0.15);
        border-left-color: rgba(255, 255, 255, 0.15);
        border-image-source: initial;
        border-image-slice: initial;
        border-image-width: initial;
        border-image-outset: initial;
        border-image-repeat: initial;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        text-decoration-line: none;
        text-decoration-thickness: initial;
        text-decoration-style: initial;
        text-decoration-color: initial;
        color: rgba(255, 255, 255, 0.5);
        transition-behavior: normal, normal;
        transition-duration: 0.3s, 0.3s;
        transition-timing-function: ease, ease;
        transition-delay: 0s, 0s;
        transition-property: border-color, color;
    }

    .social-btn:hover {
        border-top-color: rgb(200, 151, 58);
        border-right-color: rgb(200, 151, 58);
        border-bottom-color: rgb(200, 151, 58);
        border-left-color: rgb(200, 151, 58);
        color: rgb(200, 151, 58);
    }

    .footer-col h4 {
        font-size: 0.85rem;
        font-weight: 500;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: rgb(255, 255, 255);
        margin-bottom: 24px;
    }

    .footer-col ul {
        list-style-position: initial;
        list-style-image: initial;
        list-style-type: none;
        display: flex;
        flex-direction: column;
        row-gap: 12px;
        column-gap: 12px;
    }

    .footer-col ul a {
        font-size: 0.88rem;
        text-decoration-line: none;
        text-decoration-thickness: initial;
        text-decoration-style: initial;
        text-decoration-color: initial;
        color: rgba(255, 255, 255, 0.45);
        transition-behavior: normal;
        transition-duration: 0.3s;
        transition-timing-function: ease;
        transition-delay: 0s;
        transition-property: color;
    }

    .footer-col ul a:hover {
        color: rgb(200, 151, 58);
    }

    .footer-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.82rem;
    }

    .footer-bottom a {
        color: rgb(200, 151, 58);
        text-decoration-line: none;
        text-decoration-thickness: initial;
        text-decoration-style: initial;
        text-decoration-color: initial;
    }

    #i4vjbl {
        display: inline-block;
        color: #ffffff;
    }

    #i47j6g {
        color: #ffffff;
    }

    #ivjolj {
        color: rgba(255, 255, 255, 0.5);
    }

    @keyframes pulse {

        0%,
        100% {
            transform: translateY(-50%) scale(1);
        }

        50% {
            transform: translateY(-50%) scale(1.08);
        }
    }

    @keyframes fadeUp {
        0% {
            opacity: 0;
            transform: translateY(30px);
        }

        100% {
            opacity: 1;
            transform: translateY(0px);
        }
    }
</style>
</head>

<link rel="stylesheet" href="http://localhost/laravel/mbsguru/global/toastr/toastr.min.css">

<body>


    {{-- =========================================================================================== --}}
    <style>
        .modal-content {
            background: linear-gradient(145deg, #13131f, #0d0d18);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
        }

        .form-control {
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #000000 !important;
        }

        .form-control:focus {
            border-color: #7c4ddc !important;
            box-shadow: 0 0 0 2px rgba(120, 80, 255, .2) !important;
        }

        .btn-submit {
            background: linear-gradient(146deg, #fbfafe, #001051);
            border: none;
            color: #000000;
        }
    </style>
    <!-- Button to open modal -->


    <!-- Service form Modal start -->
    <div class="modal fade " id="serviceProductModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"> 
                <div class="modal-header border-0">
                    <h5 class="modal-title text-light">Enquiry Now</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div> 
                <form id="service-form-id" action="{{ route('publish-service-page.submit') }}" method="post">
                    <input type="hidden" name="product_id" id="product_id" value="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <label for="name" class="text-light">Full Name <span>*</span> </label>
                                <div class="mb-2 py-2">
                                    <input type="text" class="form-control" name="name" id="name"
                                        placeholder="Name" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="email" class="text-light">Email Address <span>*</span></label>
                                <div class="mb-2 py-2">
                                    <input type="email" class="form-control" name="email" id="email"
                                        placeholder="Email" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="phone" class="text-light">Mobile Number <span>*</span></label>
                                <div class="mb-2 py-2">
                                    <input type="tel" class="form-control" name="phone" id="phone"
                                        placeholder="Phone" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="textarea" class="text-light">Textarea</label>
                                <div class="mb-2 py-2">
                                    <textarea class="form-control" name="message"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0">
                        <button class="btn btn-submit form-submit text-white">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Service form Modal end -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('global/toastr/toastr.min.js') }}"></script>
    <script>
        $(document).ready(function(){

        
        $(".service-link").click(function() {
            let productId = $(this).attr('data-id');
            $("#product_id").val(productId);

        }) 

        $("#service-form-id").on("submit", function(e) {
            e.preventDefault();

            let name = document.getElementById('name');
            let email = document.getElementById('email');
            let phone = document.getElementById('phone');

            if (!name.value) return alert('Name required');
            if (!email.value.includes('@')) return alert('Valid email required');
            if (!phone.value) return alert('Phone required');


            let formData = $(this).serialize();
            formData = $(this).serialize() + '&_token={{ csrf_token() }}';

            $.ajax({
                method: "POST",
                url: "{{ route('publish-service-page.submit') }}",
                data: formData,
                beforeSend: function() {
                    $(".form-submit").text('Submitting...');
                    $(".form-submit").prop("disabled", true);
                },
                success: function(data) {
                    console.log(data);
                    return false;

                    toastr.success(data.message);
                    // $(".form-submit")[0].reset();
                    $(".form-submit").text('Submit');
                    $(".form-submit").prop("disabled", false);
                    // window.location.reload();
                },
                error: function(xhr, status, error) {

                    if (xhr.status === 419) {
                        toastr.error("CSRF token mismatch / session expired");
                        return;
                    }

                    let errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        toastr.error(value);
                    });
                    $(".form-submit button").text('Submit');
                    $(".form-submit button").prop("disabled", false);
                },
            });
        });
        });
    </script>





    <section id="services">
        <div class="services-header"><span class="section-tag">✦ What We Do</span>
            <h2 class="section-title" id="i47j6g">Services Built for Impact</h2>
            <p class="section-text" id="ivjolj">From concept to launch, we offer a complete
                suite of digital services tailored to elevate your brand.</p>
        </div>

        <div class="services-grid" id="service-product-section-id">

            <div class="service-card"><span class="service-num">01</span><span class="service-icon">🎯</span>
                <h3>Brand Strategy</h3>
                <p>We dive deep into your brand's DNA to craft a strategy that resonates, differentiates, and
                    endures in competitive markets.</p>
                <button class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal" data-id="1">Buy
                    Now</a>
            </div>

            <div class="service-card"><span class="service-num">02</span><span class="service-icon">💻</span>
                <h3>Web Development</h3>
                <p>High-performance websites and web applications built with modern technologies, focusing on speed,
                    scalability, and user experience.</p>
                <button class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal" data-id="2">Buy
                    Now</a>
            </div>

            <div class="service-card"><span class="service-num">03</span><span class="service-icon">📱</span>
                <h3>Mobile Apps</h3>
                <p>Native and cross-platform mobile applications that deliver seamless experiences across iOS and
                    Android devices.</p>
                <button class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal" data-id="3">Buy
                    Now</a>
            </div>
            <div class="service-card"><span class="service-num">04</span><span class="service-icon">✏️</span>
                <h3>UI/UX Design</h3>
                <p>User-centered design that combines aesthetic elegance with intuitive functionality to create
                    memorable digital experiences.</p>
                <button class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal" data-id="4">Buy
                    Now</a>
            </div>
            <div class="service-card"><span class="service-num">05</span><span class="service-icon">🚀</span>
                <h3>Digital Marketing</h3>
                <p>Data-driven marketing campaigns across SEO, paid media, social, and content to amplify your reach
                    and drive conversions.</p>
                <button class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal"
                    data-id="5">Buy Now</a>
            </div>
            <div class="service-card"><span class="service-num">06</span><span class="service-icon">☁️</span>
                <h3>Cloud Solutions</h3>
                <p>Scalable cloud infrastructure and DevOps services that keep your digital products running fast,
                    secure, and reliably.</p>
                <button class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal"
                    data-id="6">Buy Now</a>
            </div>
        </div>
    </section>

    {{-- =========================================================================================== --}}


</body>


<script src="http://localhost/laravel/mbsguru/global/js/jquery-3.7.1.min.js"></script>
<script src="http://localhost/laravel/mbsguru/global/toastr/toastr.min.js"></script>

</html>
