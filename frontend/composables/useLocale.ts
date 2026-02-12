import { computed } from 'vue'
import { useConfigStore } from '@/stores/configStore'

export function useLocale() {
  const configStore = useConfigStore()

  const currentLanguage = computed(() => configStore.language)

  function t(key: string, ...args: (string | number)[]): string {
    return configStore.t(key, ...args)
  }

  async function changeLanguage(lang: string) {
    await configStore.changeLanguage(lang)
  }

  return {
    currentLanguage,
    t,
    changeLanguage,
  }
}
