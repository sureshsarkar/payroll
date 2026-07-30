 
    <div class="container">
        <div class="service-head-box mb-4">
            <div class="home-abt-con-inn"> 
                <h5><i class="fas fa-star-of-life"></i>Certificates</h5>
                <h3>Our Certificates</h3>
                <p>Official certificates recognizing our achievements, quality standards, and professional excellence.
                </p>
            </div>
        </div>
        <div class="row">
            @foreach ($certificate_section?->global_content?->images as $c)
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="certificate-box">
                        <img src="{{ asset($c??'frontend/img/cert.png') }}">
                    </div>
                </div>
            @endforeach 
        </div>
    </div> 
