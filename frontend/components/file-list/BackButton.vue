<script setup lang="ts">
const emit = defineEmits<{
  click: []
}>()
</script>

<template>
  <li
    class="flex flex-col rounded-lg border border-dashed border-gray-300 dark:border-neutral-600
           bg-gray-50 dark:bg-neutral-800/50 overflow-hidden cursor-pointer
           hover:border-gray-400 dark:hover:border-neutral-500 hover:bg-gray-100 dark:hover:bg-neutral-800
           transition-all duration-150"
    @click="emit('click')"
  >
    <!-- Icon area -->
    <div class="aspect-[4/3] flex items-center justify-center">
      <svg
        class="w-24 h-24 text-gray-400 dark:text-gray-500"
        viewBox="0 0 48 48"
        fill="currentColor"
      >
        <path
          d="M6 10c0-1.1.9-2 2-2h10l4 4h18c1.1 0 2 .9 2 2v24c0 1.1-.9 2-2 2H8c-1.1 0-2-.9-2-2V10z"
          opacity="0.6"
        />
        <path
          d="M26 22H16m0 0l5 5m-5-5l5-5"
          fill="none"
          stroke="white"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </div>

    <!-- Label -->
    <div class="px-2.5 py-2">
      <p class="text-xs font-medium text-gray-500 dark:text-gray-400 leading-tight">..</p>
    </div>
  </li>
</template>
