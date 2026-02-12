/// <reference types="vite/client" />

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
