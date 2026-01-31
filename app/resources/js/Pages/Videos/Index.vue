<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FavoriteToggle from '@/Components/FavoriteToggle.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    currentPath: {
        type: String,
        default: null,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    }
});

const page = usePage();
const userRole = page.props.auth.user.role;

const form = useForm({
    path: null,
});

const deleteCache = (path) => {
    if (confirm('Are you sure you want to delete the cache for this video?')) {
        form.path = path;
        form.post(route('videos.cache.delete'), {
            preserveScroll: true,
        });
    }
};

const toggleWatched = (path) => {
    form.path = path;
    form.post(route('videos.watched.toggle'), {
        preserveScroll: true,
    });
};

const getParentPath = (path) => {
    if (!path) return null;
    const parts = path.split('/');
    parts.pop();
    return parts.length > 0 ? parts.join('/') : null;
};
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
                        <h3 class="text-lg font-bold mb-4">Contents</h3>
                        
                        <div v-if="items.length > 0 || currentPath">
                            <ul class="divide-y divide-gray-200">
                                <!-- Parent Directory Link -->
                                <li v-if="currentPath" class="hover:bg-gray-50 transition duration-150 ease-in-out">
                                    <Link :href="route('videos.index', { path: getParentPath(currentPath) })" class="block px-6 py-4 flex items-center">
                                        <span class="text-blue-500 mr-3">
                                            <svg class="w-6 h-6 fill-none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>
                                        </span>
                                        <span class="text-gray-900 font-medium">.. (Parent Directory)</span>
                                    </Link>
                                </li>

                                <li v-for="item in items" :key="item.path" class="hover:bg-gray-50 transition duration-150 ease-in-out">
                                    <template v-if="item.type === 'folder'">
                                        <div class="flex items-center justify-between w-full">
                                            <Link :href="route('videos.index', { path: item.path })" class="flex items-center flex-grow px-6 py-4">
                                                <div class="flex items-center">
                                                    <svg class="w-6 h-6 text-yellow-500 mr-3 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                                    <span class="text-gray-900 font-medium">{{ item.name }}</span>
                                                </div>
                                                <svg class="w-5 h-5 text-gray-400 ml-auto fill-none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            </Link>
                                            <div class="mr-4">
                                                <FavoriteToggle :path="item.path" :type="item.type" :is-favorited="item.is_favorited" />
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-center justify-between w-full">
                                            <Link :href="route('videos.watch', { path: item.path })" class="flex items-center group flex-grow px-6 py-4">
                                                <div class="flex items-center">
                                                    <svg class="w-6 h-6 text-blue-500 mr-3 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    <span class="text-gray-900">{{ item.name }}</span>
                                                </div>
                                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity ml-2">Watch</span>
                                            </Link>
                                            
                                            <div class="flex items-center mr-4 gap-2">
                                                <FavoriteToggle :path="item.path" :type="item.type" :is-favorited="item.is_favorited" />

                                                <div v-if="item.is_cached" class="mr-2">
                                                    <button 
                                                        v-if="userRole === 'admin'" 
                                                        @click="deleteCache(item.path)"
                                                        class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                                    >
                                                        Delete Cache
                                                    </button>
                                                    <span 
                                                        v-else 
                                                        class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded"
                                                    >
                                                        Cached
                                                    </span>
                                                </div>

                                                <button 
                                                    @click="toggleWatched(item.path)"
                                                    :class="[
                                                        'font-bold py-1 px-2 rounded text-xs transition-colors duration-200',
                                                        item.is_watched 
                                                            ? 'bg-blue-500 hover:bg-blue-700 text-white' 
                                                            : 'bg-gray-200 hover:bg-gray-300 text-gray-600'
                                                    ]"
                                                >
                                                    {{ item.is_watched ? 'Watched' : 'Mark Watched' }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </li>
                            </ul>
                        </div>
                        <div v-else>
                            <p class="text-gray-500">No videos or folders found.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
