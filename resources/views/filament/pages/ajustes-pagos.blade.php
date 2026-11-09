<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Pasarela de pago</x-slot>
        <x-slot name="description">Redsys, el TPV virtual del banco: el cliente paga con tarjeta o Bizum en la página segura del banco. Los datos del TPV van solo en el .env del servidor.</x-slot>
        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; font-size: .875rem;">
            <div><dt style="opacity: .7;">Pasarela</dt><dd style="margin: .25rem 0 0;">{{ $pasarela === 'redsys' ? 'Redsys' : 'Pago simulado (solo pruebas)' }}</dd></div>
            <div><dt style="opacity: .7;">Entorno</dt><dd style="margin: .25rem 0 0;">
                <x-filament::badge :color="$pruebas ? 'warning' : 'success'" style="display: inline-flex;">{{ $pruebas ? 'Pruebas: no se cobra' : 'Real' }}</x-filament::badge></dd></div>
            <div><dt style="opacity: .7;">Métodos</dt><dd style="margin: .25rem 0 0;">{{ implode(' y ', $metodos) }}</dd></div>
            <div><dt style="opacity: .7;">Comercio</dt><dd style="margin: .25rem 0 0;">{{ $comercio ?? 'Sin configurar (REDSYS_MERCHANT_CODE)' }}</dd></div>
            <div><dt style="opacity: .7;">Clave secreta</dt><dd style="margin: .25rem 0 0;">{{ $clave ? 'Configurada (no se muestra)' : 'Falta (REDSYS_SECRET_KEY)' }}</dd></div>
            <div><dt style="opacity: .7;">URL de notificación para el banco</dt><dd style="margin: .25rem 0 0; word-break: break-all;"><code>{{ $aviso }}</code></dd></div>
        </dl>
    </x-filament::section>
    <x-filament::section>
        <x-slot name="heading">Cómo se configura</x-slot>
        <ol style="font-size: .875rem; padding-left: 1.25rem; display: grid; gap: .35rem;">
            <li>En el .env del servidor: <code>PAGOS_PASARELA=redsys</code>, <code>REDSYS_MERCHANT_CODE</code>, <code>REDSYS_TERMINAL</code>, <code>REDSYS_SECRET_KEY</code> y <code>REDSYS_ENVIRONMENT</code> (<code>test</code> o <code>live</code>). Bizum, con <code>REDSYS_BIZUM=true</code> si el banco lo activa.</li>
            <li>Al banco: la URL de notificación de arriba y «parámetros en las URLs».</li>
            <li>Primero en pruebas (no se cobra) y, cuando todo cuadre, <code>live</code>.</li>
        </ol>
    </x-filament::section>
</x-filament-panels::page>
