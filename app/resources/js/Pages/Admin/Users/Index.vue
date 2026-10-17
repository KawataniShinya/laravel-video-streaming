<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    users: {
        type: Array,
        required: true,
    },
});

const form = useForm({});

const deleteUser = (id) => {
    if (confirm('Are you sure you want to delete this user?')) {
        form.delete(route('admin.users.destroy', id));
    }
};
</script>

<template>
    <Head title="User Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    User Management
                </h2>
                <Link
                    :href="route('admin.users.create')"
                    class="self-start shrink-0 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                >
                    Create New User
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6 text-gray-900">
                        <ul class="divide-y divide-gray-200 md:hidden" aria-label="Users">
                            <li v-for="user in users" :key="user.id" class="py-4">
                                <dl class="space-y-2">
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-gray-500">Name</dt>
                                        <dd class="break-words font-medium">{{ user.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-gray-500">Email</dt>
                                        <dd class="break-all">{{ user.email }}</dd>
                                    </div>
                                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                                        <div>
                                            <dt class="text-xs font-medium uppercase text-gray-500">ID</dt>
                                            <dd class="break-all">{{ user.id }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium uppercase text-gray-500">Role</dt>
                                            <dd class="break-words">{{ user.role }}</dd>
                                        </div>
                                    </div>
                                </dl>
                                <div class="mt-3 flex flex-wrap gap-3 text-sm font-medium">
                                    <Link :href="route('admin.users.edit', user.id)" class="rounded px-3 py-3 text-indigo-600 hover:text-indigo-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">Edit</Link>
                                    <button type="button" @click="deleteUser(user.id)" class="rounded px-3 py-3 text-red-600 hover:text-red-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600">Delete</button>
                                </div>
                            </li>
                        </ul>

                        <div class="hidden overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 md:block" tabindex="0" role="region" aria-label="Users table" scroll-region>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users" :key="user.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ user.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ user.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ user.email }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ user.role }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link :href="route('admin.users.edit', user.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</Link>
                                            <button @click="deleteUser(user.id)" class="text-red-600 hover:text-red-900">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
