$(document).ready(function () {
    var removedImages = [];
    tinymce.init({
        selector: '.text-editor-img',
        height: 200,
        image_class_list: [
        {title: 'image-popup', value: 'image-popup'},
        ],
        setup: function (editor) {
            editor.on('init', function () {
                previousContent = editor.getContent(); // Store the initial content
            });
    
            // Handle content changes
            editor.on('NodeChange', function (e) {
                var currentContent = editor.getContent();
                
                // Compare the previous content with the current content to detect if an image was removed
                if (previousContent !== currentContent) {
                    // Check for removed images by comparing previousContent and currentContent
                    var previousImages = $(previousContent).find('img');
                    var currentImages = $(currentContent).find('img');
                    
                    previousImages.each(function (index, img) {
                        var src = $(img).attr('src');
                        
                        // If an image in the previous content is not in the current content, it was removed
                        if (currentImages.filter(`[src="${src}"]`).length === 0) {
                            // Image removed, handle deletion
                            $.ajax({
                                type: 'DELETE',
                                url: base_url + '/tinymce-delete-image',
                                data: { file_path: src },
                                success: function(response) {},
                                error: function(xhr) {}
                            });
                        }
                    });
                    
                    // Update previous content
                    previousContent = currentContent;
                }
            });
            
        },
        plugins:"link image",
        toolbar: "bold italic | link | image",
        menubar: false,

        image_title: true,
        automatic_uploads: true,
        images_upload_url: base_url + "/tinymce-upload-image",
        file_picker_types: 'image',
        file_picker_callback: function(cb, value, meta) {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.onchange = function() {
                var file = this.files[0];

                var reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = function () {
                    var id = 'blobid' + (new Date()).getTime();
                    var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
                    var base64 = reader.result.split(',')[1];
                    var blobInfo = blobCache.create(id, file, base64);
                    blobCache.add(blobInfo);
                    cb(blobInfo.blobUri(), { title: file.name });
                };
            };
            input.click();
        }
    });

    tinymce.init({
        selector: ".text-editor",
        // 2026-07-07 — a hidden TinyMCE <textarea> that still carries the native
        // HTML5 `required` attribute breaks submission with
        //   "An invalid form control with name='…' is not focusable"
        // because the real textarea is display:none, so the browser can't focus
        // it to show the validation bubble. Move the requirement off the native
        // attribute: strip it once the editor mounts, remember it as
        // data-required, and enforce it in the global submit guard below (which
        // runs AFTER triggerSave copies the editor content into the textarea).
        // If JS/TinyMCE never loads, the textarea stays visible and its native
        // `required` still works — no regression.
        init_instance_callback: function (editor) {
            var ta = editor.getElement();
            if (ta && ta.hasAttribute && ta.hasAttribute("required")) {
                ta.removeAttribute("required");
                ta.setAttribute("data-required", "1");
            }
        },
        plugins:
            "anchor autolink charmap emoticons image link lists searchreplace visualblocks wordcount ",
        toolbar:
            "undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons",
        tinycomments_mode: "embedded",
        tinycomments_author: "Author name",
        menubar: false,
        mergetags_list: [
            {
                value: "First.Name",
                title: "First Name",
            },
            {
                value: "Email",
                title: "Email",
            },
        ],
    });

    // 2026-07-07 — global submit guard for every TinyMCE (.text-editor) form.
    // 1) triggerSave() copies each editor's content back into its <textarea> so
    //    the posted value is current (TinyMCE hides the textarea).
    // 2) enforces the soft "data-required" set in init_instance_callback above,
    //    so a required rich-text field can't be submitted empty — without the
    //    native "not focusable" crash. Attached in capture phase so it runs
    //    before any per-form submit handler. Covers all coach modules that use
    //    .text-editor, present and future; harmless on forms that have none.
    document.addEventListener(
        "submit",
        function (e) {
            if (typeof tinymce === "undefined") return;
            var form = e.target;
            if (!form || !form.querySelectorAll) return;

            tinymce.triggerSave();

            var missing = null;
            form
                .querySelectorAll('textarea.text-editor[data-required="1"]')
                .forEach(function (ta) {
                    var text = (ta.value || "")
                        .replace(/<[^>]*>/g, "")
                        .replace(/&nbsp;/gi, " ")
                        .trim();
                    if (!text && !missing) missing = ta;
                });

            if (missing) {
                e.preventDefault();
                e.stopPropagation();
                var ed = tinymce.get(missing.id);
                if (ed) ed.focus();
                var msg = "Please fill in the required content before submitting.";
                if (window.toastr) {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            }
        },
        true
    );
})