<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FavoriteToggle from '@/Components/FavoriteToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import Hls from 'hls.js';

const props = defineProps({
    filename: {
        type: String,
        required: true,
    },
    path: {
        type: String,
        required: true,
    },
    hash: {
        type: String,
        required: true,
    },
    lastPosition: {
        type: Number,
        default: 0,
    },
    isFavorited: {
        type: Boolean,
        default: false,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    }
});

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

    v.play().catch(error => {
        console.warn("Autoplay was prevented by browser:", error);
    });
};

const backLink = computed(() => {
    if (!props.path) return route('videos.index');
    const parts = props.path.split('/');
    parts.pop(); // Remove filename
    const dir = parts.join('/');
    return dir ? route('videos.index', { path: dir }) : route('videos.index');
});

const saveProgress = (time) => {
    // Avoid saving if time is 0 or invalid to prevent accidental resets
    // during component unmount or HLS initialization.
    if (!time || time <= 0) return;

    axios.post(route('videos.progress'), {
        path: props.path,
        time: Math.floor(time)
    });
};

onMounted(() => {
    // route('videos.hls', { hash: ..., file: ... })
    const videoSrc = route('videos.hls', { hash: props.hash, file: 'index.m3u8' });
    const v = video.value;

    if (Hls.isSupported()) {
        hls = new Hls({
            startPosition: props.lastPosition || 0,
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

        hls.on(Hls.Events.ERROR, function (event, data) {
            if (data.fatal) {
                switch (data.type) {
                case Hls.ErrorTypes.NETWORK_ERROR:
                    console.log("fatal network error encountered, starting countdown for reload");
                    startCountdown();
                    break;
                case Hls.ErrorTypes.MEDIA_ERROR:
                    console.log("fatal media error encountered, try to recover");
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
    <Head :title="`Watching ${filename}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Watching {{ filename }}
                </h2>
                <FavoriteToggle :path="path" type="file" :is-favorited="isFavorited" />
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Breadcrumbs -->
                <nav class="flex mb-4 text-gray-600 bg-white p-3 rounded shadow-sm" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <Link :href="route('videos.index')" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <svg aria-hidden="true" class="w-4 h-4 mr-2 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                                Home
                            </Link>
                        </li>
                        <li v-for="crumb in breadcrumbs" :key="crumb.path">
                            <div class="flex items-center">
                                <svg aria-hidden="true" class="w-6 h-6 text-gray-400 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                <Link :href="route('videos.index', { path: crumb.path })" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">{{ crumb.name }}</Link>
                            </div>
                        </li>
                    </ol>
                </nav>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="mb-4">
                            <Link :href="backLink" class="text-blue-600 hover:text-blue-800">&larr; Back to Folder</Link>
                        </div>

                        <!-- Audio Track Selection -->
                        <div v-if="audioTracks.length > 1" class="mb-4 p-3 bg-gray-100 rounded-lg flex items-center gap-3">
                            <span class="text-sm font-bold text-gray-700">Audio:</span>
                            <div class="flex gap-2">
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
                                    {{ track.name || `Track ${index + 1}` }}
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

                        <p class="text-sm text-gray-500 mb-2">Transcoding and streaming via HLS...</p>

                        <div class="relative w-full aspect-video bg-black rounded shadow-lg overflow-hidden mb-4">
                            <!-- Waiting Overlay -->
                            <div v-if="isWaiting" class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black bg-opacity-80 text-white p-6 text-center">
                                <svg class="animate-spin h-10 w-10 mb-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <h3 class="text-lg font-bold mb-2">Preparing video...</h3>
                                <p class="text-sm text-gray-300 mb-4">The video is being transcoded for the first time. Please wait a moment.</p>
                                <div class="text-3xl font-mono font-bold text-blue-400">
                                    Reloading in {{ countdown }}...
                                </div>
                            </div>

                            <video ref="video" controls autoplay class="w-full h-full"></video>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
