<script setup lang="ts">
import { ref, computed } from 'vue'
import { useConfigStore } from '@/stores/configStore'

const props = defineProps<{
  url: string
  name: string
  type: 'video' | 'audio'
}>()

defineEmits<{ close: [] }>()

const configStore = useConfigStore()
const { t } = configStore

const error = ref(false)

const isVideo = computed(() => props.type === 'video')
</script>

<template>
  <div class="flex flex-col items-center justify-center w-full h-full p-4">
    <!-- Error -->
    <div v-if="error" class="text-center text-gray-500 dark:text-gray-400">
      <svg class="w-16 h-16 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>{{ t('Media_Load_Failed') }}</p>
    </div>

    <!-- Video player -->
    <video
      v-if="isVideo && !error"
      class="max-w-full max-h-[70vh] rounded-lg shadow-lg bg-black"
      controls
      autoplay
      @error="error = true"
    >
      <source :src="url" />
      {{ t('Browser_No_Video') }}
    </video>

    <!-- Audio player -->
    <div v-else-if="!isVideo && !error" class="w-full max-w-md">
      <div class="flex flex-col items-center gap-4 p-6 bg-white dark:bg-neutral-800 rounded-xl shadow-lg">
        <svg class="w-16 h-16 text-rfm-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path d="M9 18V5l12-2v13" />
          <circle cx="6" cy="18" r="3" />
          <circle cx="18" cy="16" r="3" />
        </svg>
        <p class="text-sm text-gray-700 dark:text-gray-300 font-medium truncate max-w-full">{{ name }}</p>
        <audio
          class="w-full"
          controls
          autoplay
          @error="error = true"
        >
          <source :src="url" />
          {{ t('Browser_No_Audio') }}
        </audio>
      </div>
    </div>

    <!-- Name -->
    <p v-if="!error && isVideo" class="mt-3 text-sm text-gray-400 dark:text-gray-500 text-center truncate max-w-md">
      {{ name }}
    </p>
  </div>
</template>
