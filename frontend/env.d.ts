/// <reference types="vite/client" />

declare const __APP_VERSION__: string

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}

declare module 'react'
declare module 'react-dom'

declare module 'filerobot-image-editor' {
  const FilerobotImageEditor: any
  export default FilerobotImageEditor
  export const TABS: Record<string, string>
  export const TOOLS: Record<string, string>
}

declare module 'react-filerobot-image-editor/lib/utils/translator' {
  export function updateTranslations(translations?: Record<string, string>, language?: string): void
  export function translate(key: string): string
}
