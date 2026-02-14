<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps<{
  src: string
  alt: string
}>()

const imgRef = ref<HTMLImageElement | null>(null)
const isVisible = ref(false)
const isLoaded = ref(false)
const hasError = ref(false)

function onLoad() {
  isLoaded.value = true
}

function onError() {
  hasError.value = true
  isLoaded.value = true
}

// Shared IntersectionObserver — one instance for all Thumbnail components
const callbacks = new WeakMap<Element, () => void>()
let shared: IntersectionObserver | null = null

function getSharedObserver(): IntersectionObserver {
  if (!shared) {
    shared = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            const cb = callbacks.get(entry.target)
            if (cb) {
              cb()
              callbacks.delete(entry.target)
              shared!.unobserve(entry.target)
            }
          }
        }
      },
      { rootMargin: '200px' }
    )
  }
  return shared
}

onMounted(() => {
  if (!imgRef.value) return
  const el = imgRef.value
  callbacks.set(el, () => { isVisible.value = true })
  getSharedObserver().observe(el)
})

onUnmounted(() => {
  if (!imgRef.value) return
  callbacks.delete(imgRef.value)
  getSharedObserver().unobserve(imgRef.value)
})
</script>

<template>
  <div ref="imgRef" class="relative w-full h-full">
    <!-- Placeholder shown until image loads -->
    <div
      v-if="!isLoaded"
      class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-800"
      :class="{ 'animate-pulse': isVisible }"
    >
      <svg
        class="w-8 h-8 text-gray-300 dark:text-gray-600"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
      >
        <rect x="3" y="3" width="18" height="18" rx="2" />
        <circle cx="8.5" cy="8.5" r="1.5" />
        <path d="M21 15l-5-5L5 21" />
      </svg>
    </div>

    <!-- Actual image (only loads src when visible) -->
    <img
      v-if="isVisible && !hasError"
      :src="props.src"
      :alt="props.alt"
      class="object-cover w-full h-full transition-opacity duration-200"
      :class="isLoaded ? 'opacity-100' : 'opacity-0'"
      loading="lazy"
      @load="onLoad"
      @error="onError"
    />

    <!-- Error fallback -->
    <div
      v-if="hasError"
      class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-800"
    >
      <svg
        class="w-8 h-8 text-gray-400 dark:text-gray-500"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
      >
        <rect x="3" y="3" width="18" height="18" rx="2" />
        <line x1="9" y1="9" x2="15" y2="15" />
        <line x1="15" y1="9" x2="9" y2="15" />
      </svg>
    </div>
  </div>
</template>
