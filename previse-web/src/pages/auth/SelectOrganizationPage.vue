<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import type { Membership } from '@/types'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref<number | null>(null) // a választott membership_id töltés alatt
const error = ref('')

const memberships = computed<Membership[]>(() => authStore.selectionMemberships)

onMounted(() => {
  // Ha nincs selection token → login oldalra
  if (!authStore.selectionToken || memberships.value.length === 0) {
    router.replace({ name: 'login' })
  }
})

async function handleSelect(membership: Membership) {
  error.value = ''
  loading.value = membership.id

  try {
    await authStore.selectOrganization(membership.id)
    router.push({ name: 'dashboard' })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Hiba történt a szervezet kiválasztásakor.'
    loading.value = null
  }
}

async function handleLogout() {
  authStore.clearAuth()
  router.push({ name: 'login' })
}

function orgTypeLabel(type: string): string {
  const map: Record<string, string> = {
    platform: 'Platform',
    subscriber: 'Előfizető',
    client: 'Ügyfél',
  }
  return map[type] || type
}

function orgTypeBadge(type: string): string {
  const map: Record<string, string> = {
    platform: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
    subscriber: 'bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300',
    client: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
  }
  return map[type] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'
}

function formatLastActive(dateString: string | null | undefined): string {
  if (!dateString) return 'még nem dolgozott benne'
  const date = new Date(dateString)
  return `utoljára: ${date.toLocaleString('hu-HU', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })}`
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-8 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <div class="w-full max-w-2xl">
      <!-- Fejléc -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-teal-600 rounded-2xl mb-4">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Üdvözlünk!</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
          Válaszd ki, melyik szervezet ügyeit szeretnéd intézni:
        </p>
      </div>

      <!-- Hiba üzenet -->
      <div v-if="error" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <span class="text-sm text-red-700 dark:text-red-400">{{ error }}</span>
      </div>

      <!-- Tagságok listája -->
      <div class="space-y-3">
        <button
          v-for="m in memberships"
          :key="m.id"
          @click="handleSelect(m)"
          :disabled="loading !== null"
          class="w-full bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4 hover:border-teal-500 dark:hover:border-teal-500 hover:shadow-md transition-all text-left disabled:opacity-50 disabled:cursor-not-allowed"
          :class="loading === m.id ? 'ring-2 ring-teal-500' : ''"
        >
          <!-- Szervezet ikon -->
          <div class="w-12 h-12 rounded-lg bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
            </svg>
          </div>

          <!-- Adatok -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                {{ m.organization.name }}
              </h3>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="orgTypeBadge(m.organization.type)">
                {{ orgTypeLabel(m.organization.type) }}
              </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              <span class="font-medium text-gray-700 dark:text-gray-300">{{ m.role.name }}</span>
              <span class="mx-1.5">·</span>
              <span>{{ formatLastActive(m.last_active_at) }}</span>
            </p>
          </div>

          <!-- Loading vagy nyíl -->
          <div class="flex-shrink-0">
            <svg v-if="loading === m.id" class="animate-spin w-5 h-5 text-teal-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </button>
      </div>

      <!-- Kijelentkezés -->
      <div class="mt-6 text-center">
        <button
          @click="handleLogout"
          class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
        >
          Kijelentkezés
        </button>
      </div>

      <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-6">
        {{ t('app.copyright') }}
      </p>
    </div>
  </div>
</template>
