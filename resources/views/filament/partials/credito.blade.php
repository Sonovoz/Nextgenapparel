{{-- Pie del backend con el crédito de Minimedia (los estilos van aquí: el backend no carga tienda.css). --}}
<style>
    .credito-backend { display: flex; justify-content: center; padding: 18px 16px 22px; }
    .credito-minimedia { display: inline-flex; align-items: center; gap: 10px; color: inherit; text-decoration: none; opacity: .65; font-size: 12px; transition: opacity .2s ease; }
    .credito-minimedia:hover, .credito-minimedia:focus-visible { opacity: 1; }
    .credito-marca { position: relative; display: inline-flex; flex-direction: column; line-height: 1; font-size: 17px; letter-spacing: .01em; font-weight: 300; padding-top: 7px; }
    .credito-marca sup { position: absolute; right: -9px; top: 6px; font-size: 8px; }
    .credito-marca small { font-size: 9px; letter-spacing: .04em; margin-top: 2px; }
    .credito-bits { position: absolute; right: 0; top: -2px; font-size: 7px; letter-spacing: .08em; font-family: ui-monospace, monospace; }
</style>
<div class="credito-backend">@include('partials.credito-minimedia')</div>
