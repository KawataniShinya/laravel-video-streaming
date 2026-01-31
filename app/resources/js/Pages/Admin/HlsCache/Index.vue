<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    caches: {
        type: Array,
        required: true,
    },
    totalSize: {
        type: String,
        required: true,
    },
});

const form = useForm({});

const deleteCache = (hash) => {
    if (confirm('Are you sure you want to delete this cache?')) {
        form.delete(route('admin.hls.destroy', hash), {
            preserveScroll: true,
        });
    }
};

const deleteAllCaches = () => {
    if (confirm('Are you sure you want to delete ALL caches? This action cannot be undone.')) {
        form.delete(route('admin.hls.destroy_all'), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="HLS Cache Management" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                HLS Cache Management
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex justify-between items-center mb-6">
                            <div class="text-lg font-medium">
                                Total Cache Size: <span class="font-bold">{{ totalSize }}</span>
                            </div>
                            <button
                                @click="deleteAllCaches"
                                class="bg-red-600 hover:bg-red-800 text-white font-bold py-2 px-4 rounded"
                                :disabled="caches.length === 0"
                                :class="{'opacity-50 cursor-not-allowed': caches.length === 0}"
                            >
                                Delete All Caches
                            </button>
                        </div>

                        <div v-if="caches.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Path (Best Effort)</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hash</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="cache in caches" :key="cache.hash">
                                        <td class="px-6 py-4 text-sm text-gray-900 break-all">{{ cache.path }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ cache.hash }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ cache.size }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button @click="deleteCache(cache.hash)" class="text-red-600 hover:text-red-900">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center text-gray-500 py-4">
                            No HLS caches found.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
