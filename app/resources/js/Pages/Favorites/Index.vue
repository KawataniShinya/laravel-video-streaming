<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FavoriteToggle from '@/Components/FavoriteToggle.vue';
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
    <Head title="Favorites" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Favorites
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="items.length > 0">
                            <ul class="divide-y divide-gray-200">
                                <li v-for="item in items" :key="item.path" class="hover:bg-gray-50 transition duration-150 ease-in-out">
                                    <div class="flex items-center justify-between w-full">
                                        <Link 
                                            :href="item.type === 'folder' 
                                                ? route('videos.index', { path: item.path.replace(/#/g, '%23') }) 
                                                : route('videos.watch', { path: item.path.replace(/#/g, '%23') })" 
                                            class="flex items-center group flex-grow px-6 py-4"
                                        >
                                            <div class="flex items-center flex-grow">
                                                <template v-if="item.type === 'folder'">
                                                    <svg class="w-8 h-8 text-yellow-500 mr-4 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                                </template>
                                                <template v-else>
                                                    <svg class="w-8 h-8 text-blue-500 mr-4 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </template>
                                                
                                                <div class="flex flex-col">
                                                    <span class="text-gray-900 font-bold group-hover:text-blue-600 transition-colors">{{ item.name }}</span>
                                                    <span class="text-xs text-gray-400 font-mono mt-1">{{ item.path }}</span>
                                                    
                                                    <div v-if="item.type === 'file'" class="flex items-center mt-2 gap-3">
                                                        <span v-if="item.is_cached" class="text-[10px] font-bold text-green-700 bg-green-100 px-1.5 py-0.5 rounded uppercase tracking-wider">
                                                            Cached
                                                        </span>
                                                        <span v-if="item.is_watched" class="text-[10px] font-bold text-purple-700 bg-purple-100 px-1.5 py-0.5 rounded uppercase tracking-wider">
                                                            Watched
                                                        </span>
                                                        <span v-if="item.last_position > 0" class="text-xs font-semibold text-blue-600">
                                                            Resume at {{ formatTime(item.last_position) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center ml-4">
                                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity">
                                                    {{ item.type === 'folder' ? 'Open' : (item.last_position > 0 ? 'Resume' : 'Watch') }}
                                                </span>
                                                <svg class="w-5 h-5 text-gray-400 ml-2 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            </div>
                                        </Link>
                                        <div class="mr-6">
                                            <FavoriteToggle :path="item.path" :type="item.type" :is-favorited="true" />
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No favorites</h3>
                            <p class="mt-1 text-sm text-gray-500 italic">You haven't added any videos or folders to your favorites yet.</p>
                            <div class="mt-6">
                                <Link :href="route('videos.index')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Browse Videos
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>