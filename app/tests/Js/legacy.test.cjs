const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const asset = name => fs.readFileSync(path.join(__dirname, '../../public/legacy', name), 'utf8');

test('missing modern APIs redirects to Blade with the original path', () => {
    let destination;
    const context = {
        window: { location: { pathname: '/watch/movies/example.mp4', search: '?test=1', replace(url) { destination = url; } } },
        document: { getElementById() { return { getAttribute() { return '0'; } }; }, createElement() { return {}; } },
        setTimeout() {},
    };
    vm.runInNewContext(asset('detect.js'), context);
    const result = new URL(destination, 'http://localhost');
    assert.equal(result.pathname, '/ui/legacy');
    assert.equal(result.searchParams.get('return'), '/watch/movies/example.mp4?test=1');
});

test('manual modern override does not trigger a redirect loop', () => {
    vm.runInNewContext(asset('detect.js'), {
        document: { getElementById() { return { getAttribute() { return '1'; } }; } },
        window: { location: { replace() { throw new Error('unexpected redirect'); } } },
    });
});

function player(kind = 'mp4') {
    const videoEvents = {};
    const requests = [];
    let interval;
    const video = {
        currentTime: 0, duration: 300, paused: true,
        getAttribute(key) { return { 'data-kind': kind, 'data-source': '/media', 'data-path': 'movies/日本語 #1.mp4', 'data-position': '45' }[key]; },
        addEventListener(name, handler) { videoEvents[name] = handler; },
        play() { this.paused = false; }, // Old browsers return no Promise.
        pause() { this.paused = true; },
        canPlayType() { return 'maybe'; },
    };
    const elements = { 'legacy-video': video, 'playback-status': {}, 'progress-status': {} };
    for (const name of ['play', 'pause', 'rewind', 'forward', 'fullscreen']) elements[name] = {};
    if (kind === 'hls') {
        for (const name of ['quality', 'audio']) elements[name] = { options: [{}], appendChild() {}, remove() {} };
    }
    function XHR() { requests.push(this); }
    XHR.prototype.open = function (method, url) { this.method = method; this.url = url; };
    XHR.prototype.setRequestHeader = function (name, value) { this.headers = this.headers || {}; this.headers[name] = value; };
    XHR.prototype.send = function (body) { this.body = body; };
    const context = {
        window: { addEventListener() {} }, navigator: {}, XMLHttpRequest: XHR,
        document: { getElementById(id) { return elements[id]; }, querySelector() { return { getAttribute() { return 'csrf-test'; } }; } },
        setInterval(fn) { interval = fn; }, setTimeout() {},
    };
    return { context, elements, video, videoEvents, requests, tick() { interval(); } };
}

test('old play() return value works; resume and progress use CSRF-protected requests', () => {
    const p = player();
    vm.runInNewContext(asset('player.js'), p.context);
    p.elements.play.onclick();
    assert.equal(p.video.src, '/media');
    assert.equal(p.video.paused, false);
    p.videoEvents.loadedmetadata();
    assert.equal(p.video.currentTime, 45);
    p.video.currentTime = 72;
    p.tick();
    const request = p.requests[0];
    assert.equal(request.method, 'POST');
    assert.equal(request.url, '/videos/progress');
    const body = new URLSearchParams(request.body);
    assert.equal(body.get('_token'), 'csrf-test');
    assert.equal(body.get('path'), 'movies/日本語 #1.mp4');
    assert.equal(body.get('time'), '72');
    request.status = 419;
    request.onload();
    assert.match(p.elements['progress-status'].textContent, /ログイン/);
    p.tick();
    assert.equal(p.requests.length, 2, 'failed saves must remain retryable');
});

test('hls.js is selected over native HLS even when native support returns maybe', () => {
    const p = player('hls');
    let attached;
    function Hls(config) { assert.equal(config.startPosition, 45); }
    Hls.isSupported = () => true;
    Hls.Events = { MANIFEST_PARSED: 'manifest', AUDIO_TRACKS_UPDATED: 'audio', ERROR: 'error' };
    Hls.prototype.on = function () {};
    Hls.prototype.loadSource = function (source) { assert.equal(source, '/media'); };
    Hls.prototype.attachMedia = function (video) { attached = video; };
    p.context.Hls = p.context.window.Hls = Hls;
    vm.runInNewContext(asset('player.js'), p.context);
    p.elements.play.onclick();
    p.requests[0].status = 200;
    p.requests[0].responseText = '#EXTM3U\n';
    p.requests[0].onload();
    assert.equal(attached, p.video);
    assert.equal(p.video.src, undefined, 'native HLS must not be selected');
});

test('cold HLS cache HTML 404 keeps waiting rather than reporting an authentication error', () => {
    const p = player('hls');
    let retry;
    p.context.setTimeout = fn => { retry = fn; };
    vm.runInNewContext(asset('player.js'), p.context);
    p.elements.play.onclick();
    p.requests[0].status = 404;
    p.requests[0].responseText = '<!DOCTYPE html><html>Not Found</html>';
    p.requests[0].onload();
    assert.match(p.elements['playback-status'].textContent, /変換しています/);
    retry();
    assert.equal(p.requests.length, 2);
    p.requests[1].status = 200;
    p.requests[1].responseText = '<!doctype html><html>Login</html>';
    p.requests[1].onload();
    assert.match(p.elements['playback-status'].textContent, /ログイン/);
});
