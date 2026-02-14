import { defineStore } from 'pinia'
import { ref, shallowRef, computed } from 'vue'
import type { FileItem, BreadcrumbItem, SortField, TypeFilter, ClipboardState } from '@/types/files'
import { filesApi } from '@/api/files'
import { configApi } from '@/api/config'
import { useConfigStore } from './configStore'

const LAST_PATH_COOKIE = 'rfm_last_path'

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'))
  return match ? decodeURIComponent(match[1]) : null
}

function setCookie(name: string, value: string, days: number = 365) {
  const expires = new Date(Date.now() + days * 864e5).toUTCString()
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`
}

export const useFileStore = defineStore('files', () => {
  // State
  const items = shallowRef<FileItem[]>([])
  const currentPath = ref('')
  const breadcrumb = ref<BreadcrumbItem[]>([])
  const selectedItems = ref<Set<string>>(new Set())
  const sortBy = ref<SortField>('name')
  const descending = ref(false)
  const textFilter = ref('')
  const typeFilter = ref<TypeFilter>('all')
  const loading = ref(false)
  const loadingMore = ref(false)
  const totalItems = ref(0)
  const fileCount = ref(0)
  const folderCount = ref(0)
  const totalSize = ref(0)
  const clipboard = ref<ClipboardState>({ hasItems: false, action: null })

  // Pre-split arrays — updated directly during loading to avoid repeated filtering
  const folders = shallowRef<FileItem[]>([])
  const files = shallowRef<FileItem[]>([])

  function shouldShowFolders(): boolean {
    if (typeFilter.value === 'all') return true
    const configStore = useConfigStore()
    return !!configStore.forceTypeFilter
  }

  /** Split items into folders/files arrays. Called once after full load or reset. */
  function rebuildSplit() {
    if (shouldShowFolders()) {
      folders.value = items.value.filter(i => i.isDir)
    } else {
      folders.value = []
    }
    files.value = items.value.filter(i => !i.isDir)
  }

  /** Append a chunk of items to the pre-split arrays (avoids re-filtering entire list). */
  function appendChunk(chunk: FileItem[]) {
    const showFolders = shouldShowFolders()
    const newFolders: FileItem[] = []
    const newFiles: FileItem[] = []
    for (const item of chunk) {
      if (item.isDir) {
        if (showFolders) newFolders.push(item)
      } else {
        newFiles.push(item)
      }
    }
    items.value = [...items.value, ...chunk]
    if (newFolders.length) folders.value = [...folders.value, ...newFolders]
    if (newFiles.length) files.value = [...files.value, ...newFiles]
  }
  const hasSelection = computed(() => selectedItems.value.size > 0)
  const selectionCount = computed(() => selectedItems.value.size)
  const selectedFiles = computed(() =>
    items.value.filter(i => selectedItems.value.has(i.path))
  )
  const parentPath = computed(() => {
    if (!currentPath.value) return null
    const parts = currentPath.value.replace(/\/$/, '').split('/')
    parts.pop()
    return parts.length > 0 ? parts.join('/') + '/' : ''
  })

  // Actions
  function getLastPath(): string {
    return getCookie(LAST_PATH_COOKIE) || ''
  }

  const loadError = ref<string | null>(null)
  // Incremented each time loadDirectory is called; background fetches check this to abort if stale.
  let loadGeneration = 0

  const FIRST_PAGE = 100
  const NEXT_PAGE = 500

  async function loadDirectory(path?: string) {
    if (path !== undefined) {
      currentPath.value = path
    }
    loading.value = true
    loadingMore.value = false
    loadError.value = null
    const generation = ++loadGeneration

    try {
      const baseParams = {
        path: currentPath.value,
        sort_by: sortBy.value,
        descending: descending.value ? '1' : '0',
        filter: textFilter.value || undefined,
        type_filter: typeFilter.value !== 'all' ? typeFilter.value : undefined,
      }

      // First page — show immediately
      const response = await filesApi.list({ ...baseParams, limit: FIRST_PAGE, offset: 0 })

      if (generation !== loadGeneration) return // navigation changed

      items.value = response.items
      rebuildSplit()
      breadcrumb.value = response.breadcrumb
      fileCount.value = response.counts.files
      folderCount.value = response.counts.folders
      totalSize.value = response.totalSize
      totalItems.value = response.total
      clipboard.value = response.clipboard
      selectedItems.value.clear()
      setCookie(LAST_PATH_COOKIE, currentPath.value)

      loading.value = false

      // Fetch remaining pages in background
      if (response.total > response.items.length) {
        loadingMore.value = true
        let offset = response.items.length
        const accumulated: FileItem[] = []

        while (offset < response.total) {
          if (generation !== loadGeneration) return

          const page = await filesApi.list({ ...baseParams, limit: NEXT_PAGE, offset })

          if (generation !== loadGeneration) return

          accumulated.push(...page.items)
          offset += page.items.length

          // No more items from server
          if (page.items.length === 0) break
        }

        if (generation !== loadGeneration) return
        // Add items in small chunks so the browser can paint between them
        const CHUNK = 50
        for (let i = 0; i < accumulated.length; i += CHUNK) {
          if (generation !== loadGeneration) return
          appendChunk(accumulated.slice(i, i + CHUNK))
          // Yield to browser between chunks so scrolling stays responsive
          if (i + CHUNK < accumulated.length) {
            await new Promise(r => requestAnimationFrame(r))
          }
        }
        loadingMore.value = false
      }
    } catch (err: any) {
      if (generation !== loadGeneration) return
      const configStore = useConfigStore()
      loadError.value = err?.response?.data?.error || err?.message || configStore.t('Load_Dir_Failed')
      items.value = []
      folders.value = []
      files.value = []
      fileCount.value = 0
      folderCount.value = 0
      totalSize.value = 0
      totalItems.value = 0
      console.error('loadDirectory failed:', loadError.value)
    } finally {
      if (generation === loadGeneration) {
        loading.value = false
        loadingMore.value = false
      }
    }
  }

  function navigate(path: string) {
    return loadDirectory(path)
  }

  function goUp() {
    if (parentPath.value !== null) {
      return navigate(parentPath.value)
    }
  }

  function toggleSelection(path: string) {
    const newSet = new Set(selectedItems.value)
    if (newSet.has(path)) {
      newSet.delete(path)
    } else {
      newSet.add(path)
    }
    selectedItems.value = newSet
  }

  function selectAll() {
    const newSet = new Set<string>()
    items.value.forEach(i => {
      if (!i.isDir) newSet.add(i.path)
    })
    selectedItems.value = newSet
  }

  function deselectAll() {
    selectedItems.value = new Set()
  }

  async function changeSort(field: SortField) {
    if (sortBy.value === field) {
      descending.value = !descending.value
    } else {
      sortBy.value = field
      descending.value = false
    }
    await configApi.changeSort(sortBy.value, descending.value)
    await loadDirectory()
  }

  type SortPreset = 'name' | 'newest' | 'oldest' | 'largest' | 'smallest'

  const sortPreset = computed<SortPreset>(() => {
    if (sortBy.value === 'name') return 'name'
    if (sortBy.value === 'date') return descending.value ? 'newest' : 'oldest'
    if (sortBy.value === 'size') return descending.value ? 'largest' : 'smallest'
    return 'name'
  })

  async function setSortPreset(preset: SortPreset) {
    const map: Record<SortPreset, { field: SortField; desc: boolean }> = {
      name: { field: 'name', desc: false },
      newest: { field: 'date', desc: true },
      oldest: { field: 'date', desc: false },
      largest: { field: 'size', desc: true },
      smallest: { field: 'size', desc: false },
    }
    const { field, desc } = map[preset]
    sortBy.value = field
    descending.value = desc
    await configApi.changeSort(sortBy.value, descending.value)
    await loadDirectory()
  }

  async function changeTypeFilter(filter: TypeFilter) {
    typeFilter.value = filter
    await loadDirectory()
  }

  function setTextFilter(filter: string) {
    textFilter.value = filter
  }

  async function refresh() {
    await loadDirectory()
  }

  return {
    // State
    items, currentPath, breadcrumb, selectedItems, sortBy,
    descending, textFilter, typeFilter, loading, loadingMore, loadError, fileCount,
    folderCount, totalSize, totalItems, clipboard,
    // Computed
    folders, files, hasSelection, selectionCount, selectedFiles, parentPath,
    // Actions
    getLastPath, loadDirectory, navigate, goUp, toggleSelection, selectAll, deselectAll,
    changeSort, changeTypeFilter, setTextFilter, refresh, sortPreset, setSortPreset,
  }
})
