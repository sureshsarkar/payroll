@if (Module::isEnabled('PageTemplateBuilder') && Route::has('admin.page-template-builder.index'))
    {{-- <li class="{{ isRoute('admin.page-template-builder.*', 'active') }}">
        <a class="nav-link" href="{{ route('admin.page-template-builder.index') }}">
            <i class="fas fa-file"></i> <span>{{ __('Page Template Builder') }}</span>
        </a>
    </li> --}}

        <li
        class="nav-item dropdown {{ isRoute(['admin.page-template-builder.*', 'admin.page-template-category.*',], 'active') }}">
        <a href="javascript:void()" class="nav-link has-dropdown"><i class="fas fa-location-arrow"></i><span>{{ __('Template Builder') }}</span></a>

        <ul class="dropdown-menu">

             <li class="{{ isRoute('admin.page-template-category.*', 'active') }}">
                <a class="nav-link" href="{{ route('admin.page-template-category.index') }}">
                    {{ __('Add Category') }}
                </a>
            </li>


            <li class="{{ isRoute('admin.page-template-builder.*', 'active') }}">
                <a class="nav-link" href="{{ route('admin.page-template-builder.index') }}">
                    {{ __('Add Template') }}
                </a>
            </li>
        </ul>
    </li>



@endif
