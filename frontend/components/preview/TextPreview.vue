<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { filesApi } from '@/api/files'
import { useConfigStore } from '@/stores/configStore'

const props = defineProps<{
  path: string
  name: string
}>()

defineEmits<{ close: [] }>()

const configStore = useConfigStore()
const { t } = configStore

const content = ref('')
const loading = ref(true)
const error = ref(false)

onMounted(async () => {
  try {
    const result = await filesApi.getContent(props.path)
    content.value = result.content
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="flex flex-col w-full h-full p-4 max-w-3xl mx-auto">
    <h3 class="text-sm font-medium text-gray-300 dark:text-gray-500 mb-3 truncate">{{ name }}</h3>

    <!-- Loading -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <svg class="w-8 h-8 text-rfm-primary rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
      </svg>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex-1 flex items-center justify-center text-gray-500 dark:text-gray-400">
      <p>{{ t('Text_Load_Failed') }}</p>
    </div>

    <!-- Content -->
    <pre
      v-else
      class="flex-1 overflow-auto p-4 bg-gray-900 dark:bg-black rounded-lg text-sm text-gray-100 font-mono whitespace-pre-wrap break-words"
    >{{ content }}</pre>
  </div>
</template>
