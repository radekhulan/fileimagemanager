<script setup lang="ts">
import { useUiStore } from '@/stores/uiStore'
import { useConfigStore } from '@/stores/configStore'

const ui = useUiStore()
const { t } = useConfigStore()

function onConfirm() {
  ui.confirmDialog.onConfirm?.()
}

function onCancel() {
  ui.confirmDialog.visible = false
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="onCancel">
      <div class="fixed inset-0 bg-black/50" @click="onCancel" />
      <div class="relative bg-white dark:bg-neutral-800 rounded-xl shadow-2xl max-w-sm w-full p-6 z-10">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
          {{ ui.confirmDialog.title }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
          {{ ui.confirmDialog.message }}
        </p>
        <div class="flex justify-end gap-3">
          <button
            @click="onCancel"
            class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
          >
            {{ t('Cancel') }}
          </button>
          <button
            @click="onConfirm"
            class="px-4 py-2 text-sm rounded-lg bg-rfm-danger hover:bg-rfm-danger-hover text-white transition-colors"
          >
            {{ t('OK') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
