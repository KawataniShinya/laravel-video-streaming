<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    path: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        required: true,
    },
    isFavorited: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    path: props.path,
    type: props.type,
});

const toggle = () => {
    form.post(route('favorites.toggle'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <button @click="toggle" class="focus:outline-none transition duration-150 ease-in-out">
        <svg 
            xmlns="http://www.w3.org/2000/svg" 
            :class="['w-6 h-6', isFavorited ? 'text-red-500 fill-current' : 'text-gray-400 fill-none hover:text-red-500']"
            viewBox="0 0 24 24" 
            stroke="currentColor" 
            stroke-width="2"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
    </button>
</template>
