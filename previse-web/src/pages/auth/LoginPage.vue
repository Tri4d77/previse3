<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import type { ApiError } from '@/types'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const error = ref('')
const loading = ref(false)

// 2FA challenge state
const twoFactorMode = ref(false)
const twoFactorCode = ref('')
const useRecoveryCode = ref(false)
const recoveryCode = ref('')

async function handleLogin() {
  error.value = ''
  loading.value = true

  try {
    const response = await authStore.login({ email: email.value, password: password.value })

    if ('requires_two_factor' in response) {
      // 2FA challenge szükséges → átváltunk 2FA módba
      twoFactorMode.value = true
    } else if ('requires_organization_selection' in response) {
      router.push({ name: 'select-organization' })
    } else {
      router.push({ name: 'dashboard' })
    }
  } catch (err: any) {
    const apiError = err.response?.data as ApiError
    if (apiError?.errors?.email) {
      error.value = apiError.errors.email[0]
    } else if (apiError?.message) {
      error.value = apiError.message
    } else {
      error.value = 'Hiba történt a bejelentkezés során.'
    }
  } finally {
    loading.value = false
  }
}

async function handleVerifyTwoFactor() {
  error.value = ''
  loading.value = true

  try {
    const params = useRecoveryCode.value
      ? { recoveryCode: recoveryCode.value.trim() }
      : { code: twoFactorCode.value.trim() }

    const response = await authStore.verifyTwoFactor(params)

    if ('requires_organization_selection' in response) {
      router.push({ name: 'select-organization' })
    } else {
      router.push({ name: 'dashboard' })
    }
  } catch (err: any) {
    const apiError = err.response?.data as ApiError
    error.value = apiError?.errors?.code?.[0] ?? apiError?.message ?? 'Érvénytelen kód.'
  } finally {
    loading.value = false
  }
}

function cancelTwoFactor() {
  twoFactorMode.value = false
  twoFactorCode.value = ''
  recoveryCode.value = ''
  useRecoveryCode.value = false
  error.value = ''
  authStore.clearAuth()
}
</script>

<template>
  <div class="min-h-screen flex bg-gray-100 dark:bg-gray-900 transition-colors duration-300">

    <!-- Bal oldal: Dekorációs panel -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden items-center justify-center"
         style="background: linear-gradient(135deg, #134e4a 0%, #0d9488 50%, #14b8a6 100%);">
      <div class="relative z-10 text-center px-12">
        <div class="mb-8">
          <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-2xl backdrop-blur-sm mb-6">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
          </div>
        </div>
        <h1 class="text-4xl font-bold text-white mb-4">{{ t('app.name') }}</h1>
        <p class="text-xl text-teal-100 mb-2">{{ t('auth.app_subtitle') }}</p>
        <p class="text-teal-200/80 text-sm max-w-md mx-auto">{{ t('auth.app_desc') }}</p>
      </div>
      <div class="absolute top-20 left-10 w-72 h-72 bg-white/5 rounded-full blur-3xl"></div>
      <div class="absolute bottom-20 right-10 w-96 h-96 bg-teal-400/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Jobb oldal: Login form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
      <div class="w-full max-w-md">

        <!-- Mobil logó -->
        <div class="lg:hidden text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-teal-600 rounded-2xl mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('app.name') }}</h1>
        </div>

        <!-- Form kártya -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 border border-gray-200 dark:border-gray-700">
          <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ twoFactorMode ? 'Kétfaktoros hitelesítés' : t('auth.login') }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm">
              {{ twoFactorMode
                ? (useRecoveryCode ? 'Adj meg egy recovery kódot.' : 'Add meg az authenticator alkalmazás által generált 6 jegyű kódot.')
                : t('auth.sign_in_desc') }}
            </p>
          </div>

          <!-- 2FA Challenge form -->
          <form v-if="twoFactorMode" @submit.prevent="handleVerifyTwoFactor" class="space-y-5">
            <div v-if="!useRecoveryCode">
              <label for="totp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                6 jegyű kód
              </label>
              <input
                v-model="twoFactorCode"
                type="text"
                id="totp"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                required
                autofocus
                autocomplete="one-time-code"
                class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white text-center text-xl tracking-widest focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="000000"
              />
            </div>
            <div v-else>
              <label for="recovery" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                Recovery kód
              </label>
              <input
                v-model="recoveryCode"
                type="text"
                id="recovery"
                required
                autofocus
                autocomplete="off"
                class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white font-mono focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="XXXXX-XXXXX"
              />
            </div>

            <div v-if="error" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
              <span class="text-sm text-red-700 dark:text-red-400">{{ error }}</span>
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full py-2.5 px-4 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white font-medium rounded-lg focus:ring-4 focus:ring-teal-300 transition-all duration-200 text-sm"
            >
              {{ loading ? t('common.loading') : 'Megerősítés' }}
            </button>

            <div class="flex items-center justify-between text-xs">
              <button type="button" @click="useRecoveryCode = !useRecoveryCode" class="text-teal-600 hover:text-teal-500 font-medium">
                {{ useRecoveryCode ? 'Vissza a 6 jegyű kódhoz' : 'Recovery kód használata' }}
              </button>
              <button type="button" @click="cancelTwoFactor" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                Mégse
              </button>
            </div>
          </form>

          <form v-else @submit.prevent="handleLogin" class="space-y-5">
            <!-- Email -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {{ t('auth.email') }}
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                  </svg>
                </div>
                <input
                  v-model="email"
                  type="email"
                  id="email"
                  :placeholder="t('auth.email')"
                  required
                  autofocus
                  class="block w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-colors text-sm"
                />
              </div>
            </div>

            <!-- Jelszó -->
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {{ t('auth.password') }}
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                </div>
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  id="password"
                  :placeholder="t('auth.password')"
                  required
                  class="block w-full pl-10 pr-12 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-colors text-sm"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center"
                >
                  <!-- Eye open -->
                  <svg v-if="!showPassword" class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  <!-- Eye closed -->
                  <svg v-else class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                  </svg>
                </button>
              </div>
              <!-- Elfelejtett jelszó link a jelszó mező alatt -->
              <div class="mt-1.5 text-right">
                <router-link to="/forgot-password" class="text-sm text-teal-600 hover:text-teal-500 dark:text-teal-400 font-medium">
                  {{ t('auth.forgot_password') }}
                </router-link>
              </div>
            </div>

            <!-- Hiba üzenet -->
            <div v-if="error" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
              <div class="flex items-center">
                <svg class="w-4 h-4 text-red-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm text-red-700 dark:text-red-400">{{ error }}</span>
              </div>
            </div>

            <!-- Bejelentkezés gomb -->
            <button
              type="submit"
              :disabled="loading"
              class="w-full py-2.5 px-4 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white font-medium rounded-lg shadow-sm hover:shadow-md focus:ring-4 focus:ring-teal-300 dark:focus:ring-teal-800 transition-all duration-200 text-sm flex items-center justify-center"
            >
              <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ loading ? t('common.loading') : t('auth.login') }}
            </button>
          </form>
        </div>

        <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-6">
          {{ t('app.copyright') }}
        </p>
      </div>
    </div>
  </div>
</template>
