<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { inviteMember, checkEmail, fetchRoles, type SimpleRole, type CheckEmailResponse } from '@/services/memberships'

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success', data: { invitationUrl: string; userName: string; userEmail: string; isExistingUser: boolean }): void
}>()

const { t } = useI18n()

// Form state
const email = ref('')
const name = ref('')
const phone = ref('')
const roleId = ref<number | null>(null)
const roles = ref<SimpleRole[]>([])

// Email check state
const emailChecked = ref(false)
const checkingEmail = ref(false)
const emailCheckResult = ref<CheckEmailResponse | null>(null)

// UI state
const loading = ref(false)
const loadingRoles = ref(true)
const errors = ref<Record<string, string>>({})
const generalError = ref('')

onMounted(async () => {
  try {
    roles.value = await fetchRoles()
  } catch {
    generalError.value = 'Szerepkörök betöltése sikertelen.'
  } finally {
    loadingRoles.value = false
  }
})

// Debounce email ellenőrzés
let emailDebounce: ReturnType<typeof setTimeout> | null = null

function onEmailInput() {
  emailChecked.value = false
  emailCheckResult.value = null
  errors.value.email = ''

  if (emailDebounce) clearTimeout(emailDebounce)
  emailDebounce = setTimeout(performEmailCheck, 600)
}

async function performEmailCheck() {
  if (!email.value || !email.value.includes('@')) return

  checkingEmail.value = true
  try {
    emailCheckResult.value = await checkEmail(email.value)
    emailChecked.value = true

    // Ha létezik user, automatikusan kitöltjük a nevet
    if (emailCheckResult.value.user_exists && emailCheckResult.value.user) {
      name.value = emailCheckResult.value.user.name
    } else {
      name.value = ''
    }
  } catch {
    // Silent fail
  } finally {
    checkingEmail.value = false
  }
}

// Küldhető-e az űrlap
const canSubmit = computed(() => {
  if (loading.value) return false
  if (!email.value || !roleId.value) return false

  if (emailCheckResult.value?.already_member) return false
  if (emailCheckResult.value?.has_pending_invitation) return false

  // Új user esetén kell név
  if (emailCheckResult.value && !emailCheckResult.value.user_exists && !name.value) return false

  return true
})

async function handleSubmit() {
  errors.value = {}
  generalError.value = ''
  loading.value = true

  try {
    const response = await inviteMember({
      name: name.value || undefined,
      email: email.value,
      role_id: roleId.value!,
      phone: phone.value || undefined,
    })

    emit('success', {
      invitationUrl: response.invitation_url,
      userName: response.data.user.name,
      userEmail: response.data.user.email,
      isExistingUser: response.is_existing_user,
    })
  } catch (err: any) {
    if (err.response?.status === 422) {
      const apiErrors = err.response.data.errors || {}
      for (const field in apiErrors) {
        errors.value[field] = apiErrors[field][0]
      }
      if (err.response.data.message && Object.keys(apiErrors).length === 0) {
        generalError.value = err.response.data.message
      }
    } else {
      generalError.value = err.response?.data?.message || 'Hiba történt a meghívó küldésekor.'
    }
  } finally {
    loading.value = false
  }
}

function handleBackdropClick(event: MouseEvent) {
  if (event.target === event.currentTarget && !loading.value) {
    emit('close')
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-[90] bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 animate-fade-in"
      @click="handleBackdropClick"
    >
      <div
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full border border-gray-200 dark:border-gray-700 animate-scale-in"
        role="dialog"
        aria-modal="true"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ t('users.invite') }}
          </h3>
          <button
            @click="emit('close')"
            :disabled="loading"
            class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body / Form -->
        <form @submit.prevent="handleSubmit" class="px-6 py-5 space-y-4">
          <!-- Info -->
          <div class="p-3 bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-lg flex items-start gap-2">
            <svg class="w-4 h-4 text-teal-600 dark:text-teal-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-xs text-teal-800 dark:text-teal-200">{{ t('users.invite_desc') }}</p>
          </div>

          <!-- Általános hiba -->
          <div v-if="generalError" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <span class="text-sm text-red-700 dark:text-red-400">{{ generalError }}</span>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ t('common.email') }} <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input
                v-model="email"
                @input="onEmailInput"
                type="email"
                required
                autofocus
                :disabled="loading"
                placeholder="pelda@ceg.hu"
                class="block w-full px-3 py-2 pr-10 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm disabled:opacity-50"
                :class="errors.email ? 'border-red-300 dark:border-red-700' : 'border-gray-300 dark:border-gray-600'"
              />
              <!-- Loading indikátor -->
              <div v-if="checkingEmail" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>
            </div>
            <p v-if="errors.email" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errors.email }}</p>
          </div>

          <!-- Email check eredménye -->
          <div v-if="emailChecked && emailCheckResult" class="rounded-lg p-3 border" :class="{
            'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': emailCheckResult.already_member || emailCheckResult.has_pending_invitation,
            'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': emailCheckResult.user_exists && !emailCheckResult.already_member && !emailCheckResult.has_pending_invitation,
            'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800': !emailCheckResult.user_exists,
          }">
            <p class="text-xs" :class="{
              'text-red-700 dark:text-red-400': emailCheckResult.already_member || emailCheckResult.has_pending_invitation,
              'text-blue-700 dark:text-blue-400': emailCheckResult.user_exists && !emailCheckResult.already_member && !emailCheckResult.has_pending_invitation,
              'text-green-700 dark:text-green-400': !emailCheckResult.user_exists,
            }">
              <template v-if="emailCheckResult.already_member">
                ⚠ Ez a felhasználó már tagja a szervezetnek.
              </template>
              <template v-else-if="emailCheckResult.has_pending_invitation">
                ⚠ Ennek a felhasználónak már van függőben lévő meghívója ide.
              </template>
              <template v-else-if="emailCheckResult.has_deleted_membership">
                ℹ Ez a felhasználó korábban tag volt, de eltávolították. Az új meghívással újra tag lehet.
              </template>
              <template v-else-if="emailCheckResult.user_exists">
                ℹ <strong>{{ emailCheckResult.user?.name }}</strong> már regisztrált felhasználó. A meghívó elfogadása után a meglévő jelszavával fog tudni belépni.
              </template>
              <template v-else>
                ✓ Új felhasználó lesz létrehozva. A meghívó linken tudja beállítani a jelszavát.
              </template>
            </p>
          </div>

          <!-- Név - új user esetén kötelező, létező usernél automatikusan kitöltődik -->
          <div v-if="emailChecked && !emailCheckResult?.already_member && !emailCheckResult?.has_pending_invitation">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ t('common.name') }}
              <span v-if="!emailCheckResult?.user_exists" class="text-red-500">*</span>
              <span v-else class="text-xs text-gray-500">(nem módosítható létező felhasználónál)</span>
            </label>
            <input
              v-model="name"
              type="text"
              :required="!emailCheckResult?.user_exists"
              :disabled="loading || emailCheckResult?.user_exists"
              class="block w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-700/50"
              :class="errors.name ? 'border-red-300 dark:border-red-700' : 'border-gray-300 dark:border-gray-600'"
            />
            <p v-if="errors.name" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errors.name }}</p>
          </div>

          <!-- Telefon - csak új usernél -->
          <div v-if="emailChecked && !emailCheckResult?.already_member && !emailCheckResult?.has_pending_invitation && !emailCheckResult?.user_exists">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ t('common.phone') }}</label>
            <input
              v-model="phone"
              type="tel"
              :disabled="loading"
              placeholder="+36 30 123 4567"
              class="block w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm disabled:opacity-50"
              :class="errors.phone ? 'border-red-300 dark:border-red-700' : 'border-gray-300 dark:border-gray-600'"
            />
          </div>

          <!-- Szerepkör -->
          <div v-if="emailChecked && !emailCheckResult?.already_member && !emailCheckResult?.has_pending_invitation">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              {{ t('common.role') }} <span class="text-red-500">*</span>
            </label>
            <select
              v-model="roleId"
              required
              :disabled="loading || loadingRoles"
              class="block w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm disabled:opacity-50"
              :class="errors.role_id ? 'border-red-300 dark:border-red-700' : 'border-gray-300 dark:border-gray-600'"
            >
              <option :value="null" disabled>Válasszon szerepkört...</option>
              <option v-for="role in roles" :key="role.id" :value="role.id">
                {{ role.name }}
              </option>
            </select>
            <p v-if="errors.role_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errors.role_id }}</p>
          </div>
        </form>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 rounded-b-2xl">
          <button
            type="button"
            @click="emit('close')"
            :disabled="loading"
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            @click="handleSubmit"
            :disabled="!canSubmit"
            class="px-4 py-2 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 disabled:cursor-not-allowed rounded-lg shadow-sm flex items-center gap-2"
          >
            <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ loading ? t('common.loading') : t('users.send_invite') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes scale-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.animate-fade-in { animation: fade-in 0.15s ease-out; }
.animate-scale-in { animation: scale-in 0.15s ease-out; }
</style>
