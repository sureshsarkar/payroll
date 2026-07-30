"use strict";

const csrf_token = $("meta[name='csrf-token']").attr("content");

/** On Document Load */

$(document).ready(function () {

function formatDate(dateString) {

    // 2026-07-08 — batch Start/End dates show DATE ONLY in "D MMM YYYY" form
    // (e.g. 7 Jul 2026); the time is shown separately in the "Time" row. This is
    // display-only and does not change the stored date/time values.
    if (!dateString) return '';

    let date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;   // fall back to raw if unparseable

    return date.toLocaleDateString("en-GB", {
        day: "numeric",
        month: "short",
        year: "numeric"
    });

}

$(document).on("click", ".choose-batch-btn", function () {

    let batchId = $(this).data("id");
     $(".choose-batch-btn .cbb-label").text("Select");
     $(this).find(".cbb-label").text("Selected");

    $("#selectedBatch").val(batchId);
    $("#confirmBatch").removeClass('d-none');

    $(".batch-card").removeClass("selected");

    $(this).closest(".batch-card").addClass("selected");

});



    // Add to cart
   $(document).on("click", ".add-to-cart", function (e) {

    e.preventDefault();

    let element = $(this);
    let course_id = $(this).data('id');
    let courseType = (element.data('type') || '').toString().toLowerCase();

    // 2026-06-11 — Recorded courses ('course' | 'recorded') have NO batch, so
    // add them straight to the cart. Only Live/Batch courses ('live' | 'hybrid')
    // open the batch picker below. Fixes recorded courses dead-ending on the
    // "No batch available" error.
    if (courseType === 'course' || courseType === 'recorded') {
        $.ajax({
            method: "POST",
            url: base_url + "/add-to-cart/" + course_id,
            data: { _token: csrf_token },
            beforeSend: function () { element.find("span").text("Loading..."); },
            success: function (response) {
                element.find("span").text("Add to cart");
                if (response.status === "success") {
                    toastr.success(response.message);
                    if (typeof response.cart_count !== "undefined") {
                        $(".mini-cart-count").text(response.cart_count);
                    }
                } else {
                    toastr.error(response.message);
                }
            },
            error: function () {
                element.find("span").text("Add to cart");
                toastr.error("Something went wrong");
            }
        });
        return;
    }

    $.ajax({
        method: "POST",
        url: base_url + "/get-batch/" + course_id,
        data: {
            _token: csrf_token
        },

        beforeSend: function () {
            element.find("span").text("Loading...");
        },

        success: function (response) {

            element.find("span").text("Add to cart");

            if (!response || response.length === 0) {
                toastr.error("No batch available");
                return;
            }

            let html = '';

            response.data.forEach(function (batch) {

                let days = batch.days.join(", ");

                html += `
                        <div class="col-lg-4 col-md-6">
                                <div class="batch-card d-flex flex-column h-100" data-batch-id="${batch.id}">

                                    <span class="batch-card__check" aria-hidden="true">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </span>

                                    <div class="batch-card-content flex-grow-1">
                                        <div class="batch-card__head">
                                            <span class="batch-card__icon" aria-hidden="true">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            </span>
                                            <h4 class="batch-title">${batch.title}</h4>
                                        </div>

                                        <ul class="batch-meta">
                                            <li><span class="bm-ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="bm-k">Starts</span><span class="bm-v">${formatDate(batch.start_date)}</span></li>
                                            <li><span class="bm-ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg></span><span class="bm-k">Ends</span><span class="bm-v">${formatDate(batch.end_date)}</span></li>
                                            <li><span class="bm-ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><span class="bm-k">Time</span><span class="bm-v">${batch.start_time} - ${batch.end_time}</span></li>
                                            <li><span class="bm-ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></span><span class="bm-k">Days</span><span class="bm-v">${days}</span></li>
                                        </ul>
                                    </div>

                                    <div class="batch-card__foot">
                                        <span class="batch-seats"><span class="bm-dot"></span>${batch.capacity} seats left</span>
                                        <button type="button" class="choose-batch-btn" data-id="${batch.id}"><span class="cbb-label">Select</span></button>
                                    </div>

                                </div>
                        </div>`;
            });

            $("#batchContainer").html(html);

            $("#addToCartModal").modal("show");

        },

        error: function () {
            toastr.error("Something went wrong");
            element.find("span").text("Add to cart");
        }

    });

});



// got to add cart data with batch 

$(document).on("submit", "#batchCartForm", function(e){

    e.preventDefault();

    let form = $(this);
    let url = form.attr("action");
    let button = $("#confirmBatch");

    let course_id = $("#selectedCourse").val();
    let batch_id = $("#selectedBatch").val();

    // Validation
    if(!batch_id){
        toastr.error("Please select a batch first");
        return;
    }

    $.ajax({
        method:"POST",
        url:url,
        data:form.serialize(),

        beforeSend:function(){
            button.prop("disabled", true);
            button.text("Adding...");
        },

        success:function(response){   
            console.log(response);
            
            if(response.status === "success"){

                toastr.success(response.message);

                $(".mini-cart-count").text(response.cart_count);

                $("#addToCartModal").modal("hide");

                // After confirming the batch, take the buyer straight to the cart.
                // redirect_to is host-aware (white-label): coach domain → coach cart,
                // platform → platform cart. Falls back to /cart.
                window.location.href = response.redirect_to || "/cart";

            }else{

                toastr.error(response.message);

            }

        },

        error:function(xhr){

            if(xhr.status === 422){

                let errors = xhr.responseJSON.errors;

                $.each(errors,function(key,value){
                    toastr.error(value[0]);
                });

            }else{

                toastr.error("Something went wrong");

            }

        },

        complete:function(){
            button.prop("disabled", false);
            button.text("Confirm Batch");
        }

    });

});



      // Add to cart
//     $(document).on("click", ".add-to-cart", function (e) {
//         e.preventDefault();
//         let element = $(this);

//          $.ajax({
//             method: "post",
//             url: base_url + "/get-batch/" + $(this).data('id'),
//             data: {
//                 _token: csrf_token
//             },
//             beforeSend: function () {
//                 element.find("span").text("Adding...");
//             },
//             success: function (data) {
                
//                 if (data.status == "success") {
//                     toastr.success(data.message);
//                     $('.mini-cart-count').text(data.cart_count);
//                     if (data.dataLayer && typeof data.dataLayer === 'object') {
//                         dataLayer.push({
//                             'event': 'addToCart',
//                             'cart_details': data.dataLayer
//                         });
//                     }
                    
//                 } else {
//                     toastr.error(data.message);
//                 }

//                 element.find("span").text("Add to cart");
//             },
//             error: function (xhr, status, error) {
//                 toastr.error(basic_error_message);
//                 element.find("span").text("Add to cart");
//             },

//         })


// console.log($(this).data('id'));
// return false

//         $.ajax({
//             method: "post",
//             url: base_url + "/add-to-cart/" + $(this).data('id'),
//             data: {
//                 _token: csrf_token
//             },
//             beforeSend: function () {
//                 element.find("span").text("Adding...");
//             },
//             success: function (data) {
//                 if (data.status == "success") {
//                     toastr.success(data.message);
//                     $('.mini-cart-count').text(data.cart_count);
//                     if (data.dataLayer && typeof data.dataLayer === 'object') {
//                         dataLayer.push({
//                             'event': 'addToCart',
//                             'cart_details': data.dataLayer
//                         });
//                     }
                    
//                 } else {
//                     toastr.error(data.message);
//                 }

//                 element.find("span").text("Add to cart");
//             },
//             error: function (xhr, status, error) {
//                 toastr.error(basic_error_message);
//                 element.find("span").text("Add to cart");
//             },

//         })

//     })
    // apply coupon
    $('.coupon-form').on('submit', function (e) {
        e.preventDefault();

        let formData = $(this).serialize();
        $.ajax({
            method: "POST",
            url: base_url + "/apply-coupon",
            data: formData,
            beforeSend: function () {
                $('.coupon-form button').attr('disabled', true);
                $('.coupon-form button').text("Applying...");
            },
            success: function (data) {
                let html = `
                  <span>${discount}</span>
                    <br>
                  <small>${data.coupon_code} (${data.offer_percentage}%) <a class="ms-2 text-danger" href="/remove-coupon">×</a></small>
                `;
                $('.coupon-discount').html(html);
                $('.discount-amount').text(data.discount_amount);
                $('.amount').text(data.total);
                // reset form
                $('.coupon-form button').attr('disabled', false);
                $('.coupon-form button').text("Apply Coupon");
                $('.coupon-form')[0].reset();
                toastr.success(data.message);
            },
            error: function (xhr, status, error) {
                $('.coupon-form button').attr('disabled', false);
                $('.coupon-form button').text("Apply Coupon");
                if (xhr.responseJSON?.errors) {
                    $.each(xhr.responseJSON.errors, function (key, value) {
                        toastr.error(value);
                    });
                } else if (xhr.responseJSON?.message) {
                    toastr.error(xhr.responseJSON.message);
                }
            }
        })
    });
})