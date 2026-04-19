import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

export type SupportedLocale = 'hu' | 'en'

export const SUPPORTED_LOCALES: { code: SupportedLocale; label: string; country: string }[] = [
  { code: 'hu', label: 'Magyar', country: 'hu' },
  { code: 'en', label: 'English', country: 'gb' },
]

/**
 * Nyelvkezelés: aktív locale getter/setter, localStorage + user settings sync.
 *
 * Stratégia:
 *   - Vendég: localStorage-ba mentjük
 *   - Bejelentkezett user: localStorage + a user_settings.locale-ba is
 *   - API kéréseknél az `Accept-Language` header mindig a localStorage-ból megy
 */
export function useLocale() {
  const { locale } = useI18n()
  const authStore = useAuthStore()

  const currentLocale = computed<SupportedLocale>(() => (locale.value as SupportedLocale))

  async function setLocale(newLocale: SupportedLocale): Promise<void> {
    // i18n + localStorage
    locale.value = newLocale
    localStorage.setItem('locale', newLocale)

    // Bejelentkezett user → user_settings perzisztálás
    if (authStore.isAuthenticated && !authStore.isLocked) {
      try {
        await api.put('/settings', { locale: newLocale })
        // Frissítsük az authStore-ban is a user settings-et
        if (authStore.user?.settings) {
          authStore.user.settings.locale = newLocale
        }
      } catch {
        /* silent: a locale frontenden már érvényes */
      }
    }
  }

  /**
   * Bejelentkezés után szinkronizálja a locale-t a backend user_settings.locale-lel,
   * ha az eltér a localStorage-étól.
   */
  function syncLocaleFromUser(): void {
    const userLocale = authStore.user?.settings?.locale as SupportedLocale | undefined
    if (userLocale && userLocale !== locale.value && isSupported(userLocale)) {
      locale.value = userLocale
      localStorage.setItem('locale', userLocale)
    }
  }

  function isSupported(l: string): l is SupportedLocale {
    return SUPPORTED_LOCALES.some((s) => s.code === l)
  }

  return {
    currentLocale,
    setLocale,
    syncLocaleFromUser,
    supportedLocales: SUPPORTED_LOCALES,
  }
}
