@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')


    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <title>MBS Guru - Manding Page Builder</title>
        <meta content="Best Free Open Source Responsive No-Code Newsletter HTML Email Builder" name="description">
        <link rel="stylesheet" href="{{ asset('frontend/css/grapes.min.css') }}">
        <link rel="stylesheet" href="{{ asset('frontend/css/material.css') }}">
        <link rel="stylesheet" href="{{ asset('frontend/css/tooltip.css') }}">
        <link rel="stylesheet" href="{{ asset('frontend/css/demos.css') }}">

        <script src="{{ asset('frontend/js/grapes.min.js') }}"></script>
        <script src="{{ asset('frontend/js/index.js') }}"></script>
        <script src="https://unpkg.com/grapesjs-preset-newsletter@1.0.1"></script>
    </head>

    <style>
        .nl-link {
            color: inherit;
        }

        .gjs-logo-version {
            background-color: #5a606d;
        }

        .cke_toolbar.cke_toolbar {
            min-height: 33px;
        }

        #go-to-dashboard {
            position: fixed;
            top: 9px;
            left: 130px;
            z-index: 9999;
            padding: 1px 4px;
            background: #35d7bb;
            color: #000000;
            border: none;
            border-radius: 2px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        #save-btn {
            position: fixed;
            top: 9px;
            right: 37px;
            z-index: 9999;
            padding: 1px 4px;
            background: #35d7bb;
            color: #000000;
            border: none;
            border-radius: 2px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }
    </style>

    <body>
 
        <button id="save-btn">Save</button>
        <div id="gjs" style="height:0px; overflow:hidden">

 
 
            {!! file_get_contents(public_path($template_file->file)) !!}
 

        </div>
  

        <script>
            document.addEventListener("DOMContentLoaded", function() {

                var host = 'https://grapesjs.com/';

                var editor = grapesjs.init({
                    container: '#gjs',
                    height: '100%',
                    fromElement: true,
                    clearOnRender: true,

                    storageManager: false, // Disable local storage (we use DB)

                    assetManager: {
                        upload: "{{ route('instructor.landing-page-builder.media.upload') }}",
                        uploadName: 'file',
                        headers: {
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        autoAdd: true
                    },

                    plugins: ['grapesjs-preset-newsletter'],
                    pluginsOpts: {
                        'grapesjs-preset-newsletter': {}
                    }
                });


                // ✅ LOAD SAVED DATA 
                editor.onReady(function() {

                    @if (strlen($page->html_content) > 20)
                        const projectData = @json(json_decode($page->json_content, true));
                        editor.loadProjectData(projectData);
                        // Check if assets exist
                        // if (projectData && projectData.assets && projectData.assets.length > 0) {
                        //     editor.loadProjectData(projectData);
                        // }
                        // @else
                        // First time: auto-save the parsed template immediately
                        //setTimeout(() => saveBuilder(), 1000);
                    @endif

                });


                // ✅ SAVE FUNCTION
                function saveBuilder() {
                    // return false
                    const html = editor.getHtml();
                    const css = editor.getCss();
                    const json = editor.getProjectData();
                    // console.log(html);
                    // console.log(css);
                    console.log(json);

                    fetch("{{ route('instructor.landing-page-builder.save', $page->id) }}", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: JSON.stringify({
                                html: html,
                                css: css,
                                json: json
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            console.log(data);

                            // console.log("Saved!");
                        })
                        .catch(err => console.log(err));
                }


                // ✅ MANUAL SAVE BUTTON
                document.getElementById('save-btn').addEventListener('click', function() {
                    saveBuilder();
                    alert("Page Saved Successfully ✅");
                });


                // ✅ AUTO SAVE (Every 2 seconds after changes)
                // editor.on('update', function() {
                //     clearTimeout(window.autoSaveTimer);
                //     window.autoSaveTimer = setTimeout(() => {
                //         saveBuilder();
                //     }, 2000);
                // });

            });
        </script>



    </body>

    </html>
