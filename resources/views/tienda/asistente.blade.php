{{-- Asistente IA de venta (v2, D194): solo para clientes con cuenta; si la IA falla, se oculta. --}}
<div class="asistente" id="asistente" data-url="{{ route('asistente', ['idioma' => app()->getLocale()]) }}">
    <button type="button" class="asistente-abrir" aria-expanded="false" aria-controls="asistente-panel">{{ __('asistente.abrir') }}</button>
    <section class="asistente-panel" id="asistente-panel" hidden aria-labelledby="asistente-titulo">
        <header><h2 id="asistente-titulo">{{ __('asistente.titulo') }}</h2><button type="button" class="asistente-cerrar" aria-label="{{ __('asistente.cerrar') }}">×</button></header>
        <ol class="asistente-mensajes" aria-live="polite"><li class="asistente">{{ __('asistente.bienvenida') }}</li></ol>
        <form class="asistente-formulario">
            <label class="oculto" for="asistente-texto">{{ __('asistente.escribe') }}</label>
            <input id="asistente-texto" name="texto" maxlength="1000" autocomplete="off" placeholder="{{ __('asistente.escribe') }}" required>
            <button type="submit">{{ __('asistente.enviar') }}</button>
        </form>
        <p class="asistente-aviso">{{ __('asistente.aviso') }}</p>
    </section>
</div>
<script>
(() => {
    const raiz = document.getElementById('asistente');
    const panel = raiz.querySelector('.asistente-panel'), abrir = raiz.querySelector('.asistente-abrir'), lista = raiz.querySelector('.asistente-mensajes');
    const historial = [];
    const mostrar = (si) => { panel.hidden = !si; abrir.setAttribute('aria-expanded', si); if (si) raiz.querySelector('input').focus(); };
    abrir.addEventListener('click', () => mostrar(panel.hidden));
    raiz.querySelector('.asistente-cerrar').addEventListener('click', () => mostrar(false));
    const anadir = (rol, texto) => { const li = document.createElement('li'); li.className = rol; li.textContent = texto; lista.append(li); lista.scrollTop = lista.scrollHeight; };
    raiz.querySelector('form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const campo = e.target.texto, texto = campo.value.trim();
        if (!texto) return;
        campo.value = ''; anadir('cliente', texto); historial.push({ rol: 'cliente', texto });
        try {
            const r = await fetch(raiz.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }, body: JSON.stringify({ mensajes: historial.slice(-12) }) });
            if (!r.ok) throw new Error();
            const { respuesta } = await r.json();
            anadir('asistente', respuesta); historial.push({ rol: 'asistente', texto: respuesta });
        } catch { raiz.remove(); } // la IA no responde: se oculta y la tienda sigue
    });
})();
</script>
