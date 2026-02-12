<script setup lang="ts">
import { useUiStore } from '@/stores/uiStore'

const ui = useUiStore()

function onClose() {
  ui.alertDialog.visible = false
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="onClose">
      <div class="fixed inset-0 bg-black/50" @click="onClose" />
      <div class="relative bg-white dark:bg-neutral-800 rounded-xl shadow-2xl max-w-sm w-full p-6 z-10">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
          {{ ui.alertDialog.title }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
          {{ ui.alertDialog.message }}
        </p>
        <div class="flex justify-end">
          <button
            @click="onClose"
            class="px-4 py-2 text-sm rounded-lg bg-rfm-primary hover:bg-rfm-primary-hover text-white transition-colors"
          >
            OK
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
