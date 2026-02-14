export interface FileItem {
  name: string
  path: string
  isDir: boolean
  size: number
  modifiedAt: number
  extension: string
  category: FileCategory
  thumbnailUrl: string | null
  width: number | null
  height: number | null
  permissions: string | null
}

export type FileCategory = 'image' | 'video' | 'audio' | 'document' | 'archive' | 'misc' | 'directory'

export type SortField = 'name' | 'date' | 'size' | 'extension'

export type ViewMode = 0 | 1 | 2 // 0=grid, 1=list, 2=columns

export type TypeFilter = 'all' | 'image' | 'video' | 'audio' | 'file' | 'archive'

export interface BreadcrumbItem {
  name: string
  path: string
}

export interface ClipboardState {
  hasItems: boolean
  action: 'copy' | 'cut' | null
  count?: number
}

export interface FileListResponse {
  path: string
  items: FileItem[]
  breadcrumb: BreadcrumbItem[]
  counts: {
    files: number
    folders: number
  }
  totalSize: number
  total: number
  clipboard: ClipboardState
}

export interface UploadResult {
  name: string
  path: string
  size: number
  type: string
}

export interface PreviewData {
  type: 'text' | 'image' | 'video' | 'audio' | 'pdf' | 'googledoc' | 'unsupported'
  url?: string
  content?: string
  extension?: string
  message?: string
}
