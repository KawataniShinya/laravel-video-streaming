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
    }
});

const video = ref(null);
const audioTracks = ref([]);
const currentAudioTrack = ref(-1);
const levels = ref([]);
const currentLevel = ref(-1); // -1 is Auto
let hls = null;
let updateInterval = null;

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
        hls = new Hls();
        hls.loadSource(videoSrc);
        hls.attachMedia(v);
        hls.on(Hls.Events.MANIFEST_PARSED, function() {
            if (props.lastPosition > 0) {
                v.currentTime = props.lastPosition;
            }
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
                    console.log("fatal network error encountered, try to recover");
                    hls.startLoad();
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
            if (props.lastPosition > 0) {
                v.currentTime = props.lastPosition;
            }
            playVideo();
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

                        <video ref="video" controls autoplay class="w-full shadow-lg rounded"></video>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
