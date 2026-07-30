"use strict";
/** On DOM load */
$(document).ready(function () {
    //place order
    $(document).on("click", ".place-order-btn", function (e) {
        e.preventDefault();
        const method = $(this).data("method");
         
        
        let url = `${base_url}/place-order/${method}`;
        $.ajax({
            url: url,
            type: "POST",
            dataType: "json",
            data: {
                _token: $('meta[name="csrf-token"]').attr("content"),
            },
            beforeSend: function () {
                $("#show_currency_notifications .alert-warning").addClass(
                    "d-none"
                );
                $(".preloader-two").removeClass("d-none");
            },
            success: (response) => { 
                
                if (response.success) {
                    window.location.href = `${base_url}/payment?invoice_id=${response.invoice_id}`;
                } else {
                    if (response.supportCurrency) {
                        $("#show_currency_notifications .alert-warning")
                            .html(response.supportCurrency)
                            .removeClass("d-none");
                    }
                    toastr.warning(response.messege);
                    $(".preloader-two").addClass("d-none");
                }
            },
            error: (error) => {
                const errorMessage = error.responseJSON?.message || basic_error_message;
                toastr.error(errorMessage);
                $(".preloader-two").addClass("d-none");
            },
        });
    });
});
