<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import type { ApiError } from '@/types'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const password = ref('')
const loading = ref(false)
const error = ref('')

onMounted(() => {
  // Ha nincs bejelentkezett felhasználó, a login oldalra irányítunk
  if (!authStore.token) {
    router.replace({ name: 'login' })
    return
  }
  // Ha nincs user adat betöltve (pl. oldal frissítés után), betöltjük
  if (!authStore.user) {
    authStore.fetchUser()
  }
})

async function handleUnlock() {
  error.value = ''
  loading.value = true

  try {
    await authStore.verifyPassword(password.value)
    // Sikeres feloldás - vissza a dashboardra vagy ahol volt
    router.push({ name: 'dashboard' })
  } catch (err: any) {
    const apiError = err.response?.data as ApiError
    if (apiError?.errors?.password) {
      error.value = apiError.errors.password[0]
    } else if (err.response?.status === 401) {
      // A token érvénytelen, teljes kijelentkezés
      authStore.clearAuth()
      router.push({ name: 'login' })
    } else {
      error.value = 'Hibás jelszó.'
    }
    password.value = ''
  } finally {
    loading.value = false
  }
}

async function handleSwitchAccount() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-8 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <div class="w-full max-w-sm">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 border border-gray-200 dark:border-gray-700 text-center">

        <!-- Lakat ikon -->
        <div class="inline-flex items-center justify-center w-14 h-14 bg-teal-100 dark:bg-teal-900/30 rounded-full mb-4">
          <svg class="w-7 h-7 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>

        <!-- Felhasználó avatar és név -->
        <div class="mb-6">
          <div class="w-20 h-20 bg-teal-600 rounded-full mx-auto mb-3 flex items-center justify-center">
            <span class="text-2xl font-bold text-white">{{ authStore.userInitials }}</span>
          </div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ authStore.userName }}</h2>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
          {{ t('auth.lockscreen_desc') }}
        </p>

        <form @submit.prevent="handleUnlock" class="space-y-4">
          <div class="relative">
            <input
              v-model="password"
              type="password"
              :placeholder="t('auth.password')"
              required
              autofocus
              autocomplete="current-password"
              :disabled="loading"
              class="block w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-colors text-sm text-center disabled:opacity-50"
            />
          </div>

          <div v-if="error" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <span class="text-sm text-red-700 dark:text-red-400">{{ error }}</span>
          </div>

          <button
            type="submit"
            :disabled="loading || !password"
            class="w-full py-2.5 px-4 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 disabled:cursor-not-allowed text-white font-medium rounded-lg shadow-sm hover:shadow-md focus:ring-4 focus:ring-teal-300 dark:focus:ring-teal-800 transition-all duration-200 text-sm flex items-center justify-center"
          >
            <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ loading ? t('common.loading') : t('auth.unlock') }}
          </button>
        </form>

        <div class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
          <button
            @click="handleSwitchAccount"
            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
          >
            {{ t('auth.other_account') }}
          </button>
        </div>
      </div>

      <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-6">
        {{ t('app.copyright') }}
      </p>
    </div>
  </div>
</template>
