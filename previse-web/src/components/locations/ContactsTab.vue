<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToastStore } from '@/stores/toast'
import { useConfirmStore } from '@/stores/confirm'
import {
  fetchContacts,
  createContact,
  updateContact,
  deleteContact,
  type LocationContact,
} from '@/services/locationContacts'
import ContactCard from '@/components/common/ContactCard.vue'
import ContactFormModal from './ContactFormModal.vue'

interface Props {
  locationId: number
  canManage: boolean
}
const props = defineProps<Props>()

const { t } = useI18n()
const toast = useToastStore()
const confirmStore = useConfirmStore()

const contacts = ref<LocationContact[]>([])
const loading = ref(false)
const showModal = ref(false)
const editing = ref<LocationContact | null>(null)
const searchQuery = ref('')

const filteredContacts = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return contacts.value
  return contacts.value.filter((c) => {
    const fields = [c.name, c.role_label, c.phone, c.email].filter(Boolean) as string[]
    return fields.some((f) => f.toLowerCase().includes(q))
  })
})

async function load() {
  loading.value = true
  try {
    contacts.value = await fetchContacts(props.locationId)
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  } finally {
    loading.value = false
  }
}

function openNew() {
  editing.value = null
  showModal.value = true
}

function openEdit(c: LocationContact) {
  editing.value = c
  showModal.value = true
}

async function handleSave(payload: any) {
  try {
    if (editing.value) {
      await updateContact(editing.value.id, payload)
      toast.success(t('locations.contact_updated'))
    } else {
      await createContact(props.locationId, payload)
      toast.success(t('locations.contact_created'))
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  }
}

async function handleDelete(c: LocationContact) {
  const ok = await confirmStore.ask({
    title: t('locations.contact_deleted'),
    message: t('locations.contact_delete_confirm', { name: c.name }),
    confirmText: t('common.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteContact(c.id)
    toast.success(t('locations.contact_deleted'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  }
}

onMounted(load)
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700 gap-3 flex-wrap">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('locations.contacts_section') }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('locations.contacts_section_desc') }}</p>
      </div>
      <button
        v-if="canManage"
        @click="openNew"
        class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-sm rounded-lg"
      >
        + {{ t('locations.contact_new') }}
      </button>
    </div>

    <div v-if="loading" class="p-10 text-center">
      <div class="animate-spin w-6 h-6 border-2 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
    </div>

    <div v-else-if="!contacts.length" class="p-10 text-center text-sm text-gray-500 dark:text-gray-400">
      {{ t('locations.contacts_empty') }}
    </div>

    <template v-else>
      <!-- Gyorskereső -->
      <div v-if="contacts.length > 3" class="p-3 border-b border-gray-100 dark:border-gray-700">
        <div class="relative">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('locations.contacts_search_placeholder')"
            class="block w-full pl-9 pr-9 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
            :title="t('common.cancel')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <div v-if="!filteredContacts.length" class="p-10 text-center text-sm text-gray-500 dark:text-gray-400">
        {{ t('locations.search_no_match') }}
      </div>

      <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
      <li
        v-for="c in filteredContacts"
        :key="c.id"
        class="p-4 flex items-center gap-3"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <ContactCard
              :name="c.name"
              :role="c.role_label"
              :phone="c.phone"
              :email="c.email"
              :note="c.note"
            >
              <span class="font-medium">{{ c.name }}</span>
            </ContactCard>
            <span
              v-if="c.role_label"
              class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
            >
              {{ c.role_label }}
            </span>
          </div>
          <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex flex-wrap gap-x-4">
            <span v-if="c.phone">{{ c.phone }}</span>
            <span v-if="c.email">{{ c.email }}</span>
          </div>
        </div>

        <div v-if="canManage" class="flex items-center gap-1">
          <button
            @click="openEdit(c)"
            class="p-2 text-gray-400 hover:text-teal-600"
            :title="t('common.edit')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </button>
          <button
            @click="handleDelete(c)"
            class="p-2 text-gray-400 hover:text-red-600"
            :title="t('common.delete')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
            </svg>
          </button>
        </div>
      </li>
      </ul>
    </template>

    <ContactFormModal
      v-if="showModal"
      :location-id="locationId"
      :contact="editing"
      @close="showModal = false"
      @save="handleSave"
    />
  </div>
</template>
