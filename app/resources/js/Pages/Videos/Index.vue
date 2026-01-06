<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    files: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head title="Video Library" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Video Library
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-bold mb-4">Available Videos</h3>
                        <div v-if="files.length > 0">
                            <ul class="divide-y divide-gray-200">
                                <li v-for="file in files" :key="file" class="py-4 flex justify-between items-center">
                                    <span class="text-lg">{{ file }}</span>
                                    <Link :href="route('videos.watch', { filename: file })" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        Watch
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div v-else>
                            <p class="text-gray-500">No videos found.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
