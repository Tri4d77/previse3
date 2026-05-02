<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { fetchLocation, type LocationItem } from '@/services/locations'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import FloorsTab from '@/components/locations/FloorsTab.vue'
import ContactsTab from '@/components/locations/ContactsTab.vue'
import ResponsiblesTab from '@/components/locations/ResponsiblesTab.vue'

interface Props {
  id: number
}
const props = defineProps<Props>()

const { t } = useI18n()
const router = useRouter()
const toast = useToastStore()
const authStore = useAuthStore()

const location = ref<LocationItem | null>(null)
const loading = ref(true)

type TabKey = 'overview' | 'floors' | 'contacts' | 'responsibles' | 'documents'
const activeTab = ref<TabKey>('overview')

const tabs: { key: TabKey; labelKey: string; available: boolean }[] = [
  { key: 'overview', labelKey: 'locations.tab_overview', available: true },
  { key: 'floors', labelKey: 'locations.tab_floors', available: true },
  { key: 'contacts', labelKey: 'locations.tab_contacts', available: true },
  { key: 'responsibles', labelKey: 'locations.tab_responsibles', available: true },
  { key: 'documents', labelKey: 'locations.tab_documents', available: false },
]

const canManageFloors = computed(() => authStore.hasPermission('locations.manage_floors'))
const canManageRooms = computed(() => authStore.hasPermission('locations.manage_rooms'))
const canManageContacts = computed(() => authStore.hasPermission('locations.manage_contacts'))
const canManageResponsibles = computed(() => authStore.hasPermission('locations.manage_responsibles'))

async function loadLocation() {
  loading.value = true
  try {
    location.value = await fetchLocation(props.id)
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
    router.push({ name: 'locations' })
  } finally {
    loading.value = false
  }
}

function statusBadgeClass(status: number): string {
  if (status === 1) return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
  if (status === 0) return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'
  return 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
}

function statusLabel(status: number): string {
  if (status === 1) return t('locations.status_active')
  if (status === 0) return t('locations.status_archived')
  return t('locations.status_terminated')
}

onMounted(loadLocation)
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-6">
    <!-- Vissza a listához -->
    <nav class="mb-4">
      <router-link
        :to="{ name: 'locations' }"
        class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-teal-600 dark:hover:text-teal-400"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        {{ t('locations.back_to_list') }}
      </router-link>
    </nav>

    <!-- Loading -->
    <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
      <div class="animate-spin w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
    </div>

    <template v-else-if="location">
      <!-- Header card -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6 overflow-hidden">
        <div class="md:flex">
          <!-- Image -->
          <div class="md:w-72 md:h-48 h-40 bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0 overflow-hidden">
            <img v-if="location.image_url" :src="location.image_url" :alt="location.name" class="w-full h-full object-cover" />
            <svg v-else class="w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
          </div>
          <!-- Info -->
          <div class="p-6 flex-1">
            <div class="flex items-start justify-between gap-4 flex-wrap">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ location.name }}</h1>
                  <span class="text-sm text-gray-500 dark:text-gray-400 font-mono">[{{ location.code }}]</span>
                </div>
                <div class="mt-2 flex items-center gap-2 flex-wrap">
                  <span v-if="location.type"
                        class="text-xs px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300">
                    {{ location.type.name }}
                  </span>
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="statusBadgeClass(location.is_active)">
                    {{ statusLabel(location.is_active) }}
                  </span>
                </div>
                <p v-if="location.address || location.city" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  {{ [location.zip_code, location.city, location.address].filter(Boolean).join(', ') }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex gap-6 overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="tab.available && (activeTab = tab.key)"
            :disabled="!tab.available"
            class="whitespace-nowrap py-3 px-1 border-b-2 text-sm font-medium transition-colors"
            :class="[
              activeTab === tab.key
                ? 'border-teal-500 text-teal-600 dark:text-teal-400'
                : tab.available
                  ? 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'
                  : 'border-transparent text-gray-300 dark:text-gray-600 cursor-not-allowed',
            ]"
          >
            {{ t(tab.labelKey) }}
            <span v-if="!tab.available" class="ml-1 text-[10px] text-gray-400">({{ t('profile.coming_soon') }})</span>
          </button>
        </nav>
      </div>

      <!-- Tab content -->
      <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Alapadatok -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('locations.overview_basic') }}</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500 dark:text-gray-400">{{ t('locations.code') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100 font-mono">{{ location.code }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500 dark:text-gray-400">{{ t('locations.type') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ location.type?.name ?? t('locations.overview_no_data') }}</dd>
            </div>
            <div v-if="location.description" class="pt-2 border-t border-gray-100 dark:border-gray-700">
              <dt class="text-gray-500 dark:text-gray-400 mb-1">{{ t('locations.description') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ location.description }}</dd>
            </div>
          </dl>
        </div>

        <!-- Cím -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('locations.address') }}</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500 dark:text-gray-400">{{ t('locations.city') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ location.city ?? t('locations.overview_no_data') }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500 dark:text-gray-400">{{ t('locations.zip_code') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ location.zip_code ?? t('locations.overview_no_data') }}</dd>
            </div>
            <div v-if="location.address" class="pt-2 border-t border-gray-100 dark:border-gray-700">
              <dt class="text-gray-500 dark:text-gray-400 mb-1">{{ t('locations.address') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ location.address }}</dd>
            </div>
          </dl>
        </div>

        <!-- Geo -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('locations.overview_location') }}</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500 dark:text-gray-400">{{ t('locations.latitude') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100 font-mono">{{ location.latitude ?? t('locations.overview_no_data') }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500 dark:text-gray-400">{{ t('locations.longitude') }}</dt>
              <dd class="text-gray-900 dark:text-gray-100 font-mono">{{ location.longitude ?? t('locations.overview_no_data') }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Floors + Rooms tab -->
      <FloorsTab
        v-else-if="activeTab === 'floors'"
        :location-id="location.id"
        :can-manage-floors="canManageFloors"
        :can-manage-rooms="canManageRooms"
      />

      <!-- Contacts tab -->
      <ContactsTab
        v-else-if="activeTab === 'contacts'"
        :location-id="location.id"
        :can-manage="canManageContacts"
      />

      <!-- Responsibles tab -->
      <ResponsiblesTab
        v-else-if="activeTab === 'responsibles'"
        :location-id="location.id"
        :can-manage="canManageResponsibles"
      />
    </template>
  </div>
</template>
