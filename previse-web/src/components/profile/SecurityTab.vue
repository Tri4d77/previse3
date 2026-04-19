<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  updatePassword,
  fetchSessions,
  revokeSession,
  revokeOtherSessions,
  type SessionItem,
} from '@/services/profile'
import { useToastStore } from '@/stores/toast'

const { t } = useI18n()
const toast = useToastStore()

// --- Password form ---
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const logoutOthers = ref(false)
const pwLoading = ref(false)
const pwErrors = ref<Record<string, string[]>>({})

async function submitPassword() {
  pwErrors.value = {}

  if (newPassword.value !== confirmPassword.value) {
    pwErrors.value = { password_confirmation: [t('profile.password_mismatch')] }
    return
  }

  pwLoading.value = true
  try {
    await updatePassword({
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: confirmPassword.value,
      logout_other_devices: logoutOthers.value,
    })
    toast.success(t('profile.password_updated'))
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    logoutOthers.value = false
    await loadSessions()
  } catch (err: any) {
    if (err.response?.data?.errors) {
      pwErrors.value = err.response.data.errors
    } else {
      toast.error(err.response?.data?.message ?? 'Hiba történt.')
    }
  } finally {
    pwLoading.value = false
  }
}

// --- Sessions ---
const sessions = ref<SessionItem[]>([])
const sessionsLoading = ref(false)
const revokingId = ref<number | null>(null)

async function loadSessions() {
  sessionsLoading.value = true
  try {
    sessions.value = await fetchSessions()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? 'Sessionök betöltése sikertelen.')
  } finally {
    sessionsLoading.value = false
  }
}

async function onRevokeSession(s: SessionItem) {
  if (s.is_current) return
  if (!confirm(t('profile.session_revoke_confirm'))) return

  revokingId.value = s.id
  try {
    await revokeSession(s.id)
    await loadSessions()
    toast.success(t('profile.session_revoke'))
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? 'Sikertelen.')
  } finally {
    revokingId.value = null
  }
}

async function onRevokeOthers() {
  if (!confirm(t('profile.sessions_revoke_others_confirm'))) return
  try {
    const { revoked_count } = await revokeOtherSessions()
    toast.success(t('profile.other_sessions_revoked') ?? `${revoked_count} eszköz kijelentkeztetve.`)
    await loadSessions()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? 'Sikertelen.')
  }
}

const otherSessionsCount = computed(() => sessions.value.filter((s) => !s.is_current).length)

// Egyszerű user-agent parser: böngésző + OS kinyerése UI-hoz
function parseUserAgent(ua: string | null): string {
  if (!ua) return 'Ismeretlen eszköz'
  let browser = 'Böngésző'
  if (/Chrome\//.test(ua) && !/Edg\//.test(ua)) browser = 'Chrome'
  else if (/Firefox\//.test(ua)) browser = 'Firefox'
  else if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) browser = 'Safari'
  else if (/Edg\//.test(ua)) browser = 'Edge'

  let os = ''
  if (/Windows/.test(ua)) os = 'Windows'
  else if (/Macintosh/.test(ua)) os = 'macOS'
  else if (/iPhone|iPad|iOS/.test(ua)) os = 'iOS'
  else if (/Android/.test(ua)) os = 'Android'
  else if (/Linux/.test(ua)) os = 'Linux'

  return os ? `${browser} • ${os}` : browser
}

function formatRelative(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return 'pár másodperce'
  if (diff < 3600) return `${Math.floor(diff / 60)} perce`
  if (diff < 86400) return `${Math.floor(diff / 3600)} órája`
  if (diff < 86400 * 7) return `${Math.floor(diff / 86400)} napja`
  return d.toLocaleDateString()
}

onMounted(loadSessions)
</script>

<template>
  <div class="space-y-8">
    <!-- Password section -->
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ t('profile.password_section') }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('profile.password_hint') }}</p>
      </div>
      <form @submit.prevent="submitPassword" class="p-6 space-y-4 max-w-md">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('profile.current_password') }}
          </label>
          <input
            v-model="currentPassword"
            type="password"
            required
            autocomplete="current-password"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
          />
          <p v-if="pwErrors.current_password" class="mt-1 text-xs text-red-600">{{ pwErrors.current_password[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('profile.new_password') }}
          </label>
          <input
            v-model="newPassword"
            type="password"
            required
            autocomplete="new-password"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
          />
          <p v-if="pwErrors.password" class="mt-1 text-xs text-red-600">{{ pwErrors.password[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('profile.confirm_new_password') }}
          </label>
          <input
            v-model="confirmPassword"
            type="password"
            required
            autocomplete="new-password"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
          />
          <p v-if="pwErrors.password_confirmation" class="mt-1 text-xs text-red-600">
            {{ pwErrors.password_confirmation[0] }}
          </p>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pt-2">
          <input v-model="logoutOthers" type="checkbox" class="rounded text-teal-600 focus:ring-teal-500" />
          {{ t('profile.logout_other_devices_label') }}
        </label>

        <button
          type="submit"
          :disabled="pwLoading"
          class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white text-sm font-medium rounded-lg transition-colors"
        >
          {{ pwLoading ? '…' : t('profile.change_password') }}
        </button>
      </form>
    </section>

    <!-- Sessions section -->
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('profile.sessions_section') }}</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('profile.sessions_intro') }}</p>
        </div>
        <button
          v-if="otherSessionsCount > 0"
          @click="onRevokeOthers"
          class="text-sm px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded border border-red-300 dark:border-red-700"
        >
          {{ t('profile.sessions_revoke_others') }}
        </button>
      </div>

      <div class="divide-y divide-gray-200 dark:divide-gray-700">
        <div v-if="sessionsLoading" class="p-6 text-center text-gray-500">…</div>

        <div
          v-for="s in sessions"
          :key="s.id"
          class="p-5 flex items-center gap-4"
        >
          <!-- Device icon -->
          <div class="shrink-0 w-10 h-10 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
            <svg class="w-5 h-5 text-teal-700 dark:text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-medium text-gray-900 dark:text-white">{{ parseUserAgent(s.user_agent) }}</span>
              <span v-if="s.is_current" class="text-xs px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                {{ t('profile.session_current') }}
              </span>
              <span v-if="s.is_impersonation" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                {{ t('profile.session_impersonation') }}
              </span>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-x-3">
              <span>{{ s.name }}</span>
              <span v-if="s.ip_address">{{ t('profile.session_ip') }}: {{ s.ip_address }}</span>
              <span>{{ t('profile.session_last_used') }}: {{ formatRelative(s.last_used_at) }}</span>
            </div>
          </div>

          <button
            v-if="!s.is_current"
            @click="onRevokeSession(s)"
            :disabled="revokingId === s.id"
            class="text-sm px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded disabled:opacity-50"
          >
            {{ t('profile.session_revoke') }}
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
