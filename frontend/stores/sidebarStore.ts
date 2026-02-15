import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { TreeNode } from '@/types/files'
import { foldersApi } from '@/api/files'
import { useFileStore } from './fileStore'

const COOKIE_NAME = 'rfm_sidebar'
const COOKIE_HIDE_FOLDERS = 'rfm_hide_folders'

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'))
  return match ? decodeURIComponent(match[1]) : null
}

function setCookie(name: string, value: string, days: number = 365) {
  const expires = new Date(Date.now() + days * 864e5).toUTCString()
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`
}

export const useSidebarStore = defineStore('sidebar', () => {
  const tree = ref<TreeNode[]>([])
  const loaded = ref(false)

  // User preference from cookie (default: visible)
  const collapsed = ref(getCookie(COOKIE_NAME) === '0')

  const rootFolderCount = computed(() => tree.value.length)

  // Reactive desktop detection via matchMedia
  const mediaQuery = typeof window !== 'undefined'
    ? window.matchMedia('(min-width: 1024px)')
    : null
  const isDesktop = ref(mediaQuery?.matches ?? false)

  function onMediaChange(e: MediaQueryListEvent) {
    isDesktop.value = e.matches
  }

  mediaQuery?.addEventListener('change', onMediaChange)

  const visible = computed(() => rootFolderCount.value > 5 && isDesktop.value && !collapsed.value)

  function toggle() {
    collapsed.value = !collapsed.value
    setCookie(COOKIE_NAME, collapsed.value ? '0' : '1')
  }

  // Whether the toggle button should appear (enough folders + desktop)
  const canShow = computed(() => rootFolderCount.value > 5 && isDesktop.value)

  // "Files only" toggle — hide folders in main grid when sidebar is visible
  const hideFoldersInGrid = ref(getCookie(COOKIE_HIDE_FOLDERS) === '1')

  function toggleHideFolders() {
    hideFoldersInGrid.value = !hideFoldersInGrid.value
    setCookie(COOKIE_HIDE_FOLDERS, hideFoldersInGrid.value ? '1' : '0')
  }

  // Effective: only hide when sidebar is actually visible
  const effectiveHideFolders = computed(() => visible.value && hideFoldersInGrid.value)

  // Sync effectiveHideFolders → fileStore
  watch(effectiveHideFolders, (val) => {
    const fileStore = useFileStore()
    fileStore.externalHideFolders = val
    fileStore.rebuildSplit()
  }, { immediate: true })

  async function loadTree() {
    try {
      const response = await foldersApi.tree()
      tree.value = response.tree
      loaded.value = true
    } catch {
      // Sidebar is non-critical — fail silently
    }
  }

  async function refresh() {
    try {
      const response = await foldersApi.tree()
      tree.value = response.tree
    } catch {
      // fail silently
    }
  }

  return {
    tree,
    loaded,
    rootFolderCount,
    isDesktop,
    visible,
    canShow,
    collapsed,
    toggle,
    hideFoldersInGrid,
    toggleHideFolders,
    effectiveHideFolders,
    loadTree,
    refresh,
  }
})
