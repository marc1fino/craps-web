const joc = document.getElementById('joc');
const partidaActiva = joc.dataset.activa === '1';
let enviantFormulari = false;

joc.addEventListener('submit', () => {
    // Los botones del juego deben conservar la partida entre peticiones.
    enviantFormulari = true;
});

window.addEventListener('beforeunload', (event) => {
    if (partidaActiva && !enviantFormulari) {
        // El navegador elige el texto del aviso. Cancelarlo no borra nada.
        event.preventDefault();
        event.returnValue = '';
    }
});

window.addEventListener('pagehide', () => {
    if (partidaActiva && !enviantFormulari) {
        const datos = new FormData();
        datos.set('abandonar', '1');
        datos.set('partida_token', joc.elements.partida_token.value);
        navigator.sendBeacon('juga.php', datos);
    }
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        // Volver con Atrás no debe recuperar un formulario ya abandonado.
        enviantFormulari = true;
        window.location.replace('index.html');
    }
});
