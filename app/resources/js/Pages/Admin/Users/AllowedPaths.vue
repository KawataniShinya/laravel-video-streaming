<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    user: Object,
    items: Array,
    currentPath: String,
});

// Use a regular ref. preserve-state on folder links will keep this state alive 
// during directory navigation, but it will refresh when coming from Edit User page.
const selectedPaths = ref(props.user.allowed_paths.map(p => p.path));

const togglePath = (path) => {
    const index = selectedPaths.value.indexOf(path);
    if (index === -1) {
        selectedPaths.value = [...selectedPaths.value, path];
    } else {
        selectedPaths.value = selectedPaths.value.filter(p => p !== path);
    }
};

const removePath = (path) => {
    selectedPaths.value = selectedPaths.value.filter(p => p !== path);
};

const form = useForm({
    paths: []
});

const isProcessing = ref(false);
const showConfirm = ref(false);

const optimizePaths = () => {
    let paths = [...selectedPaths.value];
    paths.sort();

    let filtered = [];
    for (let path of paths) {
        let isRedundant = false;
        for (let alreadyAdded of filtered) {
            if (alreadyAdded === '' || path === alreadyAdded || path.startsWith(alreadyAdded + '/')) {
                isRedundant = true;
                break;
            }
        }
        if (!isRedundant) {
            filtered.push(path);
        }
    }
    return filtered;
};

const optimizedList = computed(() => optimizePaths());

const submit = () => {
    form.paths = optimizedList.value;
    form.post(route('admin.users.allowed-paths.update', props.user.id), {
        onSuccess: () => {
            showConfirm.value = false;
        },
    });
};

const getParentPath = (path) => {
    if (!path) return null;
    const parts = path.split('/');
    parts.pop();
    return parts.length > 0 ? parts.join('/') : '';
};
</script>

<template>
    <Head title="Edit Allowed Paths" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Allowed Paths for: {{ user.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 flex gap-6">
                <!-- Left: File Browser -->
                <div class="w-2/3 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold">Select Paths</h3>
                            <button 
                                @click="togglePath('')" 
                                :class="[
                                    'px-3 py-1 rounded text-sm font-bold transition',
                                    selectedPaths.includes('') ? 'bg-red-500 text-white' : 'bg-green-500 text-white'
                                ]"
                            >
                                {{ selectedPaths.includes('') ? 'Remove All Access' : 'Allow All Access (Root)' }}
                            </button>
                        </div>

                        <div v-if="form.errors.paths" class="mb-4 p-4 bg-red-100 text-red-700 rounded-md text-sm">
                            {{ form.errors.paths }}
                        </div>

                        <div class="mb-4 text-sm text-gray-600 font-mono bg-gray-50 p-2 rounded">
                            /videos/{{ currentPath }}
                        </div>
                        <ul class="divide-y divide-gray-200 border border-gray-200 rounded-md">
                            <li v-if="currentPath !== ''" class="hover:bg-gray-50 transition">
                                <Link preserve-state :href="route('admin.users.allowed-paths.edit', { user: user.id, path: getParentPath(currentPath) })" class="block px-4 py-3 flex items-center">
                                    <span class="text-blue-500 mr-3">
                                        <svg class="w-5 h-5 fill-none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>
                                    </span>
                                    <span class="text-gray-900">.. (Parent)</span>
                                </Link>
                            </li>
                            <li v-for="item in items" :key="item.path" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition">
                                <div class="flex items-center flex-grow">
                                    <template v-if="item.type === 'folder'">
                                        <Link preserve-state :href="route('admin.users.allowed-paths.edit', { user: user.id, path: item.path })" class="flex items-center flex-grow">
                                            <svg class="w-5 h-5 text-yellow-500 mr-2 fill-current" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                            <span class="text-gray-900">{{ item.name }}</span>
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-center flex-grow opacity-60">
                                            <svg class="w-5 h-5 text-blue-500 mr-2 fill-current" viewBox="0 0 24 24"><path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path></svg>
                                            <span class="text-gray-700">{{ item.name }}</span>
                                        </div>
                                    </template>
                                </div>
                                <button
                                    @click="togglePath(item.path)"
                                    :class="[
                                        'ml-4 px-4 py-1 rounded text-xs font-bold transition',
                                        selectedPaths.includes(item.path) ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                                    ]"
                                >
                                    {{ selectedPaths.includes(item.path) ? 'Remove' : 'Select' }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Right: Selected List -->
                <div class="w-1/3 flex flex-col gap-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-bold mb-4">Selected Paths</h3>
                            <div v-if="selectedPaths.length === 0" class="text-gray-500 text-sm italic">
                                No paths selected. User will have no access.
                            </div>
                            <ul class="space-y-2 max-h-96 overflow-y-auto">
                                <li v-for="path in selectedPaths" :key="path" class="flex items-center justify-between bg-gray-50 p-2 rounded text-xs border border-gray-100">
                                    <code class="truncate flex-grow">{{ path === '' ? '/' : path }}</code>
                                    <button @click="removePath(path)" class="ml-2 text-gray-400 hover:text-red-600 font-bold">×</button>
                                </li>
                            </ul>

                            <div class="mt-6 flex flex-col gap-3">
                                <button 
                                    @click="showConfirm = true"
                                    class="w-full bg-blue-600 text-white py-2 rounded font-bold hover:bg-blue-700 disabled:opacity-50"
                                >
                                    Review Changes
                                </button>
                                <Link :href="route('admin.users.edit', user.id)" class="text-center text-sm text-gray-600 underline">
                                    Back to User Edit
                                </Link>
                            </div>                        </div>
                    </div>

                    <!-- Optimization Preview (Visible only when showConfirm) -->
                    <div v-if="showConfirm" class="bg-blue-50 border border-blue-200 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-md font-bold text-blue-800 mb-2">Optimized Access List</h3>
                            <p class="text-xs text-blue-600 mb-4"> Redundant sub-paths have been removed.</p>
                            <ul v-if="optimizedList.length > 0" class="space-y-1 mb-6">
                                <li v-for="path in optimizedList" :key="path" class="text-xs font-mono text-gray-700">
                                    - {{ path === '' ? '(Full Access)' : path }}
                                </li>
                            </ul>
                            <div v-else class="mb-6 p-3 bg-red-100 text-red-700 text-xs rounded border border-red-200">
                                <strong>Warning:</strong> No paths selected. This user will have <strong>NO ACCESS</strong> to any videos.
                            </div>
                            <div class="flex gap-2">
                                <button 
                                    @click="submit" 
                                    class="flex-grow bg-green-600 text-white py-2 rounded font-bold hover:bg-green-700 disabled:opacity-50"
                                    :disabled="form.processing"
                                >
                                    {{ form.processing ? 'Saving...' : 'Confirm & Save' }}
                                </button>
                                <button 
                                    @click="showConfirm = false" 
                                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded font-bold hover:bg-gray-300"
                                    :disabled="form.processing"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
