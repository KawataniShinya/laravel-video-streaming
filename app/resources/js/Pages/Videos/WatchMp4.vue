<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    filename: {
        type: String,
        required: true,
    },
    path: {
        type: String,
        required: true,
    }
});

const backLink = computed(() => {
    if (!props.path) return route('videos.index');
    const parts = props.path.split('/');
    parts.pop(); // Remove filename
    const dir = parts.join('/');
    return dir ? route('videos.index', { path: dir }) : route('videos.index');
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
                        
                        <video controls autoplay class="w-full shadow-lg rounded">
                            <source :src="route('videos.stream', { path: path })" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
