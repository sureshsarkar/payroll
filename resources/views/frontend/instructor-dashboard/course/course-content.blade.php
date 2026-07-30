@extends('frontend.instructor-dashboard.layouts.master')
@push('custom_meta')
    <meta name="course_id" content="{{ request('id') }}">
@endpush

@section('dashboard-contents')
    {{-- Create Course — Step 3 (Course Contents). 2026-07-04: wrapped in the corp
         design system (corp-page / corp-header / corp-form-card) and corp-styled
         the toolbar buttons. The chapter/lesson/quiz accordion BUILDER and every
         JS hook are preserved verbatim (courses.js + jquery-ui depend on
         .draggable-list, .course-section, .add-lesson-btn, .edit-chapter-btn,
         .delete-item, .dragger, .sort-chapter-btn, .create_couese_item,
         .navigation-btn[data-step], #accordionPanelsStayOpenExample, #exampleModal). --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    {{-- Step form (hidden hop to step 5). Untouched. --}}
    <form action="{{ route('instructor.courses.update') }}" class="instructor__profile-form course-form">
        @csrf
        <input type="hidden" name="step" value="3">
        <input type="hidden" name="next_step" value="5">
    </form>

    <div class="corp-page" id="courseContents">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Create Course') }}</h4>
                <p>{{ __('Build the curriculum — add chapters, then lessons, documents and quizzes inside each.') }}</p>
            </div>
            <div class="corp-header__actions">
                <a href="{{ route('instructor.courses.index') }}" class="btn-corp-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Courses') }}
                </a>
            </div>
        </div>

        @include('frontend.instructor-dashboard.course.navigation')

        <div class="corp-form-card">
            <div class="corp-form-card__head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-layer-group" style="color:var(--corp-brand);"></i> {{ __('Course Structure') }}
                    </h6>
                    <p class="corp-form-card__sub" style="margin-bottom:0;">{{ __('Drag to reorder. Use the + on a chapter to add lessons, documents or a quiz.') }}</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn-corp-secondary sort-chapter-btn">
                        <i class="fas fa-sort"></i> {{ __('Sort chapter') }}
                    </button>
                    <button type="button" class="btn-corp-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                        <i class="fas fa-plus"></i> {{ __('Add new chapter') }}
                    </button>
                </div>
            </div>
            <div class="corp-form-card__body">
                <form action="">
                    @csrf
                    <div class="accordion draggable-list" id="accordionPanelsStayOpenExample">
                        @forelse ($chapters as $chapter)
                            <div class="accordion-item course-section add_course_section_area">
                                <h2 class="accordion-header" id="panelsStayOpen-heading{{ $chapter->id }}">
                                    <div class="accordion_header_content d-flex flex-wrap">
                                        <button class="accordion-button course-section-btn collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#panelsStayOpen-collapse{{ $chapter->id }}"
                                            aria-expanded="true"
                                            aria-controls="panelsStayOpen-collapse{{ $chapter->id }}">
                                            <div class="icon_area d-flex flex-wrap justify-content-between align-items-center w-100">
                                                <div class="d-flex flex-wrap align-items-center">
                                                    <span class="icon-container"><i class="far fa-folder"></i></span>
                                                    <p class="mb-0 ms-2 bold-text">{{ $chapter->title }}</p>
                                                </div>
                                            </div>
                                        </button>

                                        <div class="item-action item_action_header d-flex flex-wrap">
                                            <div class="dropdown action-item">
                                                <span class="dropdown-toggle btn btn-small small-more-btn"
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-plus"></i>
                                                </span>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item add-lesson-btn" data-type="lesson"
                                                            data-chapterid="{{ $chapter->id }}" href="javascript:;">{{ __('Add Lesson') }}</a></li>
                                                    <li><a class="dropdown-item add-lesson-btn" data-type="document"
                                                            data-chapterid="{{ $chapter->id }}" href="javascript:;">{{ __('Add Document') }}</a></li>
                                                    <li><a class="dropdown-item add-lesson-btn" data-type="quiz"
                                                            data-chapterid="{{ $chapter->id }}" href="javascript:;">{{ __('Add Quiz') }}</a></li>
                                                </ul>
                                            </div>
                                            <a href="javascript:;" class="text-dark action-item edit-chapter-btn"
                                                data-chapterid="{{ $chapter->id }}"><i class="fas fa-edit"></i></a>
                                            <a href="{{ route('instructor.course-chapter.destroy', $chapter->id) }}"
                                                class="text-danger action-item delete-item"><i class="fas fa-trash-alt"></i></a>
                                        </div>
                                    </div>
                                </h2>
                                <div id="panelsStayOpen-collapse{{ $chapter->id }}"
                                    class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                    aria-labelledby="panelsStayOpen-heading{{ $chapter->id }}">
                                    <div class="accordion-body">
                                        @forelse ($chapter->chapterItems as $chapterItem)
                                            @if ($chapterItem->type == 'lesson' || $chapterItem->type == 'live')
                                                <div class="card course-section-item create_couese_item mb-3"
                                                    data-chapter-item-id="{{ $chapterItem->id }}" data-chapterid="{{ $chapter->id }}">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                        <div class="edit_course_icons d-flex flex-wrap align-items-center">
                                                            <span class="icon-container"><i class="fas {{$chapterItem->type == 'lesson' ? 'fa-video' : 'fa-chalkboard-teacher'}}"></i></span>
                                                            <p class="mb-0 ms-2 bold-text">{{ truncate($chapterItem?->lesson?->title) }}</p>
                                                        </div>
                                                        <div class="item-action">
                                                            <a href="javascript:;" class="ms-2 text-dark edit-lesson-btn"
                                                                data-type="{{ $chapterItem->type }}" data-courseid="{{ $chapter->course_id }}"
                                                                data-chapterid="{{ $chapter->id }}" data-chapter_item_id="{{ $chapterItem->id }}"><i class="fas fa-edit"></i></a>
                                                            <a href="{{ route('instructor.course-chapter.lesson.destroy', $chapterItem->id) }}" class="ms-2 text-danger delete-item"><i class="fas fa-trash-alt"></i></a>
                                                            <a href="javascript:;" class="ms-2 dragger"><i class="fas fa-arrows-alt"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif ($chapterItem->type == 'document')
                                                <div class="card course-section-item create_couese_item mb-3"
                                                    data-chapter-item-id="{{ $chapterItem->id }}" data-chapterid="{{ $chapter->id }}">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                        <div class="edit_course_icons d-flex flex-wrap align-items-center">
                                                            <span class="icon-container"><i class="fas fa-file-pdf"></i></span>
                                                            <p class="mb-0 ms-2 bold-text">{{ truncate($chapterItem?->lesson?->title) }}</p>
                                                        </div>
                                                        <div class="item-action">
                                                            <a href="javascript:;" class="ms-2 text-dark edit-lesson-btn"
                                                                data-type="{{ $chapterItem->type }}" data-courseid="{{ $chapter->course_id }}"
                                                                data-chapterid="{{ $chapter->id }}" data-chapter_item_id="{{ $chapterItem->id }}"><i class="fas fa-edit"></i></a>
                                                            <a href="{{ route('instructor.course-chapter.lesson.destroy', $chapterItem->id) }}" class="ms-2 text-danger delete-item"><i class="fas fa-trash-alt"></i></a>
                                                            <a href="javascript:;" class="ms-2 dragger"><i class="fas fa-arrows-alt"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="accordion card mb-2" id="accordionExample"
                                                    data-chapter-item-id="{{ $chapterItem->id }}" data-chapterid="{{ $chapter->id }}">
                                                    <div class="accordion-item">
                                                        <h2 class="accordion-header">
                                                            <div class="d-flex flex-wrap justify-content-between">
                                                                <button class="accordion-button course-quiz-btn collapsed"
                                                                    type="button" data-bs-toggle="collapse"
                                                                    data-bs-target="#panelsStayOpen-collapse{{ $chapterItem->id }}"
                                                                    aria-expanded="true" aria-controls="panelsStayOpen-collapse{{ $chapterItem->id }}">
                                                                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                                        <div class="d-flex flex-wrap align-items-center">
                                                                            <span class="icon-container"><i class="fas fa-question"></i></span>
                                                                            <p class="mb-0 ms-2 bold-text">{{ $chapterItem?->quiz?->title }}</p>
                                                                        </div>
                                                                    </div>
                                                                </button>
                                                                <div class="item-action d-flex flex-wrap">
                                                                    <div class="dropdown action-item">
                                                                        <span class="dropdown-toggle btn btn-small small-more-btn"
                                                                            data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-plus"></i></span>
                                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                                            <li><a class="dropdown-item add-quiz-question-btn"
                                                                                    data-quiz-id="{{ $chapterItem->quiz?->id }}" href="javascript:;">{{ __('Add Question') }}</a></li>
                                                                        </ul>
                                                                    </div>
                                                                    <a href="javascript:;" data-type="{{ $chapterItem->type }}"
                                                                        data-courseid="{{ $chapter->course_id }}" data-chapterid="{{ $chapter->id }}"
                                                                        data-chapter_item_id="{{ $chapterItem->id }}" class="text-dark action-item edit-lesson-btn"><i class="fas fa-edit"></i></a>
                                                                    <a href="{{ route('instructor.course-chapter.lesson.destroy', $chapterItem->id) }}" class="text-danger action-item delete-item"><i class="fas fa-trash-alt"></i></a>
                                                                    <a href="javascript:;" class="ms-2 dragger"><i class="fas fa-arrows-alt"></i></a>
                                                                </div>
                                                            </div>
                                                        </h2>
                                                        <div id="panelsStayOpen-collapse{{ $chapterItem->id }}"
                                                            class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                                                            <div class="accordion-body">
                                                                @forelse ($chapterItem?->quiz?->questions as $question)
                                                                    <div class="card course-section-item mb-3" data-chapter-item-id="" data-chapterid="">
                                                                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                                            <div class="edit_course_icons d-flex flex-wrap align-items-center">
                                                                                <span class="icon-container"><i class="far fa-question-circle"></i></span>
                                                                                <p class="mb-0 ms-2 bold-text">{{ $question->title }}</p>
                                                                            </div>
                                                                            <div class="item-action">
                                                                                <a href="javascript:;" class="ms-2 text-dark edit-question-btn" data-question-id="{{ $question->id }}"><i class="fas fa-edit"></i></a>
                                                                                <a href="{{ route('instructor.course-chapter.quiz-question.destroy', $question->id) }}" class="ms-2 text-danger delete-item"><i class="fas fa-trash-alt"></i></a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <p class="text-center">{{ __('No questions found.') }}</p>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @empty
                                            <p class="text-center">{{ __('No lessons found.') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="pp-empty" style="text-align:center;padding:44px 20px;color:var(--corp-muted);">
                                <div style="font-size:30px;color:var(--corp-brand-border);margin-bottom:8px;"><i class="fas fa-folder-open"></i></div>
                                <div style="font-weight:600;color:var(--corp-text);">{{ __('No chapters yet') }}</div>
                                <div style="font-size:12.5px;margin-top:4px;">{{ __('Click “Add new chapter” to start building your curriculum.') }}</div>
                            </div>
                        @endforelse
                    </div>
                </form>
            </div>
        </div>

        <div class="corp-sticky-bar">
            <div class="corp-sticky-bar__summary">
                <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                <span><strong>{{ __('Step 3 of 4') }}</strong> <span class="text-muted">·</span> {{ __('Course Contents') }}</span>
            </div>
            <div class="corp-sticky-bar__actions">
                <button class="btn-corp-primary navigation-btn" role="presentation" id="itemFour-tab" data-step="5">
                    {{ __('Save & Continue') }} <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('global/js/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('global/js/jquery.ui.touch-punch.min.js') }}"></script>
    <script src="{{ asset('frontend/js/default/courses.js') }}?v={{ $setting?->version }}"></script>
@endpush
