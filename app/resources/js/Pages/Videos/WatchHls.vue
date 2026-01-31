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
let hls = null;
let updateInterval = null;

const backLink = computed(() => {
    if (!props.path) return route('videos.index');
    const parts = props.path.split('/');
    parts.pop(); // Remove filename
    const dir = parts.join('/');
    return dir ? route('videos.index', { path: dir }) : route('videos.index');
});

const saveProgress = (time) => {
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
            v.play();
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
            v.play();
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
    if (hls) {
        hls.destroy();
    }
    if (updateInterval) clearInterval(updateInterval);
    const v = video.value;
    if (v) {
        saveProgress(v.currentTime);
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
                        <p class="text-sm text-gray-500 mb-2">Transcoding and streaming via HLS...</p>
                        
                        <video ref="video" controls autoplay class="w-full shadow-lg rounded"></video>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
