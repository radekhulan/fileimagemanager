<script setup lang="ts">
import type { FileItem } from '@/types/files'
import { useConfigStore } from '@/stores/configStore'

const props = defineProps<{
  item: FileItem
}>()

const emit = defineEmits<{
  navigate: [item: FileItem]
  rename: [item: FileItem]
  'delete': [item: FileItem]
}>()

const configStore = useConfigStore()
const config = configStore.config
</script>

<template>
  <div
    class="rfm-card group/card flex flex-col rounded-lg border border-gray-200 dark:border-neutral-700
           bg-white dark:bg-neutral-800 overflow-hidden cursor-pointer
           hover:shadow-md hover:border-gray-300 dark:hover:border-neutral-600 transition-[box-shadow,border-color] duration-150"
    @click="emit('navigate', props.item)"
  >
    <!-- Folder icon area -->
    <div class="relative aspect-[4/3] bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50
                dark:from-amber-950/30 dark:via-orange-950/20 dark:to-yellow-950/10
                flex items-center justify-center overflow-hidden p-3.5">
      <svg
        class="w-full h-full text-amber-500 dark:text-amber-400 drop-shadow-md"
        viewBox="0 0 48 40"
        fill="currentColor"
        preserveAspectRatio="xMidYMid meet"
      >
        <path d="M2 10c0-1.1.9-2 2-2h12l4 4h22c1.1 0 2 .9 2 2v1H2v-5z" opacity="0.5" />
        <path d="M2 15h44v21c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V15z" />
        <path d="M2 15h44v3H2z" fill="white" opacity="0.2" />
      </svg>

      <!-- Hover action bar -->
      <div
        class="absolute inset-x-0 bottom-0 flex flex-wrap items-center justify-center gap-0.5 px-1 py-1
               bg-gradient-to-t from-black/60 via-black/40 to-transparent
               opacity-0 group-hover/card:opacity-100 transition-opacity duration-150"
        @click.stop
      >
        <!-- Rename -->
        <button
          v-if="config?.renameFolders"
          class="rfm-action-btn"
          :title="configStore.t('Rename')"
          @click="emit('rename', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
          </svg>
        </button>

        <!-- Delete -->
        <button
          v-if="config?.deleteFolders"
          class="rfm-action-btn !text-red-400 hover:!bg-red-500/30"
          :title="configStore.t('Delete')"
          @click="emit('delete', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Folder name -->
    <div class="px-2 py-1.5 min-w-0">
      <p
        class="text-xs font-medium text-gray-700 dark:text-gray-200 truncate leading-tight"
        :title="props.item.name"
      >
        {{ props.item.name }}
      </p>
      <p class="mt-0.5 text-[10px] text-amber-500/70 dark:text-amber-400/50 font-medium uppercase tracking-wide leading-tight">
        {{ configStore.t('Folder') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.rfm-action-btn {
  @apply flex items-center justify-center w-6 h-6 rounded
         text-white/90 hover:text-white hover:bg-white/20
         transition-colors duration-100 cursor-pointer;
}
.rfm-action-btn svg {
  @apply w-3.5 h-3.5;
}
</style>
