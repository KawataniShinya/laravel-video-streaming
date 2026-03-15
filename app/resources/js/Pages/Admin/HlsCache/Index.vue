<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    caches: {
        type: Array,
        required: true,
    },
    freeDiskSpace: {
        type: String,
        required: true,
    },
    totalDiskSpace: {
        type: String,
        required: true,
    },
});

const cacheItems = ref(props.caches.map(c => ({ ...c })));
const selectedHashes = ref([]);
const sortKey = ref('path');
const sortOrder = ref('asc');

const totalSizeBytes = computed(() => {
    return cacheItems.value.reduce((acc, curr) => acc + (curr.size_bytes || 0), 0);
});

// Simple formatter for JavaScript side
const formatBytesJS = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + units[i];
};

const totalSizeFormatted = computed(() => {
    return formatBytesJS(totalSizeBytes.value);
});

const isFetching = ref(false);

const fetchNextSize = async () => {
    const nextItem = cacheItems.value.find(item => item.size === null);
    if (!nextItem) {
        isFetching.value = false;
        return;
    }

    isFetching.value = true;
    try {
        const response = await axios.get(route('admin.hls.size', nextItem.hash));
        nextItem.size = response.data.size_formatted;
        nextItem.size_bytes = response.data.size_bytes;
        // Proceed to next
        fetchNextSize();
    } catch (error) {
        if (error.response && error.response.status === 429) {
            // Wait 2 seconds and retry same item
            setTimeout(fetchNextSize, 2000);
        } else {
            nextItem.size = 'Error';
            fetchNextSize();
        }
    }
};

onMounted(() => {
    fetchNextSize();
});

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortOrder.value = 'asc';
    }
};

const sortedCaches = computed(() => {
    return [...cacheItems.value].sort((a, b) => {
        let modifier = sortOrder.value === 'asc' ? 1 : -1;
        
        let valA = a[sortKey.value];
        let valB = b[sortKey.value];
        
        // Use size_bytes for sorting by size
        if (sortKey.value === 'size') {
            valA = a.size_bytes || 0;
            valB = b.size_bytes || 0;
        }

        if (valA === null) return 1; // Put nulls at end
        if (valB === null) return -1;

        if (valA < valB) return -1 * modifier;
        if (valA > valB) return 1 * modifier;
        return 0;
    });
});

const toggleAll = (e) => {
    if (e.target.checked) {
        selectedHashes.value = sortedCaches.value.map(c => c.hash);
    } else {
        selectedHashes.value = [];
    }
};

const isAllSelected = computed(() => {
    return sortedCaches.value.length > 0 && selectedHashes.value.length === sortedCaches.value.length;
});

const form = useForm({
    hashes: []
});

const deleteCache = (hash) => {
    if (confirm('Are you sure you want to delete this cache?')) {
        form.delete(route('admin.hls.destroy', hash), {
            preserveScroll: true,
            onSuccess: () => {
                cacheItems.value = cacheItems.value.filter(i => i.hash !== hash);
            }
        });
    }
};

const deleteSelected = () => {
    const selectedItems = cacheItems.value.filter(c => selectedHashes.value.includes(c.hash));
    const paths = selectedItems.map(i => i.path).join('\n');
    
    if (confirm(`Are you sure you want to delete the following ${selectedHashes.value.length} caches?\n\n${paths}`)) {
        form.hashes = selectedHashes.value;
        form.post(route('admin.hls.destroy_multiple'), {
            preserveScroll: true,
            onSuccess: () => {
                cacheItems.value = cacheItems.value.filter(i => !selectedHashes.value.includes(i.hash));
                selectedHashes.value = [];
            }
        });
    }
};

const deleteAllCaches = () => {
    if (confirm('Are you sure you want to delete ALL caches? This action cannot be undone.')) {
        form.delete(route('admin.hls.destroy_all'), {
            preserveScroll: true,
            onSuccess: () => {
                cacheItems.value = [];
                selectedHashes.value = [];
            }
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
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                            <div class="space-y-1">
                                <div class="text-lg font-medium flex items-center gap-2">
                                    Total Cache Size: 
                                    <span class="font-bold text-blue-600">{{ totalSizeFormatted }}</span>
                                    <svg v-if="isFetching" class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <div class="text-sm text-gray-500">
                                    Disk Space: <span class="font-semibold">{{ freeDiskSpace }}</span> free of <span class="font-semibold">{{ totalDiskSpace }}</span>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button
                                    v-if="selectedHashes.length > 0"
                                    @click="deleteSelected"
                                    class="bg-orange-600 hover:bg-orange-800 text-white font-bold py-2 px-4 rounded transition shadow-sm"
                                >
                                    Delete Selected ({{ selectedHashes.length }})
                                </button>
                                <button
                                    @click="deleteAllCaches"
                                    class="bg-red-600 hover:bg-red-800 text-white font-bold py-2 px-4 rounded transition shadow-sm"
                                    :disabled="cacheItems.length === 0"
                                    :class="{'opacity-50 cursor-not-allowed': cacheItems.length === 0}"
                                >
                                    Delete All Caches
                                </button>
                            </div>
                        </div>

                        <div v-if="cacheItems.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left">
                                            <input 
                                                type="checkbox" 
                                                :checked="isAllSelected" 
                                                @change="toggleAll"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            >
                                        </th>
                                        <th 
                                            scope="col" 
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition"
                                            @click="sortBy('path')"
                                        >
                                            <div class="flex items-center gap-1">
                                                Path (Best Effort)
                                                <span v-if="sortKey === 'path'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                            </div>
                                        </th>
                                        <th 
                                            scope="col" 
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition"
                                            @click="sortBy('status')"
                                        >
                                            <div class="flex items-center gap-1">
                                                Status
                                                <span v-if="sortKey === 'status'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                            </div>
                                        </th>
                                        <th 
                                            scope="col" 
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition"
                                            @click="sortBy('size')"
                                        >
                                            <div class="flex items-center gap-1">
                                                Size
                                                <span v-if="sortKey === 'size'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="cache in sortedCaches" :key="cache.hash" :class="{'bg-blue-50': selectedHashes.includes(cache.hash)}">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input 
                                                type="checkbox" 
                                                v-model="selectedHashes" 
                                                :value="cache.hash"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            >
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 break-all">{{ cache.path }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span v-if="cache.status === 'completed'" class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-semibold">
                                                Completed
                                            </span>
                                            <span v-else-if="cache.status === 'transcoding'" class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full font-semibold animate-pulse">
                                                Transcoding...
                                            </span>
                                            <span v-else class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full font-semibold">
                                                Failed / Incomplete
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span v-if="cache.size === null" class="text-gray-400 italic text-xs flex items-center gap-1">
                                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Calculating...
                                            </span>
                                            <span v-else>{{ cache.size }}</span>
                                        </td>
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
