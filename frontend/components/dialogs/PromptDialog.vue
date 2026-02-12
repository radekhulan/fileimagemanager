<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { useUiStore } from '@/stores/uiStore'
import { useConfigStore } from '@/stores/configStore'

const ui = useUiStore()
const { t } = useConfigStore()
const inputValue = ref(ui.promptDialog.defaultValue)
const inputRef = ref<HTMLInputElement>()

onMounted(async () => {
  await nextTick()
  inputRef.value?.focus()
  inputRef.value?.select()
})

function onConfirm() {
  ui.promptDialog.onConfirm?.(inputValue.value)
}

function onCancel() {
  ui.promptDialog.visible = false
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') {
    onConfirm()
  } else if (e.key === 'Escape') {
    onCancel()
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="onCancel">
      <div class="fixed inset-0 bg-black/50" @click="onCancel" />
      <div class="relative bg-white dark:bg-neutral-800 rounded-xl shadow-2xl max-w-sm w-full p-6 z-10">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
          {{ ui.promptDialog.title }}
        </h3>
        <p v-if="ui.promptDialog.message" class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          {{ ui.promptDialog.message }}
        </p>
        <input
          ref="inputRef"
          v-model="inputValue"
          @keydown="onKeydown"
          class="w-full px-3 py-2 border border-gray-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-700 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-rfm-primary mb-6"
        />
        <div class="flex justify-end gap-3">
          <button
            @click="onCancel"
            class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
          >
            {{ t('Cancel') }}
          </button>
          <button
            @click="onConfirm"
            class="px-4 py-2 text-sm rounded-lg bg-rfm-primary hover:bg-rfm-primary-hover text-white transition-colors"
          >
            {{ t('OK') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
