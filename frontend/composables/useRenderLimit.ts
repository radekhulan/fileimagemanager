import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const INITIAL_LIMIT = 100
const INCREMENT = 200

/**
 * Progressively renders items by limiting how many VNodes Vue creates.
 * Uses an IntersectionObserver on a sentinel element to expand the window on scroll.
 */
export function useRenderLimit(
  getFolders: () => any[],
  getFiles: () => any[],
  /** Getter that returns a value changing on directory switch (used to reset limit) */
  getResetTrigger: () => any,
) {
  const renderLimit = ref(INITIAL_LIMIT)
  const sentinelRef = ref<HTMLElement | null>(null)
  let observer: IntersectionObserver | null = null

  // Reset limit when directory changes
  watch(getResetTrigger, () => {
    renderLimit.value = INITIAL_LIMIT
  })

  const visibleFolders = computed(() => {
    const f = getFolders()
    if (f.length <= renderLimit.value) return f
    return f.slice(0, renderLimit.value)
  })

  const visibleFiles = computed(() => {
    const f = getFolders()
    const remaining = renderLimit.value - f.length
    if (remaining <= 0) return []
    const fi = getFiles()
    if (fi.length <= remaining) return fi
    return fi.slice(0, remaining)
  })

  const allRendered = computed(() =>
    getFolders().length + getFiles().length <= renderLimit.value
  )

  function expand() {
    if (!allRendered.value) {
      renderLimit.value += INCREMENT
    }
  }

  onMounted(() => {
    observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) expand()
      },
      { rootMargin: '400px' },
    )
    if (sentinelRef.value) observer.observe(sentinelRef.value)

    watch(sentinelRef, (el, oldEl) => {
      if (oldEl) observer?.unobserve(oldEl)
      if (el) observer?.observe(el)
    })
  })

  onUnmounted(() => {
    observer?.disconnect()
  })

  return { visibleFolders, visibleFiles, allRendered, sentinelRef }
}
