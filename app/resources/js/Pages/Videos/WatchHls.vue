<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    }
});

const video = ref(null);
let hls = null;

const backLink = computed(() => {
    if (!props.path) return route('videos.index');
    const parts = props.path.split('/');
    parts.pop(); // Remove filename
    const dir = parts.join('/');
    return dir ? route('videos.index', { path: dir }) : route('videos.index');
});

onMounted(() => {
    // route('videos.hls', { hash: ..., file: ... })
    const videoSrc = route('videos.hls', { hash: props.hash, file: 'index.m3u8' });

    if (Hls.isSupported()) {
        hls = new Hls();
        hls.loadSource(videoSrc);
        hls.attachMedia(video.value);
        hls.on(Hls.Events.MANIFEST_PARSED, function() {
            video.value.play();
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
    else if (video.value.canPlayType('application/vnd.apple.mpegurl')) {
        video.value.src = videoSrc;
        video.value.addEventListener('loadedmetadata', function() {
            video.value.play();
        });
    }
});

onBeforeUnmount(() => {
    if (hls) {
        hls.destroy();
    }
});
</script>

<template>
    <Head :title="`Watching ${filename}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Watching {{ filename }}
            </h2>
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
