<script setup lang="ts">
import { computed } from 'vue'
import { useUiStore } from '@/stores/uiStore'
import { useConfigStore } from '@/stores/configStore'
import ImagePreview from './ImagePreview.vue'
import MediaPlayer from './MediaPlayer.vue'
import TextPreview from './TextPreview.vue'
import GoogleDocPreview from './GoogleDocPreview.vue'

const ui = useUiStore()
const configStore = useConfigStore()
const { t } = configStore

const preview = computed(() => ui.previewItem)

function onClose() {
  ui.previewItem = null
}

function onBackdropClick(e: MouseEvent) {
  if ((e.target as HTMLElement).classList.contains('preview-backdrop')) {
    onClose()
  }
}

function onDownload() {
  if (!preview.value) return
  const a = document.createElement('a')
  a.href = `/api/files/download?path=${encodeURIComponent(preview.value.path)}`
  a.download = preview.value.name
  a.click()
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="preview"
        class="preview-backdrop fixed inset-0 z-50 flex flex-col bg-black/80 backdrop-blur-sm"
        @click="onBackdropClick"
        @keydown.escape="onClose"
        tabindex="0"
      >
        <!-- Top bar -->
        <div class="flex items-center justify-between px-4 py-2 bg-black/40">
          <span class="text-sm text-white/80 truncate max-w-xs sm:max-w-md">
            {{ preview.name }}
          </span>
          <div class="flex items-center gap-2">
            <!-- Download -->
            <button
              v-if="configStore.config?.downloadFiles"
              @click="onDownload"
              class="p-2 rounded-lg hover:bg-white/10 text-white/70 hover:text-white transition-colors"
              :title="t('Download')"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
              </svg>
            </button>
            <!-- Close -->
            <button
              @click="onClose"
              class="p-2 rounded-lg hover:bg-white/10 text-white/70 hover:text-white transition-colors"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-auto">
          <ImagePreview
            v-if="preview.type === 'image'"
            :url="preview.url"
            :name="preview.name"
            @close="onClose"
          />
          <MediaPlayer
            v-else-if="preview.type === 'video' || preview.type === 'audio'"
            :url="preview.url"
            :name="preview.name"
            :type="preview.type"
            @close="onClose"
          />
          <TextPreview
            v-else-if="preview.type === 'text'"
            :path="preview.path"
            :name="preview.name"
            @close="onClose"
          />
          <div
            v-else-if="preview.type === 'pdf'"
            class="flex flex-col w-full h-full p-4"
          >
            <iframe
              :src="preview.url"
              class="flex-1 w-full rounded-lg border border-gray-200 dark:border-neutral-700 bg-white"
            />
          </div>
          <GoogleDocPreview
            v-else-if="preview.type === 'googledoc'"
            :url="preview.url"
            :name="preview.name"
            @close="onClose"
          />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
