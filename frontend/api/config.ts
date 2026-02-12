import apiClient from './client'
import type { SessionInitResponse, LanguageInfo } from '@/types/config'

export const configApi = {
  async initSession(akey?: string): Promise<SessionInitResponse> {
    const params = akey ? { akey } : {}
    const { data } = await apiClient.get('/session/init', { params })
    return data
  },

  async getConfig() {
    const { data } = await apiClient.get('/config')
    return data.config
  },

  async getLanguages(): Promise<LanguageInfo[]> {
    const { data } = await apiClient.get('/languages')
    return data.languages
  },

  async getTranslations(lang?: string): Promise<Record<string, string>> {
    const params = lang ? { lang } : {}
    const { data } = await apiClient.get('/translations', { params })
    return data.translations
  },

  async changeLanguage(lang: string) {
    const { data } = await apiClient.post('/config/language', { lang })
    return data
  },

  async changeView(type: number) {
    const { data } = await apiClient.post('/config/view', { type })
    return data
  },

  async changeSort(sortBy: string, descending: boolean) {
    const { data } = await apiClient.post('/config/sort', { sort_by: sortBy, descending })
    return data
  },

  async changeFilter(filter: string) {
    const { data } = await apiClient.post('/config/filter', { filter })
    return data
  },
}
