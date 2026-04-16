<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const error = ref('')

async function handleSubmit() {
  error.value = ''
  loading.value = true

  try {
    await api.post('/auth/forgot-password', { email: email.value })
    sent.value = true
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Hiba történt.'
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
          <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-amber-100 dark:bg-amber-900/30 rounded-full mb-4">
              <svg class="w-7 h-7 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
              </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('auth.forgot_password_title') }}</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm">{{ t('auth.forgot_password_desc') }}</p>
          </div>

          <form v-if="!sent" @submit.prevent="handleSubmit" class="space-y-5">
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ t('auth.email') }}</label>
              <input v-model="email" type="email" id="email" :placeholder="t('auth.email')" required autofocus
                class="block w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-colors text-sm" />
            </div>

            <div v-if="error" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
              <span class="text-sm text-red-700 dark:text-red-400">{{ error }}</span>
            </div>

            <button type="submit" :disabled="loading"
              class="w-full py-2.5 px-4 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white font-medium rounded-lg shadow-sm hover:shadow-md focus:ring-4 focus:ring-teal-300 dark:focus:ring-teal-800 transition-all duration-200 text-sm">
              {{ loading ? t('common.loading') : t('auth.send_reset_link') }}
            </button>
          </form>

          <!-- Sikeres küldés -->
          <div v-else class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <div class="flex items-center">
              <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <span class="text-sm text-green-700 dark:text-green-400">{{ t('auth.reset_link_sent') }}</span>
            </div>
          </div>

          <div class="mt-6 text-center">
            <router-link to="/login" class="text-sm text-teal-600 hover:text-teal-500 dark:text-teal-400 font-medium inline-flex items-center">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
              {{ t('auth.back_to_login') }}
            </router-link>
          </div>
        </div>
        <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-6">{{ t('app.copyright') }}</p>
      </div>
    </div>
  </div>
</template>
