{{-- Instructor live-class launcher.
     Identical to the student launcher — the role (host vs. participant)
     is decided server-side in ZoomSignatureController based on
     `course.instructor_id == userAuth()->id`, so the same view works for
     both audiences. Kept as a thin include to avoid drift.
--}}
@include('frontend.student-dashboard.live.zoom')
