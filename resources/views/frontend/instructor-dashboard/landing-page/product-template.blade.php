
@if ($page->slug=="virendrastrengthyoga")
  <div style="max-width:1200px;margin:0 auto;">

    <div style="text-align:center;margin-bottom:56px;">
      <span style="font-size:0.75rem;font-weight:600;letter-spacing:0.2em;text-transform:uppercase;color:#F47A2A;">🗓️ Our Upcoming</span>
      <h2 style="font-family:Elsie, serif;font-size:clamp(2rem,4vw,2.9rem);font-weight:700;color:#1F2937;margin:12px 0 0;">Classes &amp; Schedule</h2>
      <div style="width:56px;height:3px;background:linear-gradient(90deg,#F47A2A,#f9a05a);border-radius:2px;margin:16px auto 0;"></div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;">
  @foreach ($products as $index => $p)
      <!-- Schedule Card Macro -->
      <div style="background:white;border-radius:20px;padding:28px;border:1px solid #F3F4F6;box-shadow:0 4px 24px rgba(0,0,0,0.06);transition:all 0.3s;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 16px 48px rgba(244,122,42,0.12)';this.style.borderColor='rgba(244,122,42,0.25)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 24px rgba(0,0,0,0.06)';this.style.borderColor='#F3F4F6'">
       <span ><img src="{{ asset($p->thumbnail) }}" alt="{{ $p->title }}" style="height:25vh;width:100%"></span>
        <h4 style="font-family:Elsie, serif;font-size:1.25rem;font-weight:700;color:#1F2937;margin:0 0 8px;">{{ $p->title }}</h4>
        <p style="font-size:0.84rem;color:#9CA3AF;margin:0 0 20px;line-height:1.6;">{{ \Illuminate\Support\Str::limit(strip_tags($p->description), 150, '...') }}</p>
       
        <a href="#" class="service-link" style="display:block;text-align:center;background:#FFF0E6;color:#F47A2A;padding:11px;border-radius:10px;font-size:0.85rem;font-weight:600;text-decoration:none;border:1.5px solid rgba(244,122,42,0.2);" onmouseover="this.style.background='#F47A2A';this.style.color='white'" onmouseout="this.style.background='#FFF0E6';this.style.color='#F47A2A'" data-bs-toggle="modal" data-bs-target="#serviceProductModal"
                data-id="{{ $p->id }}">Enquiry Now</a>
      </div>
    @endforeach 
    </div>
  </div>
@elseif($page->slug="advaityog")
<style>
     /* ── COURSES GRID ── */
  .courses-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; }
 @media (max-width:900px) {
     .courses-grid { grid-template-columns:1fr 1fr; }
 }
 @media (max-width:600px) {
        .courses-grid { grid-template-columns:1fr; }
 }
</style>
  <div style="max-width:1280px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:56px;">
      <div style="display:inline-flex;align-items:center;gap:8px;margin-bottom:20px;">
        <div style="width:32px;height:2px;background:#2C7A7B;"></div>
        <span style="color:#2C7A7B;font-size:12px;letter-spacing:2px;text-transform:uppercase;font-weight:500;">Classes</span>
        <div style="width:32px;height:2px;background:#2C7A7B;"></div>
      </div>
      <h2 style="font-family:Marcellus, serif;font-size:clamp(28px,4vw,54px);font-weight:400;color:#0F3D3E;margin:0 0 16px 0;letter-spacing:-0.3px;">Explore Our Latest &amp; <span style="font-style:italic;">Courses</span></h2>
      <p style="color:#6a8080;font-size:15px;max-width:600px;margin:0 auto;font-weight:300;line-height:1.75;">Join our regular workshops and classes designed to deepen your yoga and meditation. Suitable for all levels, these sessions offer holistic benefits for your mind and body.</p>
    </div>
    <div class="courses-grid">
          @foreach ($products as $index => $p)
      <!-- Card 1 -->
      
      <div style="background:white;border-radius:28px;overflow:hidden;box-shadow:0 4px 32px rgba(15,61,62,0.08);border:1px solid #e8f0ef;">
        <div style="position:relative;height:240px;overflow:hidden;">
          <img src="{{ asset($p->thumbnail) }}" alt="{{ $p->title }}" style="width:100%;height:100%;object-fit:cover;display:block;">
          <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(15,61,62,0.6),transparent);"></div> 
        </div>
        <div style="padding:24px;">
          <h3 style="font-family:Marcellus, serif;font-size:24px;font-weight:500;color:#0F3D3E;margin:0 0 8px 0;">{{ $p->title }}</h3>
          <p style="color:#6a8080;font-size:14px;line-height:1.7;margin:0 0 24px 0;font-weight:300;">{{ \Illuminate\Support\Str::limit(strip_tags($p->description), 150, '...') }}</p>
          <a href="#" style="display:flex;align-items:center;justify-content:center;background:#0F3D3E;color:white;padding:13px 24px;border-radius:50px;font-size:13px;font-weight:500;text-decoration:none;letter-spacing:0.8px;text-transform:uppercase;" class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal"
                data-id="{{ $p->id }}">Enquiry Now</a>
        </div>
      </div> 
      @endforeach
    </div>
  </div>
@else

<style>
.section-title,.service-card h3{font-family:'Playfair Display',serif}.service-card{background:#111;padding:26px 40px;transition:background .3s;cursor:default;position:relative;overflow:hidden}.service-card::before{content:'';position:absolute;bottom:0;left:0;width:100%;height:3px;background:#c8973a;transform:scaleX(0);transform-origin:left;transition:transform .4s}.service-card:hover{background:#161208}.service-card:hover::before{transform:scaleX(1)}.service-card h3{font-weight:700;color:#fff;margin-bottom:14px;margin-top:14px}.service-card p{font-size:.9rem;line-height:1.75;color:rgba(255,255,255,.5)}.service-icon img{height:25vh;width:100%}.service-card h3{font-size:19px}.service-link{padding:8px 14px;font-weight:500;border:none;gap:14px;margin-top:15px}.service-link:hover{gap:14px;column-gap:14px;background:#dddcdc;font-weight:600}#servicesGrid{padding:100px 60px;background:#0d0d0d}.services-header{text-align:center;max-width:600px;margin:0 auto 70px}.services-header .section-title{color:#fff}.services-header .section-text{color:rgba(255,255,255,.55)}.section-text{font-size:1rem;line-height:1.85;color:#6b6b6b;margin-bottom:32px}.section-title{font-size:clamp(2rem, 3.5vw, 2.8rem);font-weight:700;line-height:1.2;color:#0d0d0d;margin-bottom:24px}.services-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:rgba(200,151,58,.15);max-width:1200px;margin:0 auto;border:1px solid rgba(200,151,58,.15)}
@media (max-width: 992px) {.services-grid { grid-template-columns: repeat(2, 1fr);}#servicesGrid {padding-left: 30px;padding-right: 30px;}}
/* Mobile */
@media (max-width:576px){.services-grid{grid-template-columns:1fr}#servicesGrid{padding-left:15px;padding-right:15px}.service-card{padding:20px}.service-icon img{height:200px;object-fit:cover}.service-card h3{font-size:18px}.service-card p{font-size:14px}}
</style>
<div class="services-header">
    <h2 class="section-title" style="color:#ffffff;">Services Built for Impact</h2>
    <p class="section-text" style="color:rgba(255,255,255,0.5);">From concept to launch, we offer a complete
        suite of digital services tailored to elevate your brand.</p>
</div>
<div class="services-grid">
    @foreach ($products as $index => $p)
        <div class="service-card">
            <span class="service-icon"><img src="{{ asset($p->thumbnail) }}" alt=""></span>
            <h3>{{ $p->title }}</h3>
            <p>{{ \Illuminate\Support\Str::limit(strip_tags($p->description), 150, '...') }}</p>
            <button class="service-link" data-bs-toggle="modal" data-bs-target="#serviceProductModal"
                data-id="{{ $p->id }}">Enquiry Now</button>
        </div>
    @endforeach
</div>
@endif