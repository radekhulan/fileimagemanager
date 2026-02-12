<script setup lang="ts">
import { useConfigStore } from '@/stores/configStore'

defineProps<{ show: boolean }>()

const configStore = useConfigStore()
const { t } = configStore
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-200"
    leave-active-class="transition-opacity duration-150"
    enter-from-class="opacity-0"
    leave-to-class="opacity-0"
  >
    <div
      v-if="show"
      class="absolute inset-0 z-30 flex items-center justify-center bg-white/60 dark:bg-neutral-900/60 backdrop-blur-sm"
    >
      <div class="flex flex-col items-center gap-3">
        <svg class="w-8 h-8 text-rfm-primary rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
        </svg>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('Loading') }}</span>
      </div>
    </div>
  </Transition>
</template>
