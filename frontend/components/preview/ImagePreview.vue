<script setup lang="ts">
import { ref } from 'vue'
import { useConfigStore } from '@/stores/configStore'

const props = defineProps<{
  url: string
  name: string
}>()

defineEmits<{ close: [] }>()

const configStore = useConfigStore()
const { t } = configStore

const loaded = ref(false)
const error = ref(false)
const zoomed = ref(false)

function toggleZoom() {
  zoomed.value = !zoomed.value
}
</script>

<template>
  <div class="flex flex-col items-center justify-center w-full h-full p-4">
    <!-- Loading -->
    <div v-if="!loaded && !error" class="flex items-center justify-center">
      <svg class="w-10 h-10 text-rfm-primary rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
      </svg>
    </div>

    <!-- Error -->
    <div v-if="error" class="text-center text-gray-500 dark:text-gray-400">
      <svg class="w-16 h-16 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>{{ t('Image_Load_Failed') }}</p>
    </div>

    <!-- Image -->
    <img
      :src="url"
      :alt="name"
      class="max-w-full max-h-full object-contain transition-transform duration-200"
      :class="[
        zoomed ? 'cursor-zoom-out scale-150' : 'cursor-zoom-in',
        loaded ? 'opacity-100' : 'opacity-0 absolute'
      ]"
      @load="loaded = true"
      @error="error = true"
      @click="toggleZoom"
    />

    <!-- Image name -->
    <p v-if="loaded" class="mt-3 text-sm text-gray-400 dark:text-gray-500 text-center truncate max-w-md">
      {{ name }}
    </p>
  </div>
</template>
