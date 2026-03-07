<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const formatTime = (seconds) => {
    if (!seconds) return '0:00';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    
    if (h > 0) {
        return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }
    return `${m}:${s.toString().padStart(2, '0')}`;
};
</script>

<template>
    <Head title="Viewing History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Viewing History
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="items.length > 0">
                            <ul class="divide-y divide-gray-200">
                                <li v-for="item in items" :key="item.id" class="hover:bg-gray-50 transition duration-150 ease-in-out">
                                    <Link :href="route('videos.watch', { path: item.path.replace(/#/g, '%23') })" class="flex items-center justify-between w-full px-6 py-4 group">
                                        <div class="flex items-center flex-grow">
                                            <svg class="w-8 h-8 text-gray-400 mr-4 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div class="flex flex-col">
                                                <span class="text-gray-900 font-bold group-hover:text-blue-600 transition-colors">{{ item.name }}</span>
                                                <span class="text-xs text-gray-400 font-mono mt-1">{{ item.path }}</span>
                                                <div class="flex items-center mt-2 gap-4">
                                                    <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">
                                                        Position: {{ formatTime(item.last_position) }}
                                                    </span>
                                                    <span class="text-xs text-gray-500">
                                                        Last viewed: {{ item.updated_at_human }} ({{ item.updated_at }})
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center ml-4">
                                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity">Resume</span>
                                            <svg class="w-5 h-5 text-gray-400 ml-2 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                        </div>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div v-else class="text-center py-8">
                            <p class="text-gray-500 italic">No viewing history found. Start watching some videos!</p>
                            <Link :href="route('videos.index')" class="mt-4 inline-block text-blue-600 hover:underline">Go to Video Library</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
