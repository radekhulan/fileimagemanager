import apiClient from './client'
import type { FileListResponse, PreviewData } from '@/types/files'

export const filesApi = {
  async list(params: {
    path?: string
    sort_by?: string
    descending?: string
    filter?: string
    type_filter?: string
  }): Promise<FileListResponse> {
    const { data } = await apiClient.get('/files', { params })
    return data
  },

  async info(path: string) {
    const { data } = await apiClient.get('/files/info', { params: { path } })
    return data
  },

  async preview(path: string): Promise<PreviewData> {
    const { data } = await apiClient.get('/files/preview', { params: { path } })
    return data
  },

  async getContent(path: string): Promise<{ content: string; name: string; extension: string }> {
    const { data } = await apiClient.get('/files/content', { params: { path } })
    return data
  },

  getDownloadUrl(path: string): string {
    const basePath = window.location.pathname
    const base = basePath.endsWith('/') ? basePath : basePath.substring(0, basePath.lastIndexOf('/') + 1)
    return `${base}api/files/download?path=${encodeURIComponent(path)}`
  },
}

export const foldersApi = {
  async create(path: string, name: string) {
    const { data } = await apiClient.post('/folders/create', { path, name })
    return data
  },

  async rename(path: string, name: string) {
    const { data } = await apiClient.post('/folders/rename', { path, name })
    return data
  },

  async delete(path: string) {
    const { data } = await apiClient.post('/folders/delete', { path })
    return data
  },
}

export const operationsApi = {
  async rename(path: string, name: string) {
    const { data } = await apiClient.post('/operations/rename', { path, name })
    return data
  },

  async delete(path: string) {
    const { data } = await apiClient.post('/operations/delete', { path })
    return data
  },

  async deleteBulk(paths: string[]) {
    const { data } = await apiClient.post('/operations/delete-bulk', { paths })
    return data
  },

  async duplicate(path: string, name?: string) {
    const { data } = await apiClient.post('/operations/duplicate', { path, name })
    return data
  },

  async copy(params: { path?: string; paths?: string[] }) {
    const { data } = await apiClient.post('/operations/copy', params)
    return data
  },

  async cut(params: { path?: string; paths?: string[] }) {
    const { data } = await apiClient.post('/operations/cut', params)
    return data
  },

  async paste(path: string) {
    const { data } = await apiClient.post('/operations/paste', { path })
    return data
  },

  async clearClipboard() {
    const { data } = await apiClient.post('/operations/clear-clipboard')
    return data
  },

  async chmod(path: string, mode: string, recursive: string = 'none') {
    const { data } = await apiClient.post('/operations/chmod', { path, mode, recursive })
    return data
  },

  async extract(path: string) {
    const { data } = await apiClient.post('/operations/extract', { path })
    return data
  },

  async saveText(path: string, content: string) {
    const { data } = await apiClient.post('/operations/save-text', { path, content })
    return data
  },

  async createFile(path: string, name: string, content: string = '') {
    const { data } = await apiClient.post('/operations/create-file', { path, name, content })
    return data
  },
}

export const imageApi = {
  async saveEdited(path: string, imageData: string, name: string) {
    const { data } = await apiClient.post('/image/save', { path, image_data: imageData, name })
    return data
  },
}
