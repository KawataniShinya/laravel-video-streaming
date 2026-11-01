<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WatchedBadge from '@/Components/WatchedBadge.vue';
import VideoPlayer from '@/Components/VideoPlayer.vue';
import FavoriteToggle from '@/Components/FavoriteToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ playlist: Object, currentItem: Number, hasNext: Boolean, filename: String, path: String, hash: String, lastPosition: Number, isCached: Boolean, isFavorited: Boolean });
</script>
<template>
    <Head :title="`${filename} — ${playlist.name}`" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold break-all">{{ playlist.name }}</h2></template>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-4">
            <Link :href="route('playlists.index')" class="text-blue-700 underline">Back to Playlists</Link>
            <div class="grid lg:grid-cols-3 gap-6">
                <section class="lg:col-span-2 bg-white p-4 rounded shadow min-w-0">
                    <div class="flex items-start gap-3 justify-between mb-4">
                        <h3 class="font-bold break-all">{{ filename }}</h3>
                        <FavoriteToggle :key="path" :path="path" type="file" :is-favorited="isFavorited" />
                    </div>
                    <VideoPlayer :key="`${currentItem}:${path}:${lastPosition}`" :path="path" :hash="hash" :last-position="lastPosition" :is-cached="isCached" />
                    <Link v-if="hasNext" :href="route('playlists.next', { playlist: playlist.id, item: currentItem })" class="inline-block mt-4 bg-blue-700 text-white px-4 py-2 rounded">Play Next</Link>
                    <button v-else disabled class="mt-4 bg-gray-200 text-gray-600 px-4 py-2 rounded">Play Next (End of Playlist)</button>
                </section>
                <aside class="bg-white p-4 rounded shadow min-w-0" aria-label="Playlist">
                    <h3 class="font-bold mb-3">Playlist ({{ playlist.items.findIndex(item => item.id === currentItem) + 1 }} / {{ playlist.items.length }})</h3>
                    <ol class="divide-y">
                        <li v-for="(item, index) in playlist.items" :key="item.id" class="py-3" :class="item.id === currentItem ? 'bg-blue-50' : ''">
                            <span>{{ index + 1 }}. </span>
                            <Link v-if="item.playable" :href="route('playlists.watch', { playlist: playlist.id, item: item.id })" :aria-current="item.id === currentItem ? 'true' : undefined" class="text-blue-700 underline break-all">{{ item.name }}</Link>
                            <span v-else class="text-gray-500 break-all">{{ item.name }} (Unavailable)</span>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <WatchedBadge :watched="item.is_watched" />
                                <strong v-if="item.id === currentItem" class="text-sm">Now Playing</strong>
                            </div>
                        </li>
                    </ol>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
