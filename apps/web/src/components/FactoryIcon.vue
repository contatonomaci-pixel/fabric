<script setup lang="ts">
withDefaults(defineProps<{ size?: number; filled?: boolean }>(), { size: 24, filled: false })
</script>
<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round" aria-hidden="true">
    <path d="M3 21V12l6-4v4l6-4v4h3V3h3v18Z" :fill="filled ? 'currentColor' : 'none'" />
    <g :fill="filled ? '#17634b' : 'none'" :stroke="filled ? '#17634b' : 'currentColor'"><path d="M6 16h2v2H6zM11 16h2v2h-2zM16 16h2v2h-2z" /></g>
  </svg>
</template>
