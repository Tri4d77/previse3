<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { createOrganization, fetchOrganizations, type OrganizationItem, type OrganizationType } from '@/services/organizations'

interface Props {
  // Ha "client" előre kiválasztott szülővel indul (szubszcreiber kontextusából)
  defaultParentId?: number | null
  defaultType?: 'subscriber' | 'client'
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success', org: OrganizationItem): void
}>()

const { t } = useI18n()

// Form
const name = ref('')
const type = ref<'subscriber' | 'client'>(props.defaultType || 'subscriber')
const parentId = ref<number | null>(props.defaultParentId ?? null)
const address = ref('')
const city = ref('')
const zipCode = ref('')
const phone = ref('')
const email = ref('')
const taxNumber = ref('')

// State
const subscribers = ref<OrganizationItem[]>([])
const loading = ref(false)
const loadingSubs = ref(false)
const errors = ref<Record<string, string>>({})
const generalError = ref('')

// Show parent select csak client típusnál
const showParentSelect = computed(() => type.value === 'client')

onMounted(async () => {
  // Subscriberek betöltése, hogy a client kiválaszthasson egyet
  loadingSubs.value = true
  try {
    const all = await fetchOrganizations()
    subscribers.value = all.filter(o => o.type === 'subscriber')
  } catch {
    // silent
  } finally {
    loadingSubs.value = false
  }
})

const canSubmit = computed(() => {
  if (loading.value) return false
  if (!name.value) return false
  if (type.value === 'client' && !parentId.value) return false
  return true
})

async function handleSubmit() {
  errors.value = {}
  generalError.value = ''
  loading.value = true

  try {
    const org = await createOrganization({
      name: name.value,
      type: type.value,
      parent_id: type.value === 'client' ? parentId.value : null,
      address: address.value || null,
      city: city.value || null,
      zip_code: zipCode.value || null,
      phone: phone.value || null,
      email: email.value || null,
      tax_number: taxNumber.value || null,
    })
    emit('success', org)
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
      generalError.value = err.response?.data?.message || 'Hiba történt a létrehozáskor.'
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
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full border border-gray-200 dark:border-gray-700 animate-scale-in"
        role="dialog"
        aria-modal="true"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Új szervezet</h3>
          <button @click="emit('close')" :disabled="loading" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <form @submit.prevent="handleSubmit" class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
          <!-- Általános hiba -->
          <div v-if="generalError" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <span class="text-sm text-red-700 dark:text-red-400">{{ generalError }}</span>
          </div>

          <!-- Típus -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              Típus <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                @click="type = 'subscriber'"
                :disabled="loading"
                class="p-3 text-sm border-2 rounded-lg transition-colors disabled:opacity-50"
                :class="type === 'subscriber' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20 text-teal-700 dark:text-teal-300' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-500'"
              >
                <div class="text-lg mb-1">🏢</div>
                <div class="font-semibold">Előfizető</div>
                <div class="text-xs mt-1 opacity-75">Fizető ügyfél a Platform alatt</div>
              </button>
              <button
                type="button"
                @click="type = 'client'"
                :disabled="loading"
                class="p-3 text-sm border-2 rounded-lg transition-colors disabled:opacity-50"
                :class="type === 'client' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20 text-teal-700 dark:text-teal-300' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-500'"
              >
                <div class="text-lg mb-1">🏬</div>
                <div class="font-semibold">Ügyfél</div>
                <div class="text-xs mt-1 opacity-75">Egy előfizető alá tartozó ügyfél</div>
              </button>
            </div>
          </div>

          <!-- Szülő (csak client) -->
          <div v-if="showParentSelect">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              Előfizető (szülő) <span class="text-red-500">*</span>
            </label>
            <select
              v-model="parentId"
              :disabled="loading || loadingSubs"
              required
              class="block w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm disabled:opacity-50"
              :class="errors.parent_id ? 'border-red-300 dark:border-red-700' : 'border-gray-300 dark:border-gray-600'"
            >
              <option :value="null" disabled>Válasszon előfizetőt...</option>
              <option v-for="s in subscribers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <p v-if="errors.parent_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errors.parent_id }}</p>
            <p v-else-if="subscribers.length === 0 && !loadingSubs" class="mt-1 text-xs text-amber-600 dark:text-amber-400">Még nincs előfizető szervezet. Először hozz létre egy előfizetőt!</p>
          </div>

          <!-- Név -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              Szervezet neve <span class="text-red-500">*</span>
            </label>
            <input
              v-model="name"
              type="text"
              required
              :disabled="loading"
              placeholder="pl. XY Karbantartó Kft."
              class="block w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm disabled:opacity-50"
              :class="errors.name ? 'border-red-300 dark:border-red-700' : 'border-gray-300 dark:border-gray-600'"
            />
            <p v-if="errors.name" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errors.name }}</p>
          </div>

          <!-- Opcionális: kapcsolattartó adatok -->
          <details class="bg-gray-50 dark:bg-gray-700/30 rounded-lg">
            <summary class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">További adatok (opcionális)</summary>
            <div class="px-3 pb-3 space-y-3">
              <div>
                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Cím</label>
                <input v-model="address" type="text" :disabled="loading"
                  class="block w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500" />
              </div>
              <div class="grid grid-cols-3 gap-2">
                <div class="col-span-1">
                  <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Irsz.</label>
                  <input v-model="zipCode" type="text" :disabled="loading"
                    class="block w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500" />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Város</label>
                  <input v-model="city" type="text" :disabled="loading"
                    class="block w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500" />
                </div>
              </div>
              <div>
                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Telefon</label>
                <input v-model="phone" type="tel" :disabled="loading"
                  class="block w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Email</label>
                <input v-model="email" type="email" :disabled="loading"
                  class="block w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Adószám</label>
                <input v-model="taxNumber" type="text" :disabled="loading"
                  class="block w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500" />
              </div>
            </div>
          </details>

          <!-- Info -->
          <div class="p-3 bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-lg">
            <p class="text-xs text-teal-800 dark:text-teal-200">
              A szervezet létrehozása után automatikusan létrejönnek az alap szerepkörök (Admin, Diszpécser, Felhasználó, Rögzítő, Karbantartó), és rögtön hívhatsz meg felhasználókat.
            </p>
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
            {{ loading ? t('common.loading') : 'Létrehozás' }}
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
