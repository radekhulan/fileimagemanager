<script setup lang="ts">
import { ref, computed } from 'vue'
import { useConfigStore } from '@/stores/configStore'
import { operationsApi } from '@/api/files'
import { useFileStore } from '@/stores/fileStore'

const props = defineProps<{
  path: string
  isDir: boolean
  currentPermissions?: string
}>()

const emit = defineEmits<{ close: [] }>()

const configStore = useConfigStore()
const fileStore = useFileStore()
const { t } = configStore

// Permission bits
const userRead = ref(true)
const userWrite = ref(true)
const userExec = ref(false)
const groupRead = ref(true)
const groupWrite = ref(false)
const groupExec = ref(false)
const otherRead = ref(true)
const otherWrite = ref(false)
const otherExec = ref(false)
const recursive = ref('none')
const saving = ref(false)

// Parse current permissions
if (props.currentPermissions) {
  const p = props.currentPermissions
  const u = parseInt(p[0] || '6', 10)
  const g = parseInt(p[1] || '4', 10)
  const o = parseInt(p[2] || '4', 10)
  userRead.value = !!(u & 4)
  userWrite.value = !!(u & 2)
  userExec.value = !!(u & 1)
  groupRead.value = !!(g & 4)
  groupWrite.value = !!(g & 2)
  groupExec.value = !!(g & 1)
  otherRead.value = !!(o & 4)
  otherWrite.value = !!(o & 2)
  otherExec.value = !!(o & 1)
}

const modeString = computed(() => {
  const u = (userRead.value ? 4 : 0) + (userWrite.value ? 2 : 0) + (userExec.value ? 1 : 0)
  const g = (groupRead.value ? 4 : 0) + (groupWrite.value ? 2 : 0) + (groupExec.value ? 1 : 0)
  const o = (otherRead.value ? 4 : 0) + (otherWrite.value ? 2 : 0) + (otherExec.value ? 1 : 0)
  return `${u}${g}${o}`
})

async function onSave() {
  saving.value = true
  try {
    await operationsApi.chmod(props.path, modeString.value, recursive.value)
    await fileStore.refresh()
    emit('close')
  } catch (err: any) {
    alert(err?.response?.data?.error || t('Chmod_Failed'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
      <div class="fixed inset-0 bg-black/50" @click="$emit('close')" />
      <div class="relative bg-white dark:bg-neutral-800 rounded-xl shadow-2xl max-w-md w-full p-6 z-10">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
          {{ t('File_Permission') }}
        </h3>

        <table class="w-full text-sm mb-4">
          <thead>
            <tr class="text-gray-500 dark:text-gray-400">
              <th class="text-left py-1"></th>
              <th class="py-1">{{ t('Chmod_Read') }}</th>
              <th class="py-1">{{ t('Chmod_Write') }}</th>
              <th class="py-1">{{ t('Chmod_Exec') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="py-1 font-medium text-gray-700 dark:text-gray-300">{{ t('User') }}</td>
              <td class="text-center py-1"><input type="checkbox" v-model="userRead" class="accent-rfm-primary" /></td>
              <td class="text-center py-1"><input type="checkbox" v-model="userWrite" class="accent-rfm-primary" /></td>
              <td class="text-center py-1"><input type="checkbox" v-model="userExec" class="accent-rfm-primary" /></td>
            </tr>
            <tr>
              <td class="py-1 font-medium text-gray-700 dark:text-gray-300">{{ t('Group') }}</td>
              <td class="text-center py-1"><input type="checkbox" v-model="groupRead" class="accent-rfm-primary" /></td>
              <td class="text-center py-1"><input type="checkbox" v-model="groupWrite" class="accent-rfm-primary" /></td>
              <td class="text-center py-1"><input type="checkbox" v-model="groupExec" class="accent-rfm-primary" /></td>
            </tr>
            <tr>
              <td class="py-1 font-medium text-gray-700 dark:text-gray-300">{{ t('Chmod_Other') }}</td>
              <td class="text-center py-1"><input type="checkbox" v-model="otherRead" class="accent-rfm-primary" /></td>
              <td class="text-center py-1"><input type="checkbox" v-model="otherWrite" class="accent-rfm-primary" /></td>
              <td class="text-center py-1"><input type="checkbox" v-model="otherExec" class="accent-rfm-primary" /></td>
            </tr>
          </tbody>
        </table>

        <div class="text-center text-lg font-mono mb-4 text-gray-900 dark:text-gray-100">
          {{ modeString }}
        </div>

        <div v-if="isDir" class="mb-4">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">
            {{ t('File_Permission_Recursive') }}
          </label>
          <select
            v-model="recursive"
            class="w-full px-3 py-2 border border-gray-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-700 text-sm text-gray-900 dark:text-gray-100"
          >
            <option value="none">{{ t('No') }}</option>
            <option value="files">{{ t('Chmod_Files_Only') }}</option>
            <option value="folders">{{ t('Chmod_Folders_Only') }}</option>
            <option value="both">{{ t('Chmod_Both') }}</option>
          </select>
        </div>

        <div class="flex justify-end gap-3">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
          >
            {{ t('Cancel') }}
          </button>
          <button
            @click="onSave"
            :disabled="saving"
            class="px-4 py-2 text-sm rounded-lg bg-rfm-primary hover:bg-rfm-primary-hover text-white transition-colors disabled:opacity-50"
          >
            {{ t('OK') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
