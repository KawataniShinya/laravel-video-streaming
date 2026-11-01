<script setup>
defineProps({ watched: Boolean });
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center whitespace-nowrap rounded px-2 py-1 text-xs font-bold"
        :class="watched ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600'"
    >
        {{ watched ? 'Watched' : 'Unwatched' }}
    </span>
</template>
