<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import PasswordStrengthIndicator from '@/components/common/PasswordStrengthIndicator.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const token = route.params.token as string
const invitationData = ref<any>(null)
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const loadingInfo = ref(true)
const error = ref('')
const expired = ref(false)
const success = ref(false)

onMounted(async () => {
  try {
    const response = await api.get(`/auth/invitation/${token}`)
    invitationData.value = response.data.data
    expired.value = response.data.data.expired
  } catch {
    error.value = t('auth.invitation_invalid')
  } finally {
    loadingInfo.value = false
  }
})

async function handleAccept() {
  error.value = ''
  loading.value = true

  // Létező usernél a confirmation = password (mert csak ellenőrzés a jelenlegi jelszóval)
  const confirmation = invitationData.value?.is_new_user
    ? passwordConfirmation.value
    : password.value

  try {
    await api.post('/auth/accept-invitation', {
      token,
      password: password.value,
      password_confirmation: confirmation,
    })
    success.value = true
    setTimeout(() => router.push({ name: 'login' }), 3000)
  } catch (err: any) {
    const errors = err.response?.data?.errors
    if (errors?.token) error.value = errors.token[0]
    else if (errors?.password) error.value = errors.password[0]
    else error.value = err.response?.data?.message || 'Hiba történt.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <!-- Bal oldal -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden items-center justify-center"
         style="background: linear-gradient(135deg, #134e4a 0%, #0d9488 50%, #14b8a6 100%);">
      <div class="relative z-10 text-center px-12">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-2xl backdrop-blur-sm mb-6">
          <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
        </div>
        <h1 class="text-4xl font-bold text-white mb-4">{{ t('app.name') }}</h1>
        <p class="text-xl text-teal-100">{{ t('auth.app_subtitle') }}</p>
      </div>
      <div class="absolute top-20 left-10 w-72 h-72 bg-white/5 rounded-full blur-3xl"></div>
      <div class="absolute bottom-20 right-10 w-96 h-96 bg-teal-400/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Jobb oldal -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
      <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 border border-gray-200 dark:border-gray-700">

          <!-- Betöltés -->
          <div v-if="loadingInfo" class="text-center py-8">
            <div class="animate-spin w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
            <p class="text-sm text-gray-500 mt-4">{{ t('common.loading') }}</p>
          </div>

          <!-- Érvénytelen link -->
          <div v-else-if="!invitationData && error" class="text-center py-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
              <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ t('auth.invitation_invalid') }}</h2>
            <router-link to="/login" class="text-sm text-teal-600 dark:text-teal-400">{{ t('auth.back_to_login') }}</router-link>
          </div>

          <!-- Lejárt -->
          <div v-else-if="expired" class="text-center py-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-amber-100 dark:bg-amber-900/30 rounded-full mb-4">
              <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ t('auth.invitation_expired') }}</h2>
            <router-link to="/login" class="text-sm text-teal-600 dark:text-teal-400">{{ t('auth.back_to_login') }}</router-link>
          </div>

          <!-- Sikeres aktiválás -->
          <div v-else-if="success" class="text-center py-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-green-100 dark:bg-green-900/30 rounded-full mb-4">
              <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Fiók aktiválva!</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Átirányítás a bejelentkezéshez...</p>
          </div>

          <!-- Meghívó form -->
          <template v-else-if="invitationData">
            <div class="text-center mb-6">
              <div class="inline-flex items-center justify-center w-14 h-14 bg-green-100 dark:bg-green-900/30 rounded-full mb-4">
                <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
              </div>
              <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('auth.accept_invitation') }}</h2>
              <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm">
                <template v-if="invitationData.is_new_user">
                  Állítsd be a jelszavadat a belépéshez.
                </template>
                <template v-else>
                  Erősítsd meg a meghívó elfogadását a jelenlegi jelszavad megadásával.
                </template>
              </p>
            </div>

            <!-- Meghívott adatai -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-6">
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">{{ t('common.name') }}:</span>
                  <span class="font-medium text-gray-900 dark:text-white">{{ invitationData.name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">{{ t('common.email') }}:</span>
                  <span class="font-medium text-gray-900 dark:text-white">{{ invitationData.email }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Szervezet:</span>
                  <span class="font-medium text-gray-900 dark:text-white">{{ invitationData.organization }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">{{ t('common.role') }}:</span>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300">{{ invitationData.role }}</span>
                </div>
              </div>
            </div>

            <!-- Létező userhez tartozó info -->
            <div v-if="!invitationData.is_new_user" class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
              <p class="text-xs text-blue-800 dark:text-blue-300">
                ℹ Már van fiókod a Previse-ben. Add meg a <strong>jelenlegi</strong> jelszavad a meghívó elfogadásához. Nem lesz új jelszó létrehozva.
              </p>
            </div>

            <form @submit.prevent="handleAccept" class="space-y-5">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                  {{ invitationData.is_new_user ? t('auth.set_password') : 'Jelenlegi jelszó' }}
                </label>
                <input v-model="password" type="password" required
                  :placeholder="invitationData.is_new_user ? t('auth.password_hint') : 'Jelenlegi jelszó'"
                  class="block w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-colors text-sm" />
                <!-- Jelszó erősség indikátor CSAK új usernél -->
                <div v-if="invitationData.is_new_user" class="mt-3">
                  <PasswordStrengthIndicator :password="password" />
                </div>
              </div>

              <!-- Megerősítés mezőt csak új usernél kérjük -->
              <div v-if="invitationData.is_new_user">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ t('auth.confirm_password') }}</label>
                <input v-model="passwordConfirmation" type="password" required :placeholder="t('auth.confirm_password')"
                  class="block w-full px-4 py-2.5 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-colors text-sm"
                  :class="passwordConfirmation && passwordConfirmation !== password ? 'border-red-400 dark:border-red-600' : 'border-gray-300 dark:border-gray-600'" />
                <p v-if="passwordConfirmation && passwordConfirmation !== password" class="mt-1 text-xs text-red-600 dark:text-red-400">
                  A két jelszó nem egyezik.
                </p>
              </div>

              <div v-if="error" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <span class="text-sm text-red-700 dark:text-red-400">{{ error }}</span>
              </div>

              <button type="submit" :disabled="loading"
                class="w-full py-2.5 px-4 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white font-medium rounded-lg shadow-sm hover:shadow-md focus:ring-4 focus:ring-green-300 dark:focus:ring-green-800 transition-all duration-200 text-sm">
                {{ loading ? t('common.loading') : (invitationData.is_new_user ? t('auth.activate_account') : 'Meghívó elfogadása') }}
              </button>
            </form>
          </template>
        </div>
        <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-6">{{ t('app.copyright') }}</p>
      </div>
    </div>
  </div>
</template>
