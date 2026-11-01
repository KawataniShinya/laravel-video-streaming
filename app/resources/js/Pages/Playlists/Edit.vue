<script setup>
import VideoProgress from '@/Components/VideoProgress.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
const props = defineProps({ playlist: Object, items: Array, currentPath: String });
const selected = ref((props.playlist?.items || []).map(item => ({ ...item })));
const form = useForm({ name: props.playlist?.name || '', items: [] });
const breadcrumbs = computed(() => {
    const parts = props.currentPath.split('/').filter(Boolean);
    return parts.map((name, index) => ({ name, path: parts.slice(0, index + 1).join('/') }));
});
const parent = computed(() => props.currentPath.split('/').slice(0, -1).join('/'));
const browse = path => props.playlist
    ? route('playlists.edit', { playlist: props.playlist.id, path })
    : route('playlists.create', { path });
const contains = item => selected.value.some(row => row.video_id === item.id);
const add = item => {
    if (!contains(item)) selected.value.push({ video_id: item.id, name: item.name, path: item.path, playable: true, progress_percent: item.progress_percent });
};
const move = (index, offset) => {
    const target = index + offset;
    if (target < 0 || target >= selected.value.length) return;
    [selected.value[index], selected.value[target]] = [selected.value[target], selected.value[index]];
};
const save = () => {
    form.items = selected.value.map(item => item.id ? { id: item.id } : { video_id: item.video_id });
    if (props.playlist) form.put(route('playlists.update', props.playlist.id));
    else form.post(route('playlists.store'));
};
</script>
<template>
    <Head :title="playlist ? 'Edit Playlist' : 'Create Playlist'" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold">{{ playlist ? 'Edit Playlist' : 'Create Playlist' }}</h2></template>
        <form @submit.prevent="save" class="max-w-7xl mx-auto p-4 sm:p-6 space-y-4">
            <div>
                <label for="playlist-name" class="block font-bold mb-2">Name</label>
                <input id="playlist-name" v-model="form.name" required maxlength="255" class="rounded border-gray-300 w-full" />
            </div>
            <div v-if="Object.keys(form.errors).length" role="alert" class="bg-red-50 text-red-700 p-3">
                <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
            </div>
            <div class="grid md:grid-cols-2 gap-6">
                <section class="bg-white rounded shadow p-4 min-w-0">
                    <h3 class="font-bold mb-3">Select Videos</h3>
                    <nav aria-label="Folder breadcrumb" class="text-sm mb-3">
                        <ol class="flex flex-wrap items-center gap-2">
                            <li class="min-w-0">
                                <Link preserve-state :href="browse('')" :aria-current="!currentPath ? 'page' : undefined" class="inline-block py-1 text-blue-700 underline">Library</Link>
                            </li>
                            <li v-for="crumb in breadcrumbs" :key="crumb.path" class="flex items-center gap-2 min-w-0">
                                <span aria-hidden="true" class="text-gray-400">/</span>
                                <Link preserve-state :href="browse(crumb.path)" :aria-current="crumb.path === currentPath ? 'page' : undefined" class="min-w-0 break-all py-1 text-blue-700 underline">{{ crumb.name }}</Link>
                            </li>
                        </ol>
                    </nav>
                    <Link v-if="currentPath" preserve-state :href="browse(parent)" class="inline-block py-2 text-blue-700 underline">Parent Folder</Link>
                    <p v-if="!items.length" class="text-gray-600">No videos or folders available.</p>
                    <ul class="divide-y">
                        <li v-for="item in items" :key="item.id" class="py-3 flex items-center gap-3">
                            <Link v-if="item.type === 'folder'" preserve-state :href="browse(item.path)" class="text-blue-700 underline break-all">📁 {{ item.name }}</Link>
                            <template v-else>
                                <div class="flex-1 min-w-0"><span class="break-all">{{ item.name }}</span><VideoProgress :percent="item.progress_percent" /></div>
                                <button type="button" @click="add(item)" :disabled="contains(item)" :aria-label="`Add ${item.name}`" class="shrink-0 rounded bg-blue-700 text-white px-3 py-2 disabled:bg-gray-500">{{ contains(item) ? 'Added' : 'Add' }}</button>
                            </template>
                        </li>
                    </ul>
                </section>
                <section class="bg-white rounded shadow p-4 min-w-0">
                    <h3 class="font-bold mb-3">Play Order ({{ selected.length }})</h3>
                    <p v-if="!selected.length">Select videos to add to this playlist.</p>
                    <ol class="divide-y">
                        <li v-for="(item, index) in selected" :key="item.id ? `item-${item.id}` : `video-${item.video_id}`" class="py-3 space-y-2">
                            <p class="break-all">{{ index + 1 }}. {{ item.name }}{{ item.playable ? '' : ' (Unavailable)' }}</p>
                            <p v-if="item.path" class="text-xs text-gray-500 break-all">{{ item.path }}</p>
                            <VideoProgress v-if="item.playable" :percent="item.progress_percent" />
                            <div class="flex flex-wrap gap-3">
                                <button type="button" @click="move(index, -1)" :disabled="index === 0" :aria-label="`Move ${item.name} up`" class="px-3 py-2 border rounded disabled:opacity-40">Move Up</button>
                                <button type="button" @click="move(index, 1)" :disabled="index === selected.length - 1" :aria-label="`Move ${item.name} down`" class="px-3 py-2 border rounded disabled:opacity-40">Move Down</button>
                                <button type="button" @click="selected.splice(index, 1)" :aria-label="`Remove ${item.name}`" class="px-3 py-2 text-red-700 underline">Remove</button>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>
            <div class="flex gap-4 items-center">
                <button :disabled="form.processing" class="bg-blue-700 text-white px-5 py-2 rounded disabled:opacity-50">Save</button>
                <Link :href="route('playlists.index')" class="underline">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
