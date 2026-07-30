         <!-- FOOTER -->
         <footer class="footer--theme">
            <div class="container">
                <div class="row">
                    <div class="col-lg-5 col-md-12 col-12">
                         <div class="fl">
                 <a href="{{ url('privacy-policy') }}">Privacy Policy</a>
                 <a href="{{ url('terms-and-conditions') }}">Terms of Service</a>
             </div>
                    </div>
                    <div class="col-lg-7 col-md-12 col-12">
                        {{-- 2026-05-21 P4 — per-coach white-label.
                             $brand is auto-injected by the view composer
                             and respects host-based tenant resolution
                             (P2) — students on coach1.com see coach1's
                             footer text + brand name. Coach 0/platform
                             default falls back to "© YEAR Brand · All
                             rights reserved." --}}
                        <div class="fc text-right">{{ $brand->footerText ?: ('© ' . date('Y') . ' ' . $brand->name . ' · ' . __('All rights reserved.')) }}</div>
                    </div>
                </div>
            
             
             </div>
         </footer>
