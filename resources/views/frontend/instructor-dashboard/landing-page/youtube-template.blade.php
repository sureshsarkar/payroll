  <style>
      #youtubeGrid {
          box-shadow: rgba(60, 64, 67, 0.3) 0px 1px 2px 0px, rgba(60, 64, 67, 0.15) 0px 1px 3px 1px;
      }
  </style>

  <div class="container py-5">
      <h2 style="text-align: center; margin-bottom: 5px;font-family: Marcellus, serif;font-size: 54px;color: #0F3D3E;">
          Youtube Videos</h2>
      <p style="color:#8d9b9b; text-align: center; margin-bottom:20px;">Discover the latest and most engaging videos from
          our YouTube channel, covering trending topics and valuable insights.</p>
      <div class="row">
          @foreach ($youtubeVideos->items as $video)
              @if ($video->id->kind == 'youtube#video')
                  <div class="col-md-4 mb-4">
                      <div
                          style="padding: 10px;box-shadow: rgba(60, 64, 67, 0.3) 0px 1px 2px 0px, rgba(60, 64, 67, 0.15) 0px 1px 3px 1px;border-radius: 10px;">
                          <iframe width="100%" height="200"
                              src="https://www.youtube.com/embed/{{ $video->id->videoId }}" frameborder="0"
                              allowfullscreen>
                          </iframe>
                          <h6>{{ $video->snippet->title }}</h6>
                      </div>
                  </div>
              @endif
          @endforeach
      </div>
  </div>
