<li class="menu-header">{{ __('Settings') }}</li>
<li class="{{ Route::is('admin.general-setting') ? 'active' : '' }}"><a class="nav-link"
        href="{{ route('admin.general-setting') }}"><i class="fas fa-cog"></i>
        <span>{{ __('General Settings') }}</span></a></li>

<li class="{{ Route::is('admin.commission-setting') ? 'active' : '' }}"><a class="nav-link"
        href="{{ route('admin.commission-setting') }}"><i class="fas fa-money-bill"></i>
        <span>{{ __('Commission') }}</span></a></li>

<li class="{{ Route::is('admin.credential-setting') || Route::is('admin.crediential-setting') ? 'active' : '' }}">
    <a class="nav-link" href="{{ route('admin.credential-setting') }}"><i class="fas fa-key"></i>
        <span>{{ __('Credential Settings') }}</span>
    </a>
</li>
<li class="{{ Route::is('admin.marketing-setting') ? 'active' : '' }}">
    <a class="nav-link" href="{{ route('admin.marketing-setting') }}"><i class="fas fa-ad"></i>
        <span>{{ __('Marketing Settings') }}</span>
    </a>
</li>

<li class="{{ Route::is('admin.email-configuration') || Route::is('admin.edit-email-template') ? 'active' : '' }}">
    <a class="nav-link" href="{{ route('admin.email-configuration') }}"><i class="fas fa-envelope"></i>
        <span>{{ __('Email Configuration') }}</span>
    </a>
</li>

@if (Module::isEnabled('Language') && checkAdminHasPermission('language.view'))
    @include('language::sidebar')
@endif

<li class="{{ Route::is('admin.seo-setting') ? 'active' : '' }}">
    <a class="nav-link" href="{{ route('admin.seo-setting') }}"><i class="fas fa-search"></i>
        <span>{{ __('SEO Setup') }}</span>
    </a>
</li>
