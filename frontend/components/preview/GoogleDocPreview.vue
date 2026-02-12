<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  url: string
  name: string
}>()

defineEmits<{ close: [] }>()

const embedUrl = computed(() => {
  return `https://docs.google.com/gview?url=${encodeURIComponent(props.url)}&embedded=true`
})
</script>

<template>
  <div class="flex flex-col w-full h-full p-4">
    <h3 class="text-sm font-medium text-gray-300 dark:text-gray-500 mb-3 truncate">{{ name }}</h3>
    <iframe
      :src="embedUrl"
      class="flex-1 w-full rounded-lg border border-gray-200 dark:border-neutral-700 bg-white"
      frameborder="0"
    />
  </div>
</template>
