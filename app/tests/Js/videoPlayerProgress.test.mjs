import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { resumePosition } from '../../resources/js/videoPlayback.js';

// Exercise the player's event handlers and asynchronous saves without a browser.
const source = readFileSync(new URL('../../resources/js/Components/VideoPlayer.vue', import.meta.url), 'utf8')
    .match(/<script setup>([\s\S]*?)<\/script>/)[1].replace(/^import .*;$/gm, '');
function player() {
    const events = {}, requests = [], emitted = [];
    let mount, unmount, tick;
    const video = { currentTime: 50, duration: 200, paused: false,
        addEventListener: (name, handler) => { events[name] = handler; },
        play: () => Promise.resolve(),
    };
    const context = vm.createContext({
        defineProps: () => ({ path: 'movie.mp4', hash: null, lastPosition: 0 }),
        defineEmits: () => (name, payload) => emitted.push({ name, ...payload }),
        ref: value => ({ value }), resumePosition,
        onMounted: fn => { mount = fn; }, onBeforeUnmount: fn => { unmount = fn; },
        setInterval: fn => { tick = fn; return 1; }, clearInterval() {},
        route: name => name,
        axios: { post: (url, payload) => new Promise((resolve, reject) => requests.push({ url, payload, resolve, reject })) },
    });
    vm.runInContext(source + '\nglobalThis.player = { video, progressError };', context);
    context.player.video.value = video;
    mount();
    events.playing();
    return { events, requests, emitted, video, tick, unmount, error: context.player.progressError };
}
const flush = async () => { await Promise.resolve(); await Promise.resolve(); };

test('periodic save updates the playlist only after success, without reloading', async () => {
    const p = player();
    p.tick();
    assert.equal(p.requests[0].payload.time, 50);
    assert.equal(p.emitted.length, 0);
    p.requests[0].resolve({ data: { progress_percent: 25 } });
    await flush();
    assert.deepEqual(p.emitted, [{ name: 'progress-saved', path: 'movie.mp4', percent: 25 }]);
});

test('pause, rewind and end propagate server percentages including zero and completion', async () => {
    const p = player();
    for (const [event, time, percent] of [['pause', 50, 25], ['seeked', 0, 0], ['ended', 200, 100]]) {
        p.video.currentTime = time;
        p.events[event]();
        p.requests.at(-1).resolve({ data: { progress_percent: percent } });
        await flush();
        assert.equal(p.emitted.at(-1).percent, percent);
    }
});

test('late responses cannot overwrite a newer end position', async () => {
    const p = player();
    p.tick();
    p.video.currentTime = 200;
    p.events.ended();
    p.requests[1].resolve({ data: { progress_percent: 100 } });
    await flush();
    p.requests[0].resolve({ data: { progress_percent: 25 } });
    await flush();
    assert.equal(p.emitted.length, 1);
    assert.equal(p.emitted[0].percent, 100);
});

test('failed saves retain the displayed value and successful retry clears the error', async () => {
    const p = player();
    p.tick();
    p.requests[0].reject(new Error('offline'));
    await flush();
    assert.equal(p.error.value, true);
    assert.equal(p.emitted.length, 0);
    p.tick();
    p.requests[1].resolve({ data: { progress_percent: null } });
    await flush();
    assert.equal(p.error.value, false);
    assert.equal(p.emitted[0].percent, null);
});

test('leaving a video prevents its pending saves from updating another video', async () => {
    const p = player();
    p.tick();
    p.unmount();
    for (const request of p.requests) request.resolve({ data: { progress_percent: 25 } });
    await flush();
    assert.equal(p.emitted.length, 0);
});
