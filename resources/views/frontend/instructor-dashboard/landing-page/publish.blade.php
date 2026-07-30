<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->title }}</title>
    <style>
        {!! $page->css_content !!} .premium-modal {
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 18px;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
        }

        .modal-title {
            font-weight: 500;
            font-size: 1.5rem;
        }

        .modal-subtitle {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #8b7fff;
        }

        .custom-input {
            background: #f8f8f8 !important;
            border: 1.5px solid #e8e5f5 !important;
            border-radius: 10px !important;
            color: #1a1530 !important;
            font-family: 'Nunito', sans-serif;
            font-size: .88rem;
            font-weight: 400;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }

        .custom-input:focus {
            border-color: #7c4ddc !important;
            box-shadow: 0 0 0 3px rgba(124, 77, 220, 0.2);
            background: rgba(255, 255, 255, 0.06) !important;
        }

        .form-floating>label {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-floating>.form-control:focus~label,
        .form-floating>.form-control:not(:placeholder-shown)~label {
            color: #a78bfa;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #21295c, #02051a);
            border: none !important;
            color: #fff !important;
            font-weight: 500 !important;
            padding: 11px 28px !important;
            border-radius: 10px !important;
            transition: all 0.3s ease !important;
            color: #fff !important;
        }

        .btn-gradient:hover {
            box-shadow: 0 8px 25px rgba(124, 77, 220, 0.4);
        }

        .btn-gradient:active {
            transform: scale(0.97);
        }

        .modal-footer {
            justify-content: space-between !important;
        }

        .show-message-text {
            font-size: 15px;
            font-weight: 600;
            color: #002753;
        }

     
    </style>
</head>

<link rel="stylesheet" href="{{ asset('global/toastr/toastr.min.css') }}">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
</script>

{!! $page->html_content !!}



<div class="modal fade" id="serviceProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content premium-modal">

            <!-- Header -->
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div>
                    <p class="modal-subtitle mb-1">Get in touch</p>
                    <h5 class="modal-title text-dark mb-0">Enquiry Now</h5>
                </div>
                <button type="button" class="btn-close opacity-50" data-bs-dismiss="modal"></button>
            </div>

            <form id="service-form-id" action="{{ route('publish-service-page.submit') }}" method="post">
                @csrf
                <input type="hidden" name="product_id" id="product_id">
                <input type="hidden" name="coach_id" value="{{ $page->added_by }}">

                <div class="modal-body px-4 py-3">

                    <!-- Full Name -->
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control custom-input" name="name" id="name"
                            placeholder="Name" required>
                        <label>Full Name *</label>
                    </div>

                    <!-- Email -->
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control custom-input" name="email" id="email"
                            placeholder="Email" required>
                        <label>Email Address *</label>
                    </div>

                    <!-- Phone -->
                    <div class="form-floating mb-3">
                        <input type="tel" class="form-control custom-input" name="phone" id="phone"
                            placeholder="Phone" required>
                        <label>Mobile Number *</label>
                    </div>

                    <!-- Message -->
                    <div class="form-floating mb-2">
                        <textarea class="form-control custom-input" name="message" placeholder="Message" style="height:100px"></textarea>
                        <label>Your Message</label>
                    </div>

                </div>

                <!-- Footer -->
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <p class="mb-0 d-flex align-items-center gap-1" style="font-size:.7rem;color:#8980bb"><svg
                            width="11" height="12" viewBox="0 0 10 11" fill="none">
                            <rect x="1" y="4.5" width="8" height="6" rx="1" stroke="currentColor"
                                stroke-width="1.1"></rect>
                            <path d="M3 4.5V3a2 2 0 014 0v1.5" stroke="currentColor" stroke-width="1.1"
                                stroke-linecap="round"></path>
                        </svg> Your data is safe with us</p>

                    <p class="mb-0 show-message-text"></p>
                    <div class="text-center">
                        <button class="btn btn-gradient btn-sm px-4 product-form-submit">Submit →</button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>




<script src="{{ asset('global/js/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('global/toastr/toastr.min.js') }}"></script>
<!-- dynamic Toastr Notification -->



<script>
    /** handle contact form */
    $(document).ready(function() {
        let products = @json($product_temp);
        let youtube = @json($youtube_temp);
        $('#servicesGrid').html(products);
        $('#youtubeGrid').html(youtube);
    });
</script>



<script>
    /** handle contact form */
    $("#landing-form-id").on("submit", function(e) {
        e.preventDefault();
        let formData = $(this).serialize();
        formData = $(this).serialize() + '&_token={{ csrf_token() }}';

        $.ajax({
            method: "POST",
            url: "{{ route('publish-landing-page.submit') }}",
            data: formData,
            beforeSend: function() {
                $(".form-submit").text('Submitting...');
                $(".form-submit").prop("disabled", true);
            },
            success: function(data) {
// console.log(data);

                toastr.success(data.message);
                // $(".form-submit")[0].reset();
                $(".form-submit").text('Send Message →');
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
                $(".form-submit button").text('Send Message →');
                $(".form-submit button").prop("disabled", false);
            },
        });
    });
</script>


<script>
    $(document).ready(function() {
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
                    $(".product-form-submit").text('Submitting...');
                    $(".product-form-submit").prop("disabled", true);
                },
                success: function(data) { 
                    // console.log(data);
                    
                    // toastr.success(data.message);
                    $('.show-message-text').text(data.message)
                    $("#service-form-id")[0].reset();
                    $(".product-form-submit").text('Submit →');
                    $(".product-form-submit").prop("disabled", false);
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
                    $(".product-form-submit button").text('Submit →');
                    $(".product-form-submit button").prop("disabled", false);
                },
            });
        });
    });
</script>



    <script>
            function toggleMenu(){
            document.getElementById("nav--Menu").classList.toggle("active");
            document.querySelector(".ham--burger").classList.toggle("active");
            }
 </script>






 
<!-- ══════════════ SCRIPTS ══════════════ -->
<script>
  /* Navbar scroll */
  window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 20);
  });


/* Burger Menu */

const burger = document.getElementById('burgerBtn');
const mobileNav = document.getElementById('mobileNav');

if (burger && mobileNav) {

  burger.addEventListener('click', function () {
    mobileNav.classList.toggle('open');
  });

  mobileNav.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () {
      mobileNav.classList.remove('open');
    });
  });

}
 
  /* Counter */
  const cObserver = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting && !e.target.dataset.counted) {
        e.target.dataset.counted = '1';
        const target = +e.target.dataset.target;
        const dur = 2000;
        const step = target / (dur / 16);
        let cur = 0;
        const t = setInterval(() => {
          cur += step;
          if (cur >= target) { cur = target; clearInterval(t); }
          e.target.textContent = Math.floor(cur).toLocaleString('en-IN');
        }, 16);
      }
    });
  }, { threshold: 0.5 });
  document.querySelectorAll('.counter').forEach(el => cObserver.observe(el));
</script>

 <script>
    // Inject responsive styles
  var rs = document.createElement('style');
  rs.textContent = `
    @media (max-width: 768px) {
      #temp-nav--links { display: none !important; }  
    } 
  `;
  document.head.appendChild(rs); 
</script> 

</html>
