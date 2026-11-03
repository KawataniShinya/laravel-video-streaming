<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import Hls from 'hls.js';
import { resumePosition } from '@/videoPlayback.js';
const props = defineProps({ path: { type: String, required: true }, hash: { type: String, default: null }, lastPosition: { type: Number, default: 0 }, isCached: Boolean });
const emit = defineEmits(['progress-saved']);
let saveSequence = 0;
let disposed = false;
const needsPlay = ref(false);
const progressError = ref(false);
let hasPlayed = false;
let positionRestored = false;
const restorePosition = () => {
    if (positionRestored) return;
    positionRestored = true;
    const v = video.value;
    v.currentTime = resumePosition(props.lastPosition, v.duration);
};
const video = ref(null);
const audioTracks = ref([]);
const currentAudioTrack = ref(-1);
const levels = ref([]);
const currentLevel = ref(-1); // -1 is Auto
const isWaiting = ref(false);
const countdown = ref(10);
let hls = null;
let updateInterval = null;
let countdownInterval = null;

const startCountdown = () => {
    if (isWaiting.value) return;
    isWaiting.value = true;
    countdown.value = 10;

    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(countdownInterval);
            window.location.reload();
        }
    }, 1000);
};

const setAudioTrack = (index) => {
    if (hls) {
        hls.audioTrack = index;
        currentAudioTrack.value = index;
    }
};

const setLevel = (index) => {
    if (hls) {
        hls.currentLevel = index;
        currentLevel.value = index;
    }
};

const playVideo = () => {
    const v = video.value;
    if (!v) return;

    needsPlay.value = false;
    v.play().catch(() => {
        needsPlay.value = true;
    });
};

const saveProgress = (time) => {
    // Only persist zero after playback actually started.
    if (!Number.isFinite(time) || time < 0 || (time === 0 && !hasPlayed)) return;

    const sequence = ++saveSequence;
    axios.post(route('videos.progress'), {
        path: props.path,
        time: Math.floor(time),
        duration: !props.hash && Number.isFinite(video.value?.duration) && video.value.duration > 0 ? video.value.duration : null
    }).then(({ data }) => {
        if (disposed || sequence !== saveSequence) return;
        progressError.value = false;
        emit('progress-saved', { path: props.path, percent: data.progress_percent });
    }).catch(() => {
        if (!disposed && sequence === saveSequence) progressError.value = true;
    });
};

onMounted(() => {
    // route('videos.hls', { hash: ..., file: ... })
    const videoSrc = props.hash ? route('videos.hls', { hash: props.hash, file: 'index.m3u8' }) : route('videos.stream', { path: props.path });
    const v = video.value;

    v.addEventListener('loadedmetadata', restorePosition);
    v.addEventListener('playing', () => { hasPlayed = true; needsPlay.value = false; });
    v.addEventListener('ended', () => saveProgress(v.currentTime));
    v.addEventListener('seeked', () => { if (hasPlayed) saveProgress(v.currentTime); });
    if (!props.hash) {
        v.src = videoSrc;
        v.addEventListener('loadedmetadata', playVideo);
    } else if (Hls.isSupported()) {
        hls = new Hls({
            startPosition: -1,
        });
        hls.loadSource(videoSrc);
        hls.attachMedia(v);
        hls.on(Hls.Events.MANIFEST_PARSED, function() {
            levels.value = hls.levels;
            // hls.loadLevel defaults to -1 (Auto)
            currentLevel.value = hls.loadLevel;
            playVideo();
        });

        hls.on(Hls.Events.AUDIO_TRACKS_UPDATED, function (event, data) {
            audioTracks.value = data.audioTracks;
            currentAudioTrack.value = hls.audioTrack;
        });

        hls.on(Hls.Events.AUDIO_TRACK_SWITCHED, function (event, data) {
            currentAudioTrack.value = data.id;
        });

        hls.on(Hls.Events.ERROR, function (event, data) {
            if (data.fatal) {
                switch (data.type) {
                case Hls.ErrorTypes.NETWORK_ERROR:
                    startCountdown();
                    break;
                case Hls.ErrorTypes.MEDIA_ERROR:
                    hls.recoverMediaError();
                    break;
                default:
                    hls.destroy();
                    break;
                }
            }
        });
    }
    else if (v.canPlayType('application/vnd.apple.mpegurl')) {
        v.src = videoSrc;
        v.addEventListener('loadedmetadata', function() {
            playVideo();
        });
        // Native HLS error handling (e.g. Safari on mobile)
        v.addEventListener('error', function() {
            console.log("Native video error, starting countdown");
            startCountdown();
        });
    }

    // Save every 10 seconds
    updateInterval = setInterval(() => {
        if (v && !v.paused) {
            saveProgress(v.currentTime);
        }
    }, 10000);

    if (v) {
        v.addEventListener('pause', () => saveProgress(v.currentTime));
    }
});

onBeforeUnmount(() => {
    disposed = true;
    if (updateInterval) clearInterval(updateInterval);
    if (countdownInterval) clearInterval(countdownInterval);
    const v = video.value;
    if (v && v.currentTime > 0) {
        saveProgress(v.currentTime);
    }
    if (hls) {
        hls.destroy();
    }
});
</script>
<template>
    <div>
        <!-- Audio Track Selection -->
        <div v-if="audioTracks.length > 1" class="mb-4 p-3 bg-gray-100 rounded-lg flex flex-wrap items-center gap-3">
            <span class="text-sm font-bold text-gray-700">Audio:</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="(track, index) in audioTracks"
                    :key="index"
                    @click="setAudioTrack(index)"
                    :class="[
                        'px-3 py-1 text-xs rounded font-medium transition',
                        currentAudioTrack === index
                            ? 'bg-blue-600 text-white shadow'
                            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    {{ track.lang && track.lang !== 'und' ? `${track.lang} / ` : '' }}Track {{ index + 1 }}
                </button>
            </div>
        </div>

        <!-- Quality Selection -->
        <div v-if="levels.length > 1" class="mb-4 p-3 bg-gray-50 rounded-lg flex items-center gap-3 border border-gray-100">
            <span class="text-sm font-bold text-gray-700">Quality:</span>
            <div class="flex gap-2">
                <button
                    @click="setLevel(-1)"
                    :class="[
                        'px-3 py-1 text-xs rounded font-medium transition',
                        currentLevel === -1
                            ? 'bg-green-600 text-white shadow'
                            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Auto
                </button>
                <button
                    v-for="(level, index) in levels"
                    :key="index"
                    @click="setLevel(index)"
                    :class="[
                        'px-3 py-1 text-xs rounded font-medium transition',
                        currentLevel === index
                            ? 'bg-blue-600 text-white shadow'
                            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    {{ level.name || `${level.height}p` }}
                </button>
            </div>
        </div>

        <p v-if="hash && !isCached" class="text-sm text-gray-500 mb-2">Transcoding and streaming via HLS...</p>
        <p v-else-if="hash" class="text-sm text-green-600 mb-2 flex items-center gap-1">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
            Playing from server cache
        </p>

        <div class="relative w-full aspect-video bg-black rounded shadow-lg overflow-hidden mb-4">
            <!-- Waiting Overlay -->
            <div v-if="isWaiting" class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black bg-opacity-80 text-white p-6 text-center">
                <svg class="animate-spin h-10 w-10 mb-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <h3 class="text-lg font-bold mb-2">Preparing video...</h3>
                <p class="text-sm text-gray-300 mb-4">Preparing this video for its first playback. Please wait.</p>
                <div class="text-3xl font-mono font-bold text-blue-400">
                    Reloading in {{ countdown }}...
                </div>
            </div>

            <video ref="video" controls autoplay class="w-full h-full"></video>
        </div>
        <button v-if="needsPlay" @click="playVideo" class="bg-blue-700 text-white px-4 py-2 rounded">Start Playback</button>
        <p v-if="progressError" role="status" class="text-amber-800 text-sm">Could not save playback progress. Check your connection.</p>
    </div>
</template>
