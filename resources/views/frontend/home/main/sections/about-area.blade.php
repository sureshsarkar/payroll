
 <section class="about-sec-wrapper">
     <div class="container">
         <div class="row">
             <div class="col-lg-6 col-md-6 col-12">
                 <div class="home-about-image">
                     <!-- <img src="{{ asset($aboutSection?->global_content?->image) }}"> -->
                     <img src="{{ asset($aboutSection?->global_content?->image??'frontend/img/about-news.png') }}" alt="" class="img-fluid">

                 </div>
             </div>
             <div class="col-lg-6 col-md-6 col-12">
               <div class="home-abt-con">
                  <div class="home-abt-con-inn">
                     {!! clean($aboutSection?->content?->description) !!}
                     <a href="{{route('about-us')}}">Read More</a>
                  </div>
               </div>
             </div>
         </div>
     </div>
 </section>