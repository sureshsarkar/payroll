(function ($) {
    "use strict";


    /*===========================================
        =            Windows Load          =
    =============================================*/
    $(window).on('load', function () {
        preloader();
        wowAnimation();
        aosAnimation();
    });


    /*===========================================
        =            Preloader          =
    =============================================*/
    function preloader() {
        $('#preloader').delay(0).fadeOut();
    };


    /*===========================================
        =    		Mobile Menu			      =
    =============================================*/
    //SubMenu Dropdown Toggle
    if ($('.tgmenu__wrap li.menu-item-has-children ul').length) {
        $('.tgmenu__wrap .navigation li.menu-item-has-children').append('<div class="dropdown-btn"><span class="plus-line"></span></div>');
    }

    //Mobile Nav Hide Show
    if ($('.tgmobile__menu').length) {

        var mobileMenuContent = $('.tgmenu__wrap .tgmenu__main-menu').html();
        $('.tgmobile__menu .tgmobile__menu-box .tgmobile__menu-outer').append(mobileMenuContent);

        //Dropdown Button
        $('.tgmobile__menu li.menu-item-has-children .dropdown-btn').on('click', function () {
            $(this).toggleClass('open');
            $(this).prev('ul').slideToggle(300);
        });
        //Menu Toggle Btn
        $('.mobile-nav-toggler').on('click', function () {
            $('body').addClass('mobile-menu-visible');
        });

        //Menu Toggle Btn
        $('.tgmobile__menu-backdrop, .tgmobile__menu .close-btn').on('click', function () {
            $('body').removeClass('mobile-menu-visible');
        });
    };


    /*===========================================
        =     Menu sticky & Scroll to top      =
    =============================================*/
    $(window).on('scroll', function () {
        var scroll = $(window).scrollTop();
        if (scroll < 245) {
            $("#sticky-header").removeClass("sticky-menu");
            $('.scroll-to-target').removeClass('open');
            $("#header-fixed-height").removeClass("active-height");

        } else {
            $("#sticky-header").addClass("sticky-menu");
            $('.scroll-to-target').addClass('open');
            $("#header-fixed-height").addClass("active-height");
        }
    });


    /*===========================================
        =           Scroll Up  	         =
    =============================================*/
    if ($('.scroll-to-target').length) {
        $(".scroll-to-target").on('click', function () {
            var target = $(this).attr('data-target');
            // animate
            $('html, body').animate({
                scrollTop: $(target).offset().top
            }, 0);

        });
    }


    /*===========================================
        =          Data Background    =
    =============================================*/
    $("[data-background]").each(function () {
        $(this).css("background-image", "url(" + $(this).attr("data-background") + ")")
    });

    $("[data-bg-color]").each(function () {
        $(this).css("background-color", $(this).attr("data-bg-color"));
    });


    /*===========================================
        =      Select2 Active      =
    =============================================*/
    $("#course-cat").select2({
        tags: true,
        theme: "bootstrap",
        minimumResultsForSearch: -1,
        dropdownCssClass: "course-category-dropdown",
    });

    /*=============================================
	=          Slider active              =
=============================================*/
var swiper2 = new Swiper(".slider__active", {
    spaceBetween: 0,
    effect: "fade",
    loop: true,
    autoplay: {
        delay: 6000,
    },
});


    /*=============================================
        =        Categories Active		      =
    =============================================*/
    var categoriesSwiper = new Swiper('.categories-active', {
        // Optional parameters
        slidesPerView: 6,
        spaceBetween: 44,
        loop: false,
        breakpoints: {
            '1500': {
                slidesPerView: 6,
            },
            '1200': {
                slidesPerView: 5,
            },
            '992': {
                slidesPerView: 4,
                spaceBetween: 30,
            },
            '768': {
                slidesPerView: 3,
                spaceBetween: 25,
            },
            '576': {
                slidesPerView: 2,
            },
            '0': {
                slidesPerView: 2,
                spaceBetween: 20,
            },
        },
        // Navigation arrows
        navigation: {
            nextEl: ".categories-button-next",
            prevEl: ".categories-button-prev",
        },
    });


    /*=============================================
        =        Courses Active		      =
    =============================================*/
    var coursesSwiper = new Swiper('.courses-swiper-active', {
        // Optional parameters
        slidesPerView: 4,
        spaceBetween: 30,
        observer: true,
        observeParents: true,
        loop: false,
        breakpoints: {
            '1400': {
                slidesPerView: 4,
            },
            '1200': {
                slidesPerView: 3,
            },
            '992': {
                slidesPerView: 3,
                spaceBetween: 24,
            },
            '768': {
                slidesPerView: 2,
                spaceBetween: 24,
            },
            '576': {
                slidesPerView: 1,
            },
            '0': {
                slidesPerView: 1,
            },
        },
        // Navigation arrows
        navigation: {
            nextEl: ".courses-button-next",
            prevEl: ".courses-button-prev",
        },
    });

    /*=============================================
        =        Courses Active		      =
    =============================================*/
    var coursesSwiperTwo = new Swiper('.courses-swiper-active-two', {
        // Optional parameters
        slidesPerView: 3,
        spaceBetween: 30,
        observer: true,
        observeParents: true,
        loop: false,
        breakpoints: {
            '1500': {
                slidesPerView: 3,
            },
            '1200': {
                slidesPerView: 3,
            },
            '992': {
                slidesPerView: 3,
                spaceBetween: 24,
            },
            '768': {
                slidesPerView: 2,
                spaceBetween: 24,
            },
            '576': {
                slidesPerView: 1,
            },
            '0': {
                slidesPerView: 1,
            },
        },
        // Navigation arrows
        navigation: {
            nextEl: ".courses-button-next",
            prevEl: ".courses-button-prev",
        },
    });

    /*=============================================
        =        testimonial Active		      =
    =============================================*/
    var testimonialSwiper = new Swiper('.testimonial-swiper-active', {
        // Optional parameters
        slidesPerView: 3,
        spaceBetween: 30,
        observer: true,
        observeParents: true,
        loop: true,
        breakpoints: {
            '1500': {
                slidesPerView: 3,
            },
            '1200': {
                slidesPerView: 3,
            },
            '992': {
                slidesPerView: 3,
                spaceBetween: 24,
            },
            '768': {
                slidesPerView: 2,
                spaceBetween: 24,
            },
            '576': {
                slidesPerView: 1,
            },
            '0': {
                slidesPerView: 1,
            },
        },
        // Navigation arrows
        navigation: {
            nextEl: ".testimonial-button-next",
            prevEl: ".testimonial-button-prev",
        },
    });

/*=============================================
	=        testimonial Active		      =
=============================================*/
var testimonialSwiper = new Swiper('.testimonial-active-four', {
    // Optional parameters
    slidesPerView: 1,
    spaceBetween: 30,
    observer: true,
    observeParents: true,
    loop: true,
    breakpoints: {
        '1500': {
            slidesPerView: 1,
        },
        '1200': {
            slidesPerView: 1,
        },
        '992': {
            slidesPerView: 1,
            spaceBetween: 24,
        },
        '768': {
            slidesPerView: 1,
            spaceBetween: 24,
        },
        '576': {
            slidesPerView: 1,
        },
        '0': {
            slidesPerView: 1,
        },
    },
    // If we need pagination
    pagination: {
        el: '.testimonial-pagination',
        clickable: true,
    },
});
/*=============================================
        =        testimonial Active		      =
    =============================================*/
    var shopSwiper = new Swiper('.shop-active', {
        // Optional parameters
        slidesPerView: 4,
        spaceBetween: 30,
        observer: true,
        observeParents: true,
        loop: true,
        breakpoints: {
            '1500': {
                slidesPerView: 4,
            },
            '1200': {
                slidesPerView: 4,
            },
            '992': {
                slidesPerView: 3,
                spaceBetween: 24,
            },
            '768': {
                slidesPerView: 2,
                spaceBetween: 24,
            },
            '576': {
                slidesPerView: 2,
            },
            '0': {
                slidesPerView: 1,
            },
        },
    });
/*=============================================
	=        testimonial Active		      =
=============================================*/
var testimonialSwiper = new Swiper('.testimonial-active-five', {
    // Optional parameters
    slidesPerView: 3,
    spaceBetween: 30,
    observer: true,
    observeParents: true,
    loop: true,
    breakpoints: {
        '1500': {
            slidesPerView: 3,
        },
        '1200': {
            slidesPerView: 3,
        },
        '992': {
            slidesPerView: 3,
            spaceBetween: 24,
        },
        '768': {
            slidesPerView: 2,
            spaceBetween: 24,
        },
        '576': {
            slidesPerView: 1,
        },
        '0': {
            slidesPerView: 1,
        },
    },
    // Navigation arrows
    navigation: {
        nextEl: ".testimonial-button-next",
        prevEl: ".testimonial-button-prev",
    },
});
/*=============================================
	=        testimonial Active		      =
=============================================*/
var testimonialSwiper = new Swiper('.testimonial-active-three', {
    // Optional parameters
    slidesPerView: 1,
    spaceBetween: 30,
    observer: true,
    observeParents: true,
    loop: true,
    breakpoints: {
        '1500': {
            slidesPerView: 1,
        },
        '1200': {
            slidesPerView: 1,
        },
        '992': {
            slidesPerView: 1,
            spaceBetween: 24,
        },
        '768': {
            slidesPerView: 1,
            spaceBetween: 24,
        },
        '576': {
            slidesPerView: 1,
        },
        '0': {
            slidesPerView: 1,
        },
    },
    // If we need pagination
    pagination: {
        el: '.testimonial-pagination',
        clickable: true,
    },
});

    /*=============================================
        =          instructor active              =
    =============================================*/
    var instructor = new Swiper(".instructor-nav", {
        spaceBetween: 0,
        slidesPerView: 6,
        loop: true,
        navigation: {
            nextEl: ".instructor-button-next",
            prevEl: ".instructor-button-prev",
        },
    });
    var instructor2 = new Swiper(".instructor-active", {
        spaceBetween: 0,
        thumbs: {
            swiper: instructor,
        },
    });

    /*=============================================
	=        Brand Active		      =
=============================================*/
var brandSwiper = new Swiper('.brand-swiper-active', {
    // Optional parameters
    slidesPerView: 6,
    spaceBetween: 30,
    observer: true,
    observeParents: true,
    loop: true,
    breakpoints: {
        '1500': {
            slidesPerView: 6,
        },
        '1200': {
            slidesPerView: 6,
        },
        '992': {
            slidesPerView: 5,
            spaceBetween: 24,
        },
        '768': {
            slidesPerView: 4,
            spaceBetween: 24,
        },
        '576': {
            slidesPerView: 3,
        },
        '0': {
            slidesPerView: 2,
        },
    },
});
var brandSwiper = new Swiper('.brand-swiper-active-two', {
    // Optional parameters
    slidesPerView: 5,
    spaceBetween: 30,
    observer: true,
    observeParents: true,
    loop: true,
    breakpoints: {
        '1500': {
            slidesPerView: 5,
        },
        '1200': {
            slidesPerView: 4,
        },
        '992': {
            slidesPerView: 4,
            spaceBetween: 24,
        },
        '768': {
            slidesPerView: 4,
            spaceBetween: 24,
        },
        '576': {
            slidesPerView: 3,
        },
        '0': {
            slidesPerView: 2,
        },
    },
});


    /*==================================
        SVG Draw
    ====================================*/
    var $svgIconBox = $('.tg-svg');
    $svgIconBox.each(function () {
        var $this = $(this),
            $svgIcon = $this.find('.svg-icon'),
            $id = $svgIcon.attr('id'),
            $icon = $svgIcon.data('svg-icon');
        if ($id) {
            var $vivus = new Vivus($id, {
                duration: 80,
                file: $icon,
            });
            $this.on('mouseenter', function () {
                $vivus.reset().play();
            });
        }
    });



    /*===========================================
        =       TweenMax Active   =
    =============================================*/
    $(".tg-motion-effects").mousemove(function (e) {
        parallaxIt(e, ".tg-motion-effects1", 20);
        parallaxIt(e, ".tg-motion-effects2", 5);
        parallaxIt(e, ".tg-motion-effects3", -10);
        parallaxIt(e, ".tg-motion-effects4", 30);
        parallaxIt(e, ".tg-motion-effects5", -50);
        parallaxIt(e, ".tg-motion-effects6", -20);
    });
    function parallaxIt(e, target_class, movement) {
        var $wrap = $(e.target).parents(".tg-motion-effects");
        if (!$wrap.length) return;
        var $target = $wrap.find(target_class);
        var relX = e.pageX - $wrap.offset().left;
        var relY = e.pageY - $wrap.offset().top;

        TweenMax.to($target, 1, {
            x: ((relX - $wrap.width() / 2) / $wrap.width()) * movement,
            y: ((relY - $wrap.height() / 2) / $wrap.height()) * movement,
        });
    };



    /*===========================================
        =    		 Cart Active  	         =
    =============================================*/
    $(".cart-plus-minus").append('<div class="dec qtybutton">-</div><div class="inc qtybutton">+</div>');
    $(".qtybutton").on("click", function () {
        var $button = $(this);
        var oldValue = $button.parent().find("input").val();
        if ($button.text() == "+") {
            var newVal = parseFloat(oldValue) + 1;
        } else {
            // Don't allow decrementing below zero
            if (oldValue > 0) {
                var newVal = parseFloat(oldValue) - 1;
            } else {
                newVal = 0;
            }
        }
        $button.parent().find("input").val(newVal);
    });

    /*=============================================
        =    		player Active  	       =
    =============================================*/
    // Modern Plyr config: speed selector, quality (if multi-source), captions,
    // loop + PIP. The "speed" + "settings" entries in `controls` enable the
    // gear menu in the bottom-right.
    const player = new Plyr('#player', {
        controls: [
            'play-large', 'restart', 'rewind', 'play', 'fast-forward',
            'progress', 'current-time', 'duration', 'mute', 'volume',
            'captions', 'settings', 'pip', 'airplay', 'fullscreen',
        ],
        settings: ['captions', 'quality', 'speed', 'loop'],
        speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2] },
        keyboard: { focused: true, global: false },
        tooltips: { controls: true, seek: true },
        captions: { active: true, language: 'auto', update: true },
        storage: { enabled: true, key: 'plyr_pref' },  // remembers volume/speed/quality
    });

    /* === Resume from last position ===
       Saves the current playback time every 10s into localStorage keyed by
       the active lesson_id. On metadata load, if a saved position exists and
       is more than 10s in (and not within 30s of the end), jump back. Pure
       client-side — no server roundtrips. */
    (function () {
        if (!player) return;

        function lessonStorageKey() {
            // Prefer an explicit data-attr on the video element, fall back to
            // the active lesson element in the sidebar.
            const $v = $('#player');
            const fromAttr = $v.data('lesson-id');
            if (fromAttr) return 'lesson-pos-' + fromAttr;
            const $active = $('.item-active input.lesson-completed-checkbox').first();
            const lid = $active.data('lesson-id') || $active.attr('data-lesson-id');
            return lid ? 'lesson-pos-' + lid : null;
        }

        let saveTimer = null;

        player.on('loadedmetadata', () => {
            const key = lessonStorageKey();
            if (!key) return;
            try {
                const saved = parseFloat(localStorage.getItem(key) || '0');
                const dur = player.duration || 0;
                if (saved > 10 && dur > 0 && saved < dur - 30) {
                    player.currentTime = saved;
                }
            } catch (e) { /* localStorage unavailable */ }
        });

        player.on('timeupdate', () => {
            if (saveTimer) return;
            saveTimer = setTimeout(() => {
                saveTimer = null;
                const key = lessonStorageKey();
                if (!key) return;
                try {
                    if (player.currentTime > 0) {
                        localStorage.setItem(key, String(Math.floor(player.currentTime)));
                    }
                } catch (e) { /* ignore */ }
            }, 10000); // throttle to once per 10s
        });

        player.on('ended', () => {
            const key = lessonStorageKey();
            if (key) { try { localStorage.removeItem(key); } catch (e) {} }
        });

        // Handy keyboard shortcuts beyond Plyr's built-ins:
        // J = -10s, L = +10s, K = play/pause, M = mute toggle.
        document.addEventListener('keydown', (e) => {
            if (!player.media) return;
            const tag = (e.target.tagName || '').toLowerCase();
            if (['input', 'textarea', 'select'].includes(tag) || e.target.isContentEditable) return;
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            const key = e.key.toLowerCase();
            if (key === 'j') { e.preventDefault(); player.currentTime = Math.max(0, player.currentTime - 10); }
            else if (key === 'l') { e.preventDefault(); player.currentTime = player.currentTime + 10; }
            else if (key === 'k') { e.preventDefault(); player.togglePlay(); }
            else if (key === 'm') { e.preventDefault(); player.muted = !player.muted; }
        });
    })();

    /*===========================================
          =       Odometer Active    =
    =============================================*/
    $('.odometer').appear(function (e) {
        var odo = $(".odometer");
        odo.each(function () {
            var countNumber = $(this).attr("data-count");
            $(this).html(countNumber);
        });
    });


    /*===========================================
        =        Magnific Popup    =
    =============================================*/
    $('.popup-image').magnificPopup({
        type: 'image',
        gallery: {
            enabled: true
        }
    });

    /* magnificPopup video view */
    $('.popup-video').magnificPopup({
        type: 'iframe'
    });

    /*===========================================
        =        Wow Active      =
    =============================================*/
    function wowAnimation() {
        var wow = new WOW({
            boxClass: 'wow',
            animateClass: 'animated',
            offset: 0,
            mobile: false,
            live: true
        });
        wow.init();
    }


    /*===========================================
        =           Aos Active       =
    =============================================*/
    function aosAnimation() {
        AOS.init({
            duration: 1000,
            mirror: true,
            once: true,
            disable: 'mobile',
        });
    }

    /*===========================================
        =    		 Cart Active  	         =
    =============================================*/
    $(window).on("load", function () {
        if ($(".curved-circle").length) {
            $(".curved-circle").circleType({
                position: "absolute",
                dir: 1,
                radius: 280,
                forceHeight: true,
                forceWidth: true,
            });
        }
    });


    // Nice select
    $('.select_js').niceSelect();


    // Sidebar filter button
    $(".courses__sidebar_button").on("click", function () {
        $(".courses__sidebar_area").toggleClass("show");
    });


})(jQuery);





var counted = false;

$(window).on("scroll", function () {

    if ($("#counter").length) {

        var oTop = $("#counter").offset().top - window.innerHeight;

        if (!counted && $(window).scrollTop() > oTop) {

            $(".count").each(function () {

                var $this = $(this);
                var countTo = parseInt($this.attr("data-count"));

                $({ countNum: $this.text() }).animate(
                    { countNum: countTo },
                    {
                        duration: 2000,
                        easing: "swing",
                        step: function () {
                            $this.text(Math.floor(this.countNum));
                        },
                        complete: function () {
                            $this.text(this.countNum);
                        }
                    }
                );

            });

            counted = true;
        }
    }

});