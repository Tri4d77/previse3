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
import {
  fetchStatus as fetch2faStatus,
  enable as enable2fa,
  confirm as confirm2fa,
  disable as disable2fa,
  regenerateRecoveryCodes as regen2faCodes,
  type TwoFactorStatus,
  type TwoFactorEnableResponse,
} from '@/services/twoFactor'
import {
  requestEmailChange,
  cancelEmailChange,
} from '@/services/emailChange'
import {
  deleteAccount,
  cancelAccountDeletion,
  leaveOrganization,
} from '@/services/account'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const toast = useToastStore()
const authStore = useAuthStore()
const router = useRouter()

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

// =================== EMAIL CHANGE ===================
const emailChangeOpen = ref(false)
const newEmail = ref('')
const emailChangePassword = ref('')
const emailChangeErrors = ref<Record<string, string[]>>({})
const emailChangeLoading = ref(false)

const currentEmail = computed(() => authStore.user?.email ?? '')
const pendingEmail = computed(() => authStore.user?.pending_email ?? null)

async function submitEmailChange() {
  emailChangeErrors.value = {}
  emailChangeLoading.value = true
  try {
    await requestEmailChange({
      password: emailChangePassword.value,
      new_email: newEmail.value,
    })
    toast.success('Megerősítő levelet küldtünk az új címre.')
    emailChangeOpen.value = false
    newEmail.value = ''
    emailChangePassword.value = ''
    await authStore.fetchUser()
  } catch (err: any) {
    if (err.response?.data?.errors) {
      emailChangeErrors.value = err.response.data.errors
    } else {
      toast.error(err.response?.data?.message ?? 'Sikertelen.')
    }
  } finally {
    emailChangeLoading.value = false
  }
}

async function cancelPendingEmailChange() {
  if (!confirm('Biztosan visszavonod a függőben lévő email-változtatást?')) return
  try {
    await cancelEmailChange()
    toast.success('Email-változtatás visszavonva.')
    await authStore.fetchUser()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? 'Sikertelen.')
  }
}

// =================== 2FA ===================
const twoFaStatus = ref<TwoFactorStatus | null>(null)
const twoFaSetup = ref<TwoFactorEnableResponse | null>(null)
const twoFaCode = ref('')
const twoFaConfirming = ref(false)
const twoFaConfirmError = ref('')
const twoFaRecoveryCodes = ref<string[] | null>(null)

const disablePassword = ref('')
const disablingOpen = ref(false)
const disablingError = ref('')

async function load2faStatus() {
  try {
    twoFaStatus.value = await fetch2faStatus()
  } catch {
    /* swallow */
  }
}

async function onStart2fa() {
  try {
    twoFaSetup.value = await enable2fa()
    twoFaCode.value = ''
    twoFaConfirmError.value = ''
    twoFaRecoveryCodes.value = null
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? 'Sikertelen.')
  }
}

async function onConfirm2fa() {
  twoFaConfirming.value = true
  twoFaConfirmError.value = ''
  try {
    twoFaRecoveryCodes.value = await confirm2fa(twoFaCode.value.trim())
    twoFaSetup.value = null
    await load2faStatus()
    toast.success('Kétfaktoros hitelesítés bekapcsolva.')
  } catch (err: any) {
    twoFaConfirmError.value = err.response?.data?.errors?.code?.[0] ?? 'Érvénytelen kód.'
  } finally {
    twoFaConfirming.value = false
  }
}

async function onDisable2fa() {
  disablingError.value = ''
  try {
    await disable2fa(disablePassword.value)
    disablePassword.value = ''
    disablingOpen.value = false
    twoFaRecoveryCodes.value = null
    await load2faStatus()
    toast.success('Kétfaktoros hitelesítés kikapcsolva.')
  } catch (err: any) {
    disablingError.value = err.response?.data?.errors?.password?.[0] ?? err.response?.data?.message ?? 'Sikertelen.'
  }
}

async function onRegenRecoveryCodes() {
  if (!confirm('Új recovery kódok generálása — a régiek érvénytelenné válnak. Folytatod?')) return
  try {
    twoFaRecoveryCodes.value = await regen2faCodes()
    toast.success('Új recovery kódok generálva.')
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? 'Sikertelen.')
  }
}

function downloadRecoveryCodes() {
  if (!twoFaRecoveryCodes.value) return
  const content = [
    'Previse – 2FA recovery kódok',
    `Generálva: ${new Date().toLocaleString()}`,
    '',
    ...twoFaRecoveryCodes.value,
    '',
    'Minden kód csak EGYSZER használható. Tárold biztonságos helyen.',
  ].join('\n')
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = 'previse-recovery-codes.txt'
  link.click()
  URL.revokeObjectURL(link.href)
}

// =================== ACCOUNT DELETION ===================
const deleteAccountOpen = ref(false)
const deleteAccountPassword = ref('')
const deleteAccountError = ref('')
const deleteAccountLoading = ref(false)

const isScheduledForDeletion = computed(() => !!authStore.user?.scheduled_deletion_at)
const daysUntilDeletion = computed(() => authStore.user?.days_until_deletion ?? null)

async function confirmDeleteAccount() {
  deleteAccountError.value = ''
  deleteAccountLoading.value = true
  try {
    await deleteAccount(deleteAccountPassword.value)
    toast.success('A fiókod törlésre ütemezve. 30 nap múlva véglegesen törlődik.')
    // Kijelentkeztetjük (token törlődött a backend-en)
    authStore.clearAuth()
    router.push({ name: 'login' })
  } catch (err: any) {
    deleteAccountError.value =
      err.response?.data?.errors?.password?.[0]
      ?? err.response?.data?.message
      ?? 'Sikertelen.'
  } finally {
    deleteAccountLoading.value = false
  }
}

// =================== MEMBERSHIPS (kilépés) ===================
const memberships = computed(() => authStore.memberships ?? [])
const leavingMembershipId = ref<number | null>(null)

async function onLeaveMembership(membershipId: number, orgName: string) {
  if (!confirm(`Biztosan kilépsz a(z) "${orgName}" szervezetből?`)) return

  leavingMembershipId.value = membershipId
  try {
    await leaveOrganization(membershipId)
    toast.success('Kiléptél a szervezetből.')
    await authStore.fetchUser()
  } catch (err: any) {
    const code = err.response?.data?.code
    if (code === 'last_active_membership') {
      if (confirm(
        'Ez az egyetlen aktív tagságod, így kilépés nem lehetséges.\n\n'
        + 'Szeretnéd elindítani a fiók-törlést (30 napos grace periód)?'
      )) {
        // Lentebb görgetünk a fiók-törlés szekcióhoz
        deleteAccountOpen.value = true
        document.querySelector('[data-section="delete-account"]')?.scrollIntoView({ behavior: 'smooth' })
      }
    } else if (code === 'last_super_admin') {
      toast.error('Te vagy az egyetlen szuper-admin. Előbb nevezz ki új szuper-admint.')
    } else {
      toast.error(err.response?.data?.message ?? 'Sikertelen.')
    }
  } finally {
    leavingMembershipId.value = null
  }
}

async function doCancelAccountDeletion() {
  if (!confirm('Biztosan visszavonod a fiók törlését?')) return
  try {
    await cancelAccountDeletion()
    toast.success('Fiók-törlés visszavonva.')
    await authStore.fetchUser()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? 'Sikertelen.')
  }
}

onMounted(() => {
  loadSessions()
  load2faStatus()
})
</script>

<template>
  <div class="space-y-8">

    <!-- Email cím szekció -->
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Email cím</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Az email címedhez fűződik a bejelentkezés és a fiók-értesítések küldése.
        </p>
      </div>
      <div class="p-6 space-y-4">
        <div class="flex items-center gap-3">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jelenlegi:</span>
          <code class="font-mono text-sm px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">{{ currentEmail }}</code>
        </div>

        <!-- Pending állapot -->
        <div v-if="pendingEmail" class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
          <p class="text-sm text-amber-900 dark:text-amber-200">
            Függőben: az új cím (<code class="font-mono">{{ pendingEmail }}</code>) megerősítésére várunk.
            Nézd meg az új címen kapott levelet, és kattints a megerősítő linkre.
          </p>
          <button
            @click="cancelPendingEmailChange"
            class="mt-3 text-xs px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded border border-red-300 dark:border-red-700"
          >
            Változtatás visszavonása
          </button>
        </div>

        <div v-else>
          <button
            v-if="!emailChangeOpen"
            @click="emailChangeOpen = true"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            Email cím módosítása
          </button>

          <form v-else @submit.prevent="submitEmailChange" class="space-y-3 max-w-md">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Új email cím</label>
              <input
                v-model="newEmail"
                type="email"
                required
                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
              />
              <p v-if="emailChangeErrors.new_email" class="mt-1 text-xs text-red-600">{{ emailChangeErrors.new_email[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jelenlegi jelszó</label>
              <input
                v-model="emailChangePassword"
                type="password"
                required
                autocomplete="current-password"
                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
              />
              <p v-if="emailChangeErrors.password" class="mt-1 text-xs text-red-600">{{ emailChangeErrors.password[0] }}</p>
            </div>
            <div class="flex gap-2">
              <button
                type="submit"
                :disabled="emailChangeLoading"
                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white text-sm font-medium rounded-lg"
              >
                {{ emailChangeLoading ? '…' : 'Megerősítő levél küldése' }}
              </button>
              <button
                type="button"
                @click="emailChangeOpen = false; emailChangeErrors = {}; newEmail = ''; emailChangePassword = ''"
                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
              >
                Mégse
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <!-- Tagságaim szekció -->
    <section v-if="memberships.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Szervezeti tagságaim</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Ezekhez a szervezetekhez van aktív tagságod. Kiléphetsz bármelyikből, ha már nem dolgozol ott.
        </p>
      </div>
      <div class="divide-y divide-gray-200 dark:divide-gray-700">
        <div v-for="m in memberships" :key="m.id" class="p-5 flex items-center gap-4">
          <div class="shrink-0 w-10 h-10 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
            <span class="text-base">
              {{ m.organization.type === 'platform' ? '🏛️' : m.organization.type === 'subscriber' ? '🏢' : '🏬' }}
            </span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-medium text-gray-900 dark:text-white">{{ m.organization.name }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                {{ m.role.name }}
              </span>
            </div>
          </div>
          <button
            @click="onLeaveMembership(m.id, m.organization.name)"
            :disabled="leavingMembershipId === m.id"
            class="text-sm px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded disabled:opacity-50"
          >
            Kilépés
          </button>
        </div>
      </div>
    </section>

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

    <!-- 2FA szekció -->
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Kétfaktoros hitelesítés (2FA)</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Plusz biztonsági réteg: bejelentkezéskor egy authenticator app által generált 6 jegyű kód kell.
          </p>
        </div>
        <span
          v-if="twoFaStatus?.enabled"
          class="text-xs px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 font-medium"
        >
          Aktív
        </span>
        <span
          v-else
          class="text-xs px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 font-medium"
        >
          Kikapcsolva
        </span>
      </div>

      <div class="p-6">
        <!-- Nincs aktív 2FA és nincs setup folyamatban → Bekapcsolás gomb -->
        <div v-if="!twoFaStatus?.enabled && !twoFaSetup">
          <button
            @click="onStart2fa"
            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg"
          >
            2FA bekapcsolása
          </button>
        </div>

        <!-- Setup folyamatban → QR + kód megerősítés -->
        <div v-if="twoFaSetup" class="space-y-4">
          <p class="text-sm text-gray-700 dark:text-gray-300">
            1. Olvasd be a QR kódot egy authenticator appal (Google Authenticator, 1Password, Authy…).
          </p>

          <div class="inline-block p-3 bg-white rounded-lg" v-html="twoFaSetup.qr_code_svg"></div>

          <p class="text-xs text-gray-500 dark:text-gray-400">
            Vagy add hozzá kézzel ezt a secret kulcsot:
            <code class="font-mono px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">{{ twoFaSetup.secret }}</code>
          </p>

          <p class="text-sm text-gray-700 dark:text-gray-300 pt-2">
            2. Add meg az app által mutatott 6 jegyű kódot:
          </p>

          <div class="flex items-start gap-2">
            <input
              v-model="twoFaCode"
              type="text"
              inputmode="numeric"
              pattern="[0-9]{6}"
              maxlength="6"
              placeholder="000000"
              class="w-40 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-center tracking-widest focus:ring-2 focus:ring-teal-500"
            />
            <button
              @click="onConfirm2fa"
              :disabled="twoFaConfirming || twoFaCode.length !== 6"
              class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white text-sm font-medium rounded-lg"
            >
              Megerősítés
            </button>
          </div>

          <p v-if="twoFaConfirmError" class="text-sm text-red-600">{{ twoFaConfirmError }}</p>
        </div>

        <!-- Recovery kódok (frissen generált / újragenerált) -->
        <div v-if="twoFaRecoveryCodes" class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
          <h3 class="font-medium text-amber-900 dark:text-amber-200 mb-2">Recovery kódok — mentsd el most!</h3>
          <p class="text-xs text-amber-800 dark:text-amber-300 mb-3">
            Minden kód EGYSZER használható. Ha elveszítenéd az authenticator appot, ezekkel tudsz még belépni.
          </p>
          <div class="grid grid-cols-2 gap-2 mb-3 font-mono text-sm">
            <code v-for="c in twoFaRecoveryCodes" :key="c" class="px-3 py-1.5 bg-white dark:bg-gray-800 rounded border border-amber-300">{{ c }}</code>
          </div>
          <button
            @click="downloadRecoveryCodes"
            class="text-xs px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded"
          >
            Letöltés (.txt)
          </button>
        </div>

        <!-- Aktív 2FA → disable + regenerate gombok -->
        <div v-if="twoFaStatus?.enabled && !twoFaSetup" class="space-y-3">
          <button
            @click="onRegenRecoveryCodes"
            class="text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            Új recovery kódok generálása
          </button>

          <div>
            <button
              v-if="!disablingOpen"
              @click="disablingOpen = true"
              class="text-sm px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded border border-red-300 dark:border-red-700"
            >
              2FA kikapcsolása
            </button>

            <div v-else class="flex items-start gap-2 mt-2">
              <input
                v-model="disablePassword"
                type="password"
                placeholder="Jelenlegi jelszó"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"
                autocomplete="current-password"
              />
              <button @click="onDisable2fa" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg">
                Megerősítés
              </button>
              <button @click="disablingOpen = false; disablePassword = ''; disablingError = ''" class="px-3 py-2 text-sm text-gray-500">
                Mégse
              </button>
            </div>
            <p v-if="disablingError" class="mt-1 text-xs text-red-600">{{ disablingError }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Fiók megszüntetése szekció -->
    <section data-section="delete-account" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-red-200 dark:border-red-900/30">
      <div class="p-6 border-b border-red-100 dark:border-red-900/30">
        <h2 class="text-lg font-semibold text-red-700 dark:text-red-400">Fiók megszüntetése</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Véglegesen lezárja a fiókod. 30 napos türelmi idő után anonimizálásra kerül.
          Egyedül a neved marad meg (a rendszerbe bevitt adatok mellett, hogy a műszaki
          személyzet tudja, ki rögzítette).
        </p>
      </div>
      <div class="p-6">

        <!-- Pending deletion állapot -->
        <div v-if="isScheduledForDeletion" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <p class="text-sm text-red-900 dark:text-red-200 font-medium">
            Fiókod <strong>{{ daysUntilDeletion }} nap</strong> múlva véglegesen törlődik.
          </p>
          <p class="text-xs text-red-800 dark:text-red-300 mt-1">
            A tagságaid deaktiválva lettek. A visszavonáshoz kattints alul.
          </p>
          <button
            @click="doCancelAccountDeletion"
            class="mt-3 text-sm px-3 py-1.5 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/30"
          >
            Fiók-törlés visszavonása
          </button>
        </div>

        <!-- Nincs pending -->
        <div v-else>
          <button
            v-if="!deleteAccountOpen"
            @click="deleteAccountOpen = true"
            class="text-sm px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded border border-red-300 dark:border-red-700"
          >
            Fiók megszüntetése
          </button>

          <div v-else class="space-y-3 max-w-md">
            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded text-xs text-amber-900 dark:text-amber-200">
              <p class="font-medium mb-1">⚠️ Figyelem</p>
              <p class="mb-2">
                Ha egy szervezet <strong>utolsó adminisztrátora</strong> vagy,
                előbb érdemes kinevezni egy másik admint (új meghívással vagy egy meglévő
                tag szerepkörének módosításával), különben a szervezetet a Platform
                szuper-admin fogja felügyelni.
              </p>
              <p>A tagjaid email-ben értesítést kapnak, ha te voltál az utolsó admin.</p>
            </div>

            <input
              v-model="deleteAccountPassword"
              type="password"
              placeholder="Jelszó megerősítése"
              autocomplete="current-password"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"
            />
            <p v-if="deleteAccountError" class="text-xs text-red-600">{{ deleteAccountError }}</p>

            <div class="flex gap-2">
              <button
                @click="confirmDeleteAccount"
                :disabled="deleteAccountLoading || !deleteAccountPassword"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white text-sm font-medium rounded-lg"
              >
                {{ deleteAccountLoading ? '…' : 'Igen, indítom a 30 napos visszaszámlálást' }}
              </button>
              <button
                @click="deleteAccountOpen = false; deleteAccountPassword = ''; deleteAccountError = ''"
                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
              >
                Mégse
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
