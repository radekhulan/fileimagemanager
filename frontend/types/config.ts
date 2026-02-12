export interface AppConfig {
  autoUpload: boolean
  uploadFiles: boolean
  createFolders: boolean
  deleteFolders: boolean
  deleteFiles: boolean
  renameFiles: boolean
  renameFolders: boolean
  duplicateFiles: boolean
  copyCutFiles: boolean
  copyCutDirs: boolean
  chmodFiles: boolean
  chmodDirs: boolean
  extractFiles: boolean
  previewTextFiles: boolean
  editTextFiles: boolean
  createTextFiles: boolean
  downloadFiles: boolean
  urlUpload: boolean
  multipleSelection: boolean
  multipleSelectionActionButton: boolean
  showTotalSize: boolean
  showFolderSize: boolean
  showSortingBar: boolean
  showFilterButtons: boolean
  showLanguageSelection: boolean
  imageEditorActive: boolean
  darkMode: boolean
  removeHeader: boolean
  maxSizeUpload: number
  fileNumberLimitJs: number
  extImg: string[]
  extVideo: string[]
  extMusic: string[]
  extFile: string[]
  extMisc: string[]
  baseUrl: string
  uploadDir: string
  editableTextFileExts: string[]
  previewableTextFileExts: string[]
  addTimeToImg: boolean
  copyCutMaxSize: number | false
  copyCutMaxCount: number | false
  googledocEnabled: boolean
  googledocFileExts: string[]
  defaultView: number
}

export interface SessionInitResponse {
  csrfToken: string
  config: AppConfig
  language: string
  translations: Record<string, string>
}

export interface LanguageInfo {
  code: string
  name: string
}
