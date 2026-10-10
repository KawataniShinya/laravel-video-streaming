(function () {
    var video = document.getElementById('legacy-video');
    if (!video) { return; }
    var status = document.getElementById('playback-status');
    var progressStatus = document.getElementById('progress-status');
    var path = video.getAttribute('data-path');
    var source = video.getAttribute('data-source');
    var position = Number(video.getAttribute('data-position')) || 0;
    var isHls = video.getAttribute('data-kind') === 'hls';
    var initialized = false;
    var preparing = false;
    var stopped = false;
    var hls = null;
    var restored = false;
    var lastSaved = -1;
    var saveRequest = null;
    var quality = document.getElementById('quality');
    var audio = document.getElementById('audio');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    function message(text) { status.textContent = text; }
    function play() {
        try {
            var result = video.play();
            if (result && typeof result.catch === 'function') {
                result.catch(function () { message('再生ボタンをもう一度押してください。'); });
            }
        } catch (e) { message('再生できません: ' + e.message); }
    }
    function saveProgress(unloading) {
        var time = Math.floor(video.currentTime || 0);
        if (time <= 0 || time === lastSaved || (saveRequest && !unloading)) { return; }
        if (unloading && navigator.sendBeacon && window.FormData) {
            var data = new FormData();
            data.append('_token', token);
            data.append('path', path);
            data.append('time', time);
            if (navigator.sendBeacon('/videos/progress', data)) { return; }
        }
        var xhr = new XMLHttpRequest();
        saveRequest = xhr;
        xhr.open('POST', '/videos/progress', true);
        xhr.timeout = 10000;
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status === 200) { lastSaved = time; progressStatus.textContent = '再生位置を保存しました。'; }
            else if (xhr.status === 401 || xhr.status === 419) { progressStatus.textContent = '再生位置を保存できません。ログインし直してください。'; }
            else { progressStatus.textContent = '再生位置の保存に失敗しました。'; }
            saveRequest = null;
        };
        xhr.onerror = xhr.ontimeout = function () { saveRequest = null; progressStatus.textContent = '再生位置を保存できません。通信状態を確認してください。'; };
        xhr.send('_token=' + encodeURIComponent(token) + '&path=' + encodeURIComponent(path) + '&time=' + time);
    }
    function setOptions(select, values, label) {
        while (select.options.length > 1) { select.remove(1); }
        for (var i = 0; i < values.length; i++) {
            var option = document.createElement('option');
            option.value = i;
            option.textContent = label(values[i], i);
            select.appendChild(option);
        }
    }
    function initialize() {
        preparing = false;
        if (!isHls) {
            video.src = source;
            initialized = true;
            play();
            return;
        }
        // Prefer the MSE path verified on the tuner, even if native HLS says "maybe".
        if (window.Hls && Hls.isSupported()) {
            hls = new Hls({ startPosition: position, startLevel: 1 });
            hls.on(Hls.Events.MANIFEST_PARSED, function () {
                setOptions(quality, hls.levels, function (level, i) { return level.height ? level.height + 'p' : '画質 ' + (i + 1); });
                message('再生準備ができました。');
                play();
            });
            hls.on(Hls.Events.AUDIO_TRACKS_UPDATED, function () {
                setOptions(audio, hls.audioTracks, function (track, i) { return track.name || track.lang || '音声 ' + (i + 1); });
            });
            hls.on(Hls.Events.ERROR, function (event, data) {
                if (data.fatal) {
                    position = Math.floor(video.currentTime || position);
                    message('再生エラー: ' + data.details + '。再生ボタンで再試行できます。');
                    hls.destroy();
                    hls = null;
                    initialized = false;
                }
            });
            hls.loadSource(source);
            hls.attachMedia(video);
            initialized = true;
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = source;
            initialized = true;
            play();
        } else { message('このブラウザではHLS動画を再生できません。'); }
    }
    function waitForPlaylist(attempt) {
        if (stopped) { return; }
        var xhr = new XMLHttpRequest();
        function retry() {
            if (attempt >= 90) { preparing = false; message('動画を準備できませんでした。再生ボタンで再試行してください。'); return; }
            message('動画を変換しています。準備ができると再生します（待機 ' + ((attempt + 1) * 2) + '秒）。');
            setTimeout(function () { waitForPlaylist(attempt + 1); }, 2000);
        }
        xhr.open('GET', source + '?t=' + new Date().getTime(), true);
        xhr.timeout = 10000;
        xhr.onload = function () {
            if (xhr.status === 200 && xhr.responseText.indexOf('#EXTM3U') === 0) { initialize(); }
            else if (xhr.status === 404) { retry(); }
            else if (xhr.status === 401 || xhr.status === 403 || (xhr.status === 200 && /<!doctype html>/i.test(xhr.responseText))) {
                preparing = false;
                message('動画を取得できません。ログイン状態とアクセス権限を確認してください。');
            } else { retry(); }
        };
        xhr.onerror = xhr.ontimeout = retry;
        xhr.send(null);
    }
    document.getElementById('play').onclick = function () {
        if (initialized) { play(); return; }
        if (preparing) { return; }
        preparing = true;
        message('動画の準備を確認しています。');
        if (isHls) { waitForPlaylist(0); } else { initialize(); }
    };
    document.getElementById('pause').onclick = function () { video.pause(); };
    function seek(delta) {
        try {
            var time = Math.max(0, video.currentTime + delta);
            if (isFinite(video.duration)) { time = Math.min(time, video.duration); }
            video.currentTime = time;
        } catch (e) { message('まだシークできません。動画の読み込みを待ってください。'); }
    }
    document.getElementById('rewind').onclick = function () { seek(-30); };
    document.getElementById('forward').onclick = function () { seek(30); };
    document.getElementById('fullscreen').onclick = function () {
        var enter = video.requestFullscreen || video.webkitRequestFullscreen || video.webkitEnterFullscreen || video.mozRequestFullScreen;
        if (enter) {
            try {
                var result = enter.call(video);
                if (result && result.catch) { result.catch(function () { message('全画面に切り替えられません。'); }); }
            } catch (e) { message('全画面に切り替えられません。'); }
        } else { message('ブラウザの動画コントロールから全画面を選んでください。'); }
    };
    if (quality) { quality.onchange = function () { if (hls) { hls.currentLevel = Number(quality.value); } }; }
    if (audio) { audio.onchange = function () { if (hls) { hls.audioTrack = Math.max(0, Number(audio.value)); } }; }
    video.addEventListener('loadedmetadata', function () {
        if (!restored && !hls && position > 0) {
            try { video.currentTime = position; } catch (e) { message('前回位置に移動できませんでした。'); }
        }
        restored = true;
    });
    video.addEventListener('playing', function () { message('再生中'); });
    video.addEventListener('waiting', function () { message('動画を読み込んでいます。'); });
    video.addEventListener('error', function () { message('動画を再生できません。エラー番号: ' + (video.error ? video.error.code : '不明')); });
    video.addEventListener('pause', function () { saveProgress(false); if (!video.ended) { message('一時停止中'); } });
    video.addEventListener('ended', function () { saveProgress(false); message('再生が終了しました。'); });
    setInterval(function () { if (!video.paused) { saveProgress(false); } }, 10000);
    window.addEventListener('pagehide', function () {
        stopped = true;
        saveProgress(true);
        position = Math.floor(video.currentTime || position);
        if (hls) { hls.destroy(); hls = null; initialized = false; }
    });
    window.addEventListener('pageshow', function () { stopped = false; preparing = false; });
}());
