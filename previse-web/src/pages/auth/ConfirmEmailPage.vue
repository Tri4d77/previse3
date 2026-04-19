<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { confirmEmailChange } from '@/services/emailChange'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const success = ref(false)
const error = ref('')
const newEmail = ref('')

onMounted(async () => {
  const token = route.params.token as string
  if (!token) {
    error.value = 'Hiányzó vagy érvénytelen link.'
    loading.value = false
    return
  }

  try {
    newEmail.value = await confirmEmailChange(token)
    success.value = true
  } catch (err: any) {
    error.value = err.response?.data?.errors?.token?.[0]
      ?? err.response?.data?.message
      ?? 'Érvénytelen vagy lejárt megerősítő link.'
  } finally {
    loading.value = false
  }
})

function goLogin() {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 p-6">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 border border-gray-200 dark:border-gray-700">
      <div class="text-center">

        <div v-if="loading" class="py-8">
          <svg class="animate-spin w-8 h-8 mx-auto text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
          <p class="mt-4 text-gray-600 dark:text-gray-300">Megerősítés folyamatban…</p>
        </div>

        <div v-else-if="success">
          <div class="mx-auto w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Email-cím megerősítve</h1>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
            Az új email címed: <strong class="font-mono">{{ newEmail }}</strong>
          </p>
          <button
            @click="goLogin"
            class="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg"
          >
            Bejelentkezés
          </button>
        </div>

        <div v-else>
          <div class="mx-auto w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </div>
          <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Nem sikerült</h1>
          <p class="text-sm text-red-600 dark:text-red-400 mb-6">{{ error }}</p>
          <button
            @click="goLogin"
            class="w-full py-2.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-white font-medium rounded-lg"
          >
            Vissza a bejelentkezéshez
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
