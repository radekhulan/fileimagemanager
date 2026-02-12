<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useConfigStore } from '@/stores/configStore'
import { useUiStore } from '@/stores/uiStore'
import { configApi } from '@/api/config'

const configStore = useConfigStore()
const ui = useUiStore()
const { t } = configStore

const languages = ref<{ code: string; name: string }[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const result = await configApi.getLanguages()
    languages.value = result
  } catch {
    languages.value = []
  } finally {
    loading.value = false
  }
})

async function onSelect(lang: string) {
  await configStore.changeLanguage(lang)
  ui.showLanguageDialog = false
}

function onClose() {
  ui.showLanguageDialog = false
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="onClose">
      <div class="fixed inset-0 bg-black/50" @click="onClose" />
      <div class="relative bg-white dark:bg-neutral-800 rounded-xl shadow-2xl max-w-sm w-full p-6 z-10">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
          {{ t('Lang_Change') }}
        </h3>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-8">
          <svg class="w-6 h-6 text-rfm-primary rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
          </svg>
        </div>

        <!-- Language list -->
        <div v-else class="space-y-1 max-h-[50vh] overflow-y-auto">
          <button
            v-for="lang in languages"
            :key="lang.code"
            @click="onSelect(lang.code)"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
            :class="lang.code === configStore.language
              ? 'bg-rfm-primary/10 text-rfm-primary font-medium'
              : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-700'"
          >
            <span class="flex-1 text-left">{{ lang.name }}</span>
            <svg
              v-if="lang.code === configStore.language"
              class="w-4 h-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </button>
        </div>

        <div class="flex justify-end mt-4">
          <button
            @click="onClose"
            class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
          >
            {{ t('Cancel') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
