<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useConfigStore } from '@/stores/configStore'
import { useFileStore } from '@/stores/fileStore'
import { operationsApi, filesApi } from '@/api/files'

const props = defineProps<{
  mode: 'create' | 'edit'
  path?: string // for edit mode
  currentDir?: string // for create mode
}>()

const emit = defineEmits<{ close: [] }>()

const configStore = useConfigStore()
const fileStore = useFileStore()
const { t } = configStore

const fileName = ref('')
const content = ref('')
const saving = ref(false)
const loading = ref(false)

onMounted(async () => {
  if (props.mode === 'edit' && props.path) {
    loading.value = true
    try {
      const result = await filesApi.getContent(props.path)
      content.value = result.content
      fileName.value = result.name
    } catch (err: any) {
      content.value = ''
    } finally {
      loading.value = false
    }
  }
})

async function onSave() {
  saving.value = true
  try {
    if (props.mode === 'edit' && props.path) {
      await operationsApi.saveText(props.path, content.value)
    } else {
      if (!fileName.value) return
      await operationsApi.createFile(props.currentDir || '', fileName.value, content.value)
    }
    await fileStore.refresh()
    emit('close')
  } catch (err: any) {
    alert(err?.response?.data?.error || t('Save_Failed'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
      <div class="fixed inset-0 bg-black/50" @click="$emit('close')" />
      <div class="relative bg-white dark:bg-neutral-800 rounded-xl shadow-2xl max-w-2xl w-full p-6 z-10 flex flex-col max-h-[80vh]">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
          {{ mode === 'create' ? t('New_File') : t('Edit_File') }}
        </h3>

        <!-- File name (create mode) -->
        <div v-if="mode === 'create'" class="mb-3">
          <input
            v-model="fileName"
            :placeholder="t('Filename')"
            class="w-full px-3 py-2 border border-gray-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-700 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-rfm-primary"
          />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ t('Valid_Extensions', configStore.config?.editableTextFileExts?.join(', ') || 'txt') }}
          </p>
        </div>

        <!-- File name (edit mode) -->
        <div v-else class="mb-3 text-sm text-gray-600 dark:text-gray-400">
          {{ fileName }}
        </div>

        <!-- Content editor -->
        <textarea
          v-model="content"
          :disabled="loading"
          class="flex-1 min-h-[200px] w-full px-3 py-2 border border-gray-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-700 text-gray-900 dark:text-gray-100 text-sm font-mono resize-none focus:outline-none focus:ring-2 focus:ring-rfm-primary"
          :placeholder="loading ? t('Loading') : ''"
        />

        <div class="flex justify-end gap-3 mt-4">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
          >
            {{ t('Cancel') }}
          </button>
          <button
            @click="onSave"
            :disabled="saving || (mode === 'create' && !fileName)"
            class="px-4 py-2 text-sm rounded-lg bg-rfm-primary hover:bg-rfm-primary-hover text-white transition-colors disabled:opacity-50"
          >
            {{ t('OK') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
