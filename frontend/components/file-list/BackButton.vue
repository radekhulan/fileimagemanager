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
    <div class="aspect-[4/3] flex items-center justify-center p-3.5">
      <svg
        class="w-full h-full text-gray-400 dark:text-gray-500"
        viewBox="0 0 48 40"
        fill="none"
        preserveAspectRatio="xMidYMid meet"
      >
        <!-- Folder body -->
        <path
          d="M2 10c0-1.1.9-2 2-2h12l4 4h22c1.1 0 2 .9 2 2v22c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V10z"
          fill="currentColor"
          opacity="0.5"
        />
        <!-- Folder front face -->
        <path
          d="M0 16c0-1.1.9-2 2-2h44c1.1 0 2 .9 2 2v20c0 1.1-.9 2-2 2H2c-1.1 0-2-.9-2-2V16z"
          fill="currentColor"
          opacity="0.8"
        />
        <!-- Arrow (centered on front face 16–38, midY=27) -->
        <path
          d="M30 25.5H19.5l4.3-4.3a1.2 1.2 0 10-1.7-1.7l-6.4 6.4a1.2 1.2 0 000 1.7l6.4 6.4a1.2 1.2 0 001.7-1.7L19.5 28H30a1.25 1.25 0 100-2.5z"
          fill="white"
          opacity="0.9"
        />
      </svg>
    </div>

    <!-- Label -->
    <div class="px-2.5 py-2">
      <p class="text-xs font-medium text-gray-500 dark:text-gray-400 leading-tight">..</p>
    </div>
  </li>
</template>
