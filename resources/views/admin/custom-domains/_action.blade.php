{{-- Small POST-button partial for a one-click admin action.
     Vars: route, class, icon, label, confirm (optional). --}}
<form action="{{ $route }}" method="POST" style="display:inline;"
      @if(!empty($confirm)) onsubmit="return confirm('{{ $confirm }}');" @endif>
    @csrf
    <button class="btn btn-sm {{ $class }}" title="{{ $label }}"><i class="fas {{ $icon }}"></i></button>
</form>
