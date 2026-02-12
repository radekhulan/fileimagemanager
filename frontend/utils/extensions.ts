import type { FileCategory } from '@/types/files'

/** Map file category to a CSS color class */
export function getCategoryColor(category: FileCategory): string {
  return {
    image: 'text-green-600 dark:text-green-400',
    video: 'text-purple-600 dark:text-purple-400',
    audio: 'text-rose-600 dark:text-rose-400',
    document: 'text-gray-500 dark:text-gray-400',
    archive: 'text-amber-600 dark:text-amber-400',
    misc: 'text-gray-500 dark:text-gray-400',
    directory: 'text-amber-500 dark:text-amber-400',
  }[category]
}

/** Map file extension + category to an icon-specific CSS color class */
export function getIconColor(ext: string, category: FileCategory): string {
  const e = ext.toLowerCase()
  if (e === 'pdf') return 'text-red-500 dark:text-red-400'
  if (['doc', 'docx', 'odt', 'rtf'].includes(e)) return 'text-blue-600 dark:text-blue-400'
  if (['xls', 'xlsx', 'ods', 'csv'].includes(e)) return 'text-emerald-600 dark:text-emerald-400'
  if (['ppt', 'pptx', 'odp'].includes(e)) return 'text-orange-500 dark:text-orange-400'
  return getCategoryColor(category)
}

/** Return a file type key for icon selection */
export function getIconType(ext: string, category: FileCategory): string {
  const e = ext.toLowerCase()
  if (e === 'pdf') return 'pdf'
  if (['doc', 'docx', 'odt', 'rtf'].includes(e)) return 'word'
  if (['xls', 'xlsx', 'ods', 'csv'].includes(e)) return 'excel'
  if (['ppt', 'pptx', 'odp'].includes(e)) return 'powerpoint'
  return category
}

/** Map file category to an SVG icon name */
export function getCategoryIcon(category: FileCategory): string {
  return {
    image: 'icon-image',
    video: 'icon-media',
    audio: 'icon-media',
    document: 'icon-file',
    archive: 'icon-archive',
    misc: 'icon-file',
    directory: 'folder',
  }[category]
}

/** Check if extension is an editable image */
export function isEditableImage(ext: string): boolean {
  return ['jpg', 'jpeg', 'png', 'webp'].includes(ext.toLowerCase())
}

/** Check if extension is a previewable media */
export function isPreviewableMedia(ext: string, extVideo: string[], extMusic: string[]): boolean {
  const lower = ext.toLowerCase()
  return extVideo.includes(lower) || extMusic.includes(lower)
}
