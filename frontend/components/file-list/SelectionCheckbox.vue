<script setup lang="ts">
defineProps<{
  checked: boolean
}>()

const emit = defineEmits<{
  change: [value: boolean]
}>()

function toggle(event: Event) {
  event.stopPropagation()
  emit('change', !(event.target as HTMLInputElement).checked ? false : true)
}
</script>

<template>
  <div
    class="absolute top-1 left-1 z-10 transition-opacity"
    :class="checked ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
  >
    <input
      type="checkbox"
      :checked="checked"
      class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-rfm-primary
             focus:ring-rfm-primary focus:ring-offset-0 cursor-pointer
             bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm"
      @change="toggle"
      @click.stop
    />
  </div>
</template>
