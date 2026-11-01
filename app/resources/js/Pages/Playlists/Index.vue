<script setup>
import VideoProgress from '@/Components/VideoProgress.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WatchedBadge from '@/Components/WatchedBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
defineProps({ playlists: Array, status: String });
const remove = playlist => {
    if (confirm(`Delete "${playlist.name}"? Videos and watch history will be kept.`)) {
        router.delete(route('playlists.destroy', playlist.id));
    }
};
</script>
<template>
    <Head title="Playlists" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold">Playlists</h2></template>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-4">
            <p v-if="status" role="status" class="p-3 bg-blue-50">{{ status }}</p>
            <Link :href="route('playlists.create')" class="inline-block bg-blue-700 text-white px-4 py-2 rounded">Create Playlist</Link>
            <p v-if="!playlists.length">No playlists yet.</p>
            <section v-for="playlist in playlists" :key="playlist.id" class="bg-white shadow rounded p-4 space-y-3">
                <div class="flex flex-wrap items-center gap-4">
                    <h3 class="font-bold break-all">{{ playlist.name }}</h3>
                    <span>{{ playlist.items.length }} {{ playlist.items.length === 1 ? 'video' : 'videos' }}</span>
                    <Link :href="route('playlists.edit', playlist.id)" class="text-blue-700 underline">Edit</Link>
                    <button @click="remove(playlist)" class="text-red-700 underline">Delete</button>
                </div>
                <details>
                    <summary class="cursor-pointer py-2">Show Videos</summary>
                    <p v-if="!playlist.items.length" class="py-2 text-gray-600">No videos yet. Edit this playlist to add videos.</p>
                    <ol class="divide-y">
                        <li v-for="(item, index) in playlist.items" :key="item.id" class="py-3">
                            <div class="flex gap-3 items-start">
                                <span>{{ index + 1 }}.</span>
                                <Link v-if="item.playable" :href="route('playlists.watch', { playlist: playlist.id, item: item.id })" class="text-blue-700 underline break-all">{{ item.name }}</Link>
                                <span v-else class="text-gray-500 break-all">{{ item.name }} (Unavailable)</span>
                                <WatchedBadge :watched="item.is_watched" />
                            </div>
                            <VideoProgress v-if="item.playable" :percent="item.progress_percent" />
                        </li>
                    </ol>
                </details>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
