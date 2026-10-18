const game = document.getElementById('game');
const gameActive = game.dataset.active === '1';
let submittingForm = false;

game.addEventListener('submit', () => {
    // Keep the game state between requests.
    submittingForm = true;
});

window.addEventListener('beforeunload', (event) => {
    if (gameActive && !submittingForm) {
        // The browser chooses the warning message.
        event.preventDefault();
        event.returnValue = '';
    }
});

window.addEventListener('pagehide', () => {
    if (gameActive && !submittingForm) {
        const data = new FormData();
        data.set('abandon', '1');
        data.set('game_token', game.elements.game_token.value);
        navigator.sendBeacon('juga.php', data);
    }
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        // Going back must not restore a previously submitted form.
        submittingForm = true;
        window.location.replace('index.html');
    }
});
