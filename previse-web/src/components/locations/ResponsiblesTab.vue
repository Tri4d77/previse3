<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToastStore } from '@/stores/toast'
import { useConfirmStore } from '@/stores/confirm'
import {
  fetchResponsibles,
  fetchAvailableResponsibles,
  addResponsibles,
  removeResponsible,
  type LocationResponsible,
} from '@/services/locationResponsibles'
import ContactCard from '@/components/common/ContactCard.vue'

interface Props {
  locationId: number
  canManage: boolean
}
const props = defineProps<Props>()

const { t } = useI18n()
const toast = useToastStore()
const confirmStore = useConfirmStore()

const items = ref<LocationResponsible[]>([])
const available = ref<LocationResponsible[]>([])
const loading = ref(false)
const showAddDialog = ref(false)
const selectedId = ref<number | null>(null)

async function load() {
  loading.value = true
  try {
    items.value = await fetchResponsibles(props.locationId)
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  } finally {
    loading.value = false
  }
}

async function openAdd() {
  try {
    available.value = await fetchAvailableResponsibles(props.locationId)
    selectedId.value = available.value[0]?.id ?? null
    showAddDialog.value = true
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  }
}

async function handleAdd() {
  if (!selectedId.value) return
  try {
    await addResponsibles(props.locationId, [selectedId.value])
    toast.success(t('locations.responsible_added'))
    showAddDialog.value = false
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  }
}

async function handleRemove(r: LocationResponsible) {
  const ok = await confirmStore.ask({
    title: t('locations.responsible_removed'),
    message: t('locations.responsible_remove_confirm', { name: r.user?.name ?? '' }),
    confirmText: t('common.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await removeResponsible(props.locationId, r.id)
    toast.success(t('locations.responsible_removed'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  }
}

const hasAvailable = computed(() => available.value.length > 0)

function formatDate(s: string | null): string {
  if (!s) return ''
  try {
    return new Date(s).toLocaleDateString()
  } catch {
    return s
  }
}

onMounted(load)
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700 gap-3 flex-wrap">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('locations.responsibles_section') }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('locations.responsibles_section_desc') }}</p>
      </div>
      <button
        v-if="canManage"
        @click="openAdd"
        class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-sm rounded-lg"
      >
        + {{ t('locations.responsible_add') }}
      </button>
    </div>

    <div v-if="loading" class="p-10 text-center">
      <div class="animate-spin w-6 h-6 border-2 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
    </div>

    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500 dark:text-gray-400">
      {{ t('locations.responsibles_empty') }}
    </div>

    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
      <li v-for="r in items" :key="r.id" class="p-4 flex items-center gap-3">
        <div class="shrink-0 w-10 h-10 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 flex items-center justify-center font-semibold text-sm overflow-hidden">
          <img v-if="r.user?.avatar_url" :src="r.user.avatar_url" :alt="r.user.name" class="w-full h-full object-cover" />
          <span v-else>{{ r.user?.name?.charAt(0).toUpperCase() }}</span>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <ContactCard
              v-if="r.user"
              :name="r.user.name"
              :role="r.role?.name"
              :phone="r.user.phone"
              :email="r.user.email"
              :avatar-url="r.user.avatar_url"
            >
              <span class="font-medium">{{ r.user.name }}</span>
            </ContactCard>
            <span
              v-if="r.role"
              class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
            >
              {{ r.role.name }}
            </span>
          </div>
          <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ t('locations.responsible_assigned_at') }}: {{ formatDate(r.assigned_at) }}
          </div>
        </div>
        <button
          v-if="canManage"
          @click="handleRemove(r)"
          class="p-2 text-gray-400 hover:text-red-600"
          :title="t('common.delete')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
          </svg>
        </button>
      </li>
    </ul>

    <!-- Add dialog -->
    <div
      v-if="showAddDialog"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="showAddDialog = false"
    >
      <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('locations.responsible_add') }}</h2>
        </div>
        <div class="p-5">
          <div v-if="!hasAvailable" class="text-sm text-gray-500 dark:text-gray-400">
            {{ t('locations.responsible_no_available') }}
          </div>
          <div v-else>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.responsible_select_user') }}
            </label>
            <select
              v-model="selectedId"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
            >
              <option v-for="m in available" :key="m.id" :value="m.id">
                {{ m.user?.name }}<span v-if="m.role"> — {{ m.role.name }}</span>
              </option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 p-5 border-t border-gray-200 dark:border-gray-700">
          <button
            @click="showAddDialog = false"
            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            v-if="hasAvailable"
            @click="handleAdd"
            :disabled="!selectedId"
            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white text-sm font-medium rounded-lg"
          >
            {{ t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
