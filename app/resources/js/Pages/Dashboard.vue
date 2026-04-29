<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Welcome Section -->
                <div class="mb-8 px-4 sm:px-0">
                    <h3 class="text-2xl font-bold text-gray-900">Welcome back, {{ $page.props.auth.user.name }}!</h3>
                    <p class="mt-1 text-sm text-gray-500">Access your library, favorites, and history from here.</p>
                </div>

                <!-- Media Section -->
                <div class="mb-12">
                    <div class="px-4 sm:px-0 mb-4 flex items-center gap-2">
                        <div class="w-1 h-6 bg-blue-600 rounded-full"></div>
                        <h4 class="text-lg font-bold text-gray-800 uppercase tracking-wider">Media Content</h4>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Video Library -->
                        <Link :href="route('videos.index')" class="group bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 hover:shadow-md transition-all duration-200">
                            <div class="p-6">
                                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                                <h5 class="text-lg font-bold text-gray-900">Video Library</h5>
                                <p class="mt-2 text-sm text-gray-500">Browse and watch your collection of videos and folders.</p>
                                <div class="mt-4 flex items-center text-blue-600 text-sm font-semibold">
                                    Browse now
                                    <svg class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                            </div>
                        </Link>

                        <!-- Favorites -->
                        <Link :href="route('favorites.index')" class="group bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 hover:shadow-md transition-all duration-200">
                            <div class="p-6">
                                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center mb-4 group-hover:bg-purple-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </div>
                                <h5 class="text-lg font-bold text-gray-900">Favorites</h5>
                                <p class="mt-2 text-sm text-gray-500">Quickly access the videos and folders you've marked as favorites.</p>
                                <div class="mt-4 flex items-center text-purple-600 text-sm font-semibold">
                                    View favorites
                                    <svg class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                            </div>
                        </Link>

                        <!-- Viewing History -->
                        <Link :href="route('videos.history')" class="group bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 hover:shadow-md transition-all duration-200">
                            <div class="p-6">
                                <div class="w-12 h-12 bg-green-50 text-green-600 rounded-lg flex items-center justify-center mb-4 group-hover:bg-green-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h5 class="text-lg font-bold text-gray-900">Viewing History</h5>
                                <p class="mt-2 text-sm text-gray-500">Pick up where you left off with your recently watched videos.</p>
                                <div class="mt-4 flex items-center text-green-600 text-sm font-semibold">
                                    Resume watching
                                    <svg class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Admin Section -->
                <div v-if="$page.props.auth.user.role === 'admin'">
                    <div class="px-4 sm:px-0 mb-4 flex items-center gap-2">
                        <div class="w-1 h-6 bg-gray-800 rounded-full"></div>
                        <h4 class="text-lg font-bold text-gray-800 uppercase tracking-wider">Administration</h4>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- User Management -->
                        <Link :href="route('admin.users.index')" class="group bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 hover:shadow-md transition-all duration-200">
                            <div class="p-6 flex items-start gap-4">
                                <div class="shrink-0 w-12 h-12 bg-slate-50 text-slate-600 rounded-lg flex items-center justify-center group-hover:bg-slate-800 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h5 class="text-lg font-bold text-gray-900">User Management</h5>
                                    <p class="mt-1 text-sm text-gray-500">Manage system users, roles, and their allowed video paths.</p>
                                </div>
                            </div>
                        </Link>

                        <!-- HLS Cache -->
                        <Link :href="route('admin.hls.index')" class="group bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 hover:shadow-md transition-all duration-200">
                            <div class="p-6 flex items-start gap-4">
                                <div class="shrink-0 w-12 h-12 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center group-hover:bg-orange-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h5 class="text-lg font-bold text-gray-900">HLS Cache</h5>
                                    <p class="mt-1 text-sm text-gray-500">Monitor transcode processes and manage server disk space.</p>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
