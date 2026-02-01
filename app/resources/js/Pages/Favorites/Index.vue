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
                                    <template v-if="item.type === 'folder'">
                                        <div class="flex items-center justify-between w-full">
                                            <Link :href="route('videos.index', { path: item.path.replace(/#/g, '%23') })" class="flex items-center flex-grow px-6 py-4">
                                                <div class="flex items-center">
                                                    <svg class="w-6 h-6 text-yellow-500 mr-3 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                                    <span class="text-gray-900 font-medium">{{ item.name }}</span>
                                                </div>
                                                <svg class="w-5 h-5 text-gray-400 ml-auto fill-none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            </Link>
                                            <div class="mr-4">
                                                <FavoriteToggle :path="item.path" :type="item.type" :is-favorited="true" />
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-center justify-between w-full">
                                            <Link :href="route('videos.watch', { path: item.path.replace(/#/g, '%23') })" class="flex items-center group flex-grow px-6 py-4">
                                                <div class="flex items-center">
                                                    <svg class="w-6 h-6 text-blue-500 mr-3 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    <span class="text-gray-900">{{ item.name }}</span>
                                                </div>
                                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity ml-2">Watch</span>
                                            </Link>
                                            
                                            <div class="flex items-center mr-4 gap-2">
                                                <FavoriteToggle :path="item.path" :type="item.type" :is-favorited="true" />
                                            </div>
                                        </div>
                                    </template>
                                </li>
                            </ul>
                        </div>
                        <div v-else>
                            <p class="text-gray-500">No favorites found.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>