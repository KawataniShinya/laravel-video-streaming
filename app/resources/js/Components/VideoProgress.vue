<script setup>
defineProps({ percent: { type: Number, default: null } });
</script>
<template>
    <div class="mt-2 flex items-center gap-2 w-full" :title="percent === null ? 'Total duration is not available yet' : `Playback position: ${percent}%`">
        <div v-if="percent !== null" role="progressbar" aria-label="Playback position" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" class="h-1 flex-1 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-blue-600" :style="{ width: `${percent}%` }"></div>
        </div>
        <div v-else aria-hidden="true" class="h-1 flex-1 rounded-full bg-gray-200"></div>
        <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ percent === null ? 'Progress unknown' : `${percent}%` }}</span>
    </div>
</template>
