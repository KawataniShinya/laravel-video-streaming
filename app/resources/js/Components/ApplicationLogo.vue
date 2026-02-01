<script setup>
import logoSvg from '../../images/logo.svg?raw';
</script>

<template>
    <div v-bind="$attrs" v-html="logoSvg" class="flex items-center justify-center logo-container"></div>
</template>

<style scoped>
.logo-container :deep(svg) {
    width: 100%;
    height: 100%;
    display: block;
    fill: currentColor; /* Use currentColor to inherit color from the parent text color classes */
}
</style>