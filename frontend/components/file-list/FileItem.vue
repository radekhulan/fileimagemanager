<script setup lang="ts">
import type { FileItem } from '@/types/files'
import { formatFileSize } from '@/utils/filesize'
import { getIconColor, getIconType, isEditableImage } from '@/utils/extensions'
import { useConfigStore } from '@/stores/configStore'
import Thumbnail from './Thumbnail.vue'

const props = defineProps<{
  item: FileItem
}>()

const emit = defineEmits<{
  click: [item: FileItem]
  preview: [item: FileItem]
  editImage: [item: FileItem]
  download: [item: FileItem]
  rename: [item: FileItem]
  duplicate: [item: FileItem]
  'delete': [item: FileItem]
}>()

const configStore = useConfigStore()
const config = configStore.config
</script>

<template>
  <div
    class="rfm-card group/card flex flex-col rounded-lg border border-gray-200 dark:border-neutral-700
           bg-white dark:bg-neutral-800 overflow-hidden cursor-pointer
           hover:shadow-md hover:border-gray-300 dark:hover:border-neutral-600 transition-all duration-150"
    @click="emit('click', props.item)"
  >
    <!-- Thumbnail area -->
    <div class="relative aspect-[4/3] bg-gray-100 dark:bg-neutral-900 overflow-hidden">
      <!-- Image thumbnail -->
      <Thumbnail
        v-if="props.item.thumbnailUrl"
        :src="props.item.thumbnailUrl"
        :alt="props.item.name"
      />

      <!-- File type icon (non-image) -->
      <div v-else class="flex items-center justify-center w-full h-full">
        <svg
          class="w-14 h-14"
          :class="getIconColor(props.item.extension, props.item.category)"
          viewBox="0 0 48 48"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
        >
          <!-- PDF -->
          <template v-if="getIconType(props.item.extension, props.item.category) === 'pdf'">
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
            <path d="M28 6v10h10" />
            <rect x="13" y="26" width="22" height="13" rx="2" fill="currentColor" opacity="0.15" />
            <path d="M18 35v-6h3a2.5 2.5 0 010 5h-3" stroke-width="1.5" />
            <path d="M26 35v-6h3a2.5 2.5 0 011.5 4.5L27 35h3" stroke-width="1.5" />
          </template>
          <!-- Word -->
          <template v-else-if="getIconType(props.item.extension, props.item.category) === 'word'">
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
            <path d="M28 6v10h10" />
            <line x1="16" y1="24" x2="32" y2="24" />
            <line x1="16" y1="30" x2="28" y2="30" />
            <line x1="16" y1="36" x2="24" y2="36" />
          </template>
          <!-- Excel -->
          <template v-else-if="getIconType(props.item.extension, props.item.category) === 'excel'">
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
            <path d="M28 6v10h10" />
            <rect x="14" y="24" width="20" height="14" rx="1" />
            <line x1="14" y1="31" x2="34" y2="31" />
            <line x1="24" y1="24" x2="24" y2="38" />
          </template>
          <!-- Video - clapperboard -->
          <template v-else-if="props.item.category === 'video'">
            <rect x="4" y="12" width="40" height="28" rx="3" fill="currentColor" opacity="0.15" />
            <rect x="4" y="12" width="40" height="28" rx="3" />
            <path d="M4 19h40" />
            <path d="M12 12l-5 7M22 12l-5 7M32 12l-5 7M42 12l-5 7" stroke-width="1.3" />
            <path d="M20 26v10l9-5-9-5z" fill="currentColor" opacity="0.4" />
          </template>
          <!-- Audio - speaker with waves -->
          <template v-else-if="props.item.category === 'audio'">
            <rect x="6" y="6" width="36" height="36" rx="3" fill="currentColor" opacity="0.15" />
            <polygon points="22 16 14 21 8 21 8 29 14 29 22 34 22 16" fill="currentColor" opacity="0.25" />
            <polygon points="22 16 14 21 8 21 8 29 14 29 22 34 22 16" />
            <path d="M28 20a6 6 0 010 10" stroke-width="2" />
            <path d="M32 16a12 12 0 010 18" stroke-width="2" />
          </template>
          <!-- Archive - package box -->
          <template v-else-if="props.item.category === 'archive'">
            <path d="M42 32V16a4 4 0 00-2-3.46l-14-8a4 4 0 00-4 0l-14 8A4 4 0 006 16v16a4 4 0 002 3.46l14 8a4 4 0 004 0l14-8A4 4 0 0042 32z" fill="currentColor" opacity="0.15" />
            <path d="M42 32V16a4 4 0 00-2-3.46l-14-8a4 4 0 00-4 0l-14 8A4 4 0 006 16v16a4 4 0 002 3.46l14 8a4 4 0 004 0l14-8A4 4 0 0042 32z" />
            <polyline points="6.5 13.9 24 24 41.5 13.9" />
            <line x1="24" y1="44.2" x2="24" y2="24" />
          </template>
          <!-- Image -->
          <template v-else-if="props.item.category === 'image'">
            <rect x="6" y="6" width="36" height="36" rx="3" fill="currentColor" opacity="0.15" />
            <rect x="6" y="6" width="36" height="36" rx="3" />
            <circle cx="16" cy="16" r="4" fill="currentColor" opacity="0.4" />
            <path d="M6 34l10-10 8 8 6-6 12 12H8a2 2 0 01-2-2v-2z" fill="currentColor" opacity="0.3" />
          </template>
          <!-- Generic document / Misc -->
          <template v-else>
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
            <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
            <path d="M28 6v10h10" />
            <line x1="16" y1="24" x2="32" y2="24" opacity="0.5" />
            <line x1="16" y1="30" x2="32" y2="30" opacity="0.5" />
            <line x1="16" y1="36" x2="26" y2="36" opacity="0.5" />
          </template>
        </svg>
      </div>

      <!-- Hover action bar -->
      <div
        class="absolute inset-x-0 bottom-0 flex flex-wrap items-center justify-center gap-0.5 px-1 py-1
               bg-gradient-to-t from-black/70 via-black/50 to-transparent
               opacity-0 group-hover/card:opacity-100 transition-opacity duration-150"
        @click.stop
      >
        <!-- Preview -->
        <button
          class="rfm-action-btn"
          :title="configStore.t('Preview')"
          @click="emit('preview', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" />
          </svg>
        </button>

        <!-- Edit Image -->
        <button
          v-if="config?.imageEditorActive && isEditableImage(props.item.extension)"
          class="rfm-action-btn"
          :title="configStore.t('Edit_image')"
          @click="emit('editImage', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
          </svg>
        </button>

        <!-- Download -->
        <button
          v-if="config?.downloadFiles"
          class="rfm-action-btn"
          :title="configStore.t('Download')"
          @click="emit('download', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
          </svg>
        </button>

        <!-- Rename -->
        <button
          v-if="config?.renameFiles"
          class="rfm-action-btn"
          :title="configStore.t('Rename')"
          @click="emit('rename', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
          </svg>
        </button>

        <!-- Duplicate -->
        <button
          v-if="config?.duplicateFiles"
          class="rfm-action-btn"
          :title="configStore.t('Duplicate')"
          @click="emit('duplicate', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
          </svg>
        </button>

        <!-- Delete -->
        <button
          v-if="config?.deleteFiles"
          class="rfm-action-btn !text-red-400 hover:!bg-red-500/30"
          :title="configStore.t('Delete')"
          @click="emit('delete', props.item)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
          </svg>
        </button>
      </div>

      <!-- Extension badge -->
      <span
        class="absolute top-1.5 right-1.5 px-1.5 py-0.5 text-[10px] font-bold uppercase leading-none
               rounded bg-black/50 text-white backdrop-blur-sm"
      >
        {{ props.item.extension }}
      </span>
    </div>

    <!-- File info -->
    <div class="px-2 py-1.5 min-w-0">
      <p
        class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate leading-tight"
        :title="props.item.name"
      >
        {{ props.item.name }}
      </p>
      <p class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500 truncate leading-tight">
        {{ formatFileSize(props.item.size) }}
        <template v-if="props.item.width && props.item.height">
          &middot; {{ props.item.width }}&times;{{ props.item.height }}
        </template>
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
