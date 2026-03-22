(function () {
    if (!('serviceWorker' in navigator)) {
        return;
    }
    const register = function () {
        navigator.serviceWorker.register('/service-worker.js')
        .catch((err) => console.warn('SW register failed', err));
    };
    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register, { once: true });
    }
})();
