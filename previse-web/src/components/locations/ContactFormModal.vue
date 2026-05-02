<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { fetchContactRoles, type LocationContact } from '@/services/locationContacts'

interface Props {
  locationId: number
  contact?: LocationContact | null
}
const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (
    e: 'save',
    payload: {
      name: string
      role_label: string | null
      phone: string | null
      email: string | null
      note: string | null
    },
  ): void
}>()

const { t } = useI18n()

const name = ref(props.contact?.name ?? '')
const roleLabel = ref(props.contact?.role_label ?? '')
const phone = ref(props.contact?.phone ?? '')
const email = ref(props.contact?.email ?? '')
const note = ref(props.contact?.note ?? '')

const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const roleSuggestions = ref<string[]>([])

onMounted(async () => {
  try {
    roleSuggestions.value = await fetchContactRoles(props.locationId)
  } catch {
    /* ignore */
  }
})

async function submit() {
  errors.value = {}
  loading.value = true
  try {
    emit('save', {
      name: name.value.trim(),
      role_label: roleLabel.value.trim() || null,
      phone: phone.value.trim() || null,
      email: email.value.trim() || null,
      note: note.value.trim() || null,
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl">
      <div class="p-5 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ contact ? t('locations.contact_edit') : t('locations.contact_new') }}
        </h2>
      </div>
      <form @submit.prevent="submit" class="p-5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.contact_name') }} <span class="text-red-500">*</span>
          </label>
          <input
            v-model="name"
            type="text"
            required
            :placeholder="t('locations.contact_name_placeholder')"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.contact_role') }}
          </label>
          <input
            v-model="roleLabel"
            type="text"
            list="contact-role-suggestions"
            :placeholder="t('locations.contact_role_placeholder')"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
          <datalist id="contact-role-suggestions">
            <option v-for="r in roleSuggestions" :key="r" :value="r" />
          </datalist>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('locations.contact_role_hint') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.contact_phone') }}
            </label>
            <input
              v-model="phone"
              type="text"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.contact_email') }}
            </label>
            <input
              v-model="email"
              type="email"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
            />
            <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email[0] }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.contact_note') }}
          </label>
          <textarea
            v-model="note"
            rows="2"
            :placeholder="t('locations.contact_note_placeholder')"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="loading || !name.trim()"
            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white text-sm font-medium rounded-lg"
          >
            {{ t('common.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
