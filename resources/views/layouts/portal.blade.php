{{-- Layout Portal Inovasi: kerangka aplikasi + pita portal --}}
@php
    $inner = view('layouts.partials.portal-frame', ['slot' => $slot])->render();
@endphp
@include('layouts.app', ['slot' => new \Illuminate\Support\HtmlString($inner), 'inPortal' => true])
