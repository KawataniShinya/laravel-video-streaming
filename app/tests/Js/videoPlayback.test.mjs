import test from 'node:test';
import assert from 'node:assert/strict';
import { resumePosition } from '../../resources/js/videoPlayback.js';
test('manual resume keeps a position within the video', () => {
    assert.equal(resumePosition(32, 90), 32);
});
test('the last saved whole second and positions past the end restart at zero', () => {
    assert.equal(resumePosition(89, 89.8), 0);
    assert.equal(resumePosition(90, 90), 0);
    assert.equal(resumePosition(91, 90), 0);
});
test('new and explicit next playback start at zero', () => {
    assert.equal(resumePosition(0, 90), 0);
    assert.equal(resumePosition(-1, 90), 0);
    assert.equal(resumePosition(NaN, 90), 0);
});
