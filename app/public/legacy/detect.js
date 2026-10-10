(function () {
    var fallback = document.getElementById('legacy-fallback');
    if (!fallback || fallback.getAttribute('data-forced-modern') === '1') { return; }
    var supported = 'noModule' in document.createElement('script') &&
        typeof window.Proxy === 'function' && typeof window.Symbol === 'function' &&
        window.Reflect && typeof window.Reflect.get === 'function' &&
        typeof window.Promise === 'function' && typeof window.fetch === 'function';
    try {
        // Parse only: no dynamic import or async function is executed.
        new Function('return (null ?? 1) === 1 && ({a:1})?.a === 1;');
        new Function('return import("./unused.js");');
        new Function('return async function() {};');
    } catch (e) { supported = false; }
    if (!supported) {
        window.location.replace('/ui/legacy?return=' + encodeURIComponent(window.location.pathname + window.location.search));
        return;
    }
    setTimeout(function () {
        var app = document.getElementById('app');
        if (app && !app.hasChildNodes()) { fallback.style.display = 'block'; }
    }, 10000);
}());
