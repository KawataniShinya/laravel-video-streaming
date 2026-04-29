<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FavoriteToggle from '@/Components/FavoriteToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    filename: {
        type: String,
        required: true,
    },
    path: {
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
    isCached: {
        type: Boolean,
        default: false,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    }
});

const video = ref(null);
let updateInterval = null;

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
    if (!time || time <= 0) return;

    axios.post(route('videos.progress'), {
        path: props.path,
        time: Math.floor(time)
    });
};

onMounted(() => {
    const v = video.value;
    if (v && props.lastPosition > 0) {
        v.currentTime = props.lastPosition;
    }
    playVideo();

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
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Watching {{ filename }}
                    </h2>
                    <span v-if="isCached" class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold uppercase tracking-wider rounded border border-green-200">
                        Cached
                    </span>
                </div>
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

                        <video ref="video" controls autoplay class="w-full shadow-lg rounded">
                            <source :src="route('videos.stream', { path: path })" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
