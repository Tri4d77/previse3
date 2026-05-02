<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  fetchLocations,
  fetchLocationTypes,
  deleteLocation,
  setLocationStatus,
  type LocationItem,
  type LocationsListParams,
  type LocationsListResponse,
  type LocationType,
  type LocationStatus,
} from '@/services/locations'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import LocationCard from '@/components/locations/LocationCard.vue'
import LocationFormModal from '@/components/locations/LocationFormModal.vue'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToastStore()

// Nézet (list / cards), perzisztálva user_settings.locations_view-ba
type ViewMode = 'list' | 'cards'
const viewMode = ref<ViewMode>(
  (authStore.user?.settings?.locations_view as ViewMode) ?? 'list'
)

async function setViewMode(mode: ViewMode) {
  viewMode.value = mode
  // Mentsd a user_settings-be, ha be van jelentkezve
  if (authStore.isAuthenticated) {
    try {
      const api = (await import('@/services/api')).default
      await api.put('/settings', { locations_view: mode })
      if (authStore.user?.settings) {
        authStore.user.settings.locations_view = mode
      }
    } catch { /* silent */ }
  }
}

// Lista state
const locations = ref<LocationItem[]>([])
const meta = ref<LocationsListResponse['meta'] | null>(null)
const loading = ref(false)
const types = ref<LocationType[]>([])

// Szűrők
const search = ref('')
const filterType = ref<string>('')
const filterStatus = ref<string>('active') // 'active' | 'all' | '0' | '1' | '2'
const includeDeleted = ref(false)
const currentPage = ref(1)
const perPage = ref(25)

// Modal state
const showFormModal = ref(false)
const editingLocation = ref<LocationItem | null>(null)

// ============== METÓDUSOK ==============

async function loadList() {
  loading.value = true
  try {
    const params: LocationsListParams = {
      page: currentPage.value,
      per_page: perPage.value,
      sort: 'name',
      order: 'asc',
      include_deleted: includeDeleted.value,
    }
    if (search.value) params.search = search.value
    if (filterType.value) params.type_id = parseInt(filterType.value)
    if (filterStatus.value) params.is_active = filterStatus.value as any

    const response = await fetchLocations(params)
    locations.value = response.data
    meta.value = response.meta
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  } finally {
    loading.value = false
  }
}

async function loadTypes() {
  try {
    types.value = await fetchLocationTypes()
  } catch { /* silent */ }
}

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadList()
  }, 400)
})

watch([filterType, filterStatus, includeDeleted], () => {
  currentPage.value = 1
  loadList()
})

watch(currentPage, loadList)

function clearFilters() {
  search.value = ''
  filterType.value = ''
  filterStatus.value = 'active'
  includeDeleted.value = false
  currentPage.value = 1
  loadList()
}

// Modal események
function onNewLocation() {
  editingLocation.value = null
  showFormModal.value = true
}

function onEditLocation(loc: LocationItem) {
  editingLocation.value = loc
  showFormModal.value = true
}

function onOpenLocation(loc: LocationItem) {
  router.push({ name: 'location-detail', params: { id: loc.id } })
}

function onLocationSaved(_: LocationItem) {
  showFormModal.value = false
  editingLocation.value = null
  loadList()
}

async function onDeleteLocation(loc: LocationItem) {
  if (!confirm(t('locations.confirm_delete', { name: loc.name }))) return
  try {
    await deleteLocation(loc.id)
    toast.success(t('locations.deleted'))
    loadList()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  }
}

async function onSetStatus(loc: LocationItem, status: LocationStatus) {
  try {
    await setLocationStatus(loc.id, status)
    toast.success(t('locations.updated'))
    loadList()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  }
}

// Statisztikák
const stats = computed(() => {
  const total = meta.value?.total || 0
  const active = locations.value.filter(l => l.is_active === 1).length
  const archived = locations.value.filter(l => l.is_active === 0).length
  return { total, active, archived }
})

const hasActiveFilters = computed(() =>
  search.value !== '' || filterType.value !== '' || filterStatus.value !== 'active' || includeDeleted.value
)

function statusLabel(s: LocationStatus): string {
  if (s === 1) return t('locations.status_active')
  if (s === 0) return t('locations.status_archived')
  return t('locations.status_terminated')
}

function statusDotClass(s: LocationStatus): string {
  if (s === 1) return 'bg-green-500'
  if (s === 0) return 'bg-amber-500'
  return 'bg-red-500'
}

onMounted(() => {
  loadList()
  loadTypes()
})
</script>

<template>
  <div class="p-6">
    <!-- Fejléc -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('locations.title') }}</h1>
        <nav class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          <span>{{ t('common.home') }}</span>
          <span class="mx-1">/</span>
          <span class="text-gray-700 dark:text-gray-200">{{ t('locations.breadcrumb') }}</span>
        </nav>
      </div>

      <div class="mt-3 sm:mt-0 flex items-center gap-2">
        <!-- Nézet váltó -->
        <div class="inline-flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
          <button
            @click="setViewMode('list')"
            class="p-1.5 rounded transition-colors"
            :class="viewMode === 'list' ? 'bg-white dark:bg-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
            :title="t('locations.view_list')"
            :aria-label="t('locations.view_list')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
          <button
            @click="setViewMode('cards')"
            class="p-1.5 rounded transition-colors"
            :class="viewMode === 'cards' ? 'bg-white dark:bg-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
            :title="t('locations.view_cards')"
            :aria-label="t('locations.view_cards')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
          </button>
        </div>

        <button
          @click="onNewLocation"
          class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg shadow-sm flex items-center gap-2 text-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          {{ t('locations.new') }}
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('locations.stat_total') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wider font-medium">{{ t('locations.stat_active') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.active }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider font-medium">{{ t('locations.stat_archived') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.archived }}</p>
      </div>
    </div>

    <!-- Szűrők -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Keresés -->
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
          <input
            v-model="search"
            type="text"
            :placeholder="t('locations.search_placeholder')"
            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
        </div>

        <select
          v-model="filterType"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option value="">{{ t('locations.filter_all_types') }}</option>
          <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>

        <select
          v-model="filterStatus"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option value="active">{{ t('locations.show_only_active') }}</option>
          <option value="0">{{ t('locations.status_archived') }}</option>
          <option value="2">{{ t('locations.status_terminated') }}</option>
          <option value="all">{{ t('common.all') }}</option>
        </select>
      </div>

      <div v-if="hasActiveFilters" class="mt-3 flex items-center justify-end">
        <button @click="clearFilters" class="text-xs text-teal-600 dark:text-teal-400 hover:underline">
          {{ t('locations.clear_filters') }}
        </button>
      </div>
    </div>

    <!-- Loader -->
    <div v-if="loading && locations.length === 0" class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
      <div class="animate-spin w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
      <p class="text-sm text-gray-500 mt-3">{{ t('common.loading') }}</p>
    </div>

    <!-- Üres állapot -->
    <div v-else-if="locations.length === 0" class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center text-gray-500 dark:text-gray-400">
      <svg class="w-16 h-16 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h6.75v-12L13.5 3 6.75 9V21" />
      </svg>
      <p class="text-base font-medium text-gray-700 dark:text-gray-300">{{ t('locations.empty_title') }}</p>
      <p class="text-sm mt-1">{{ t('locations.empty_subtitle') }}</p>
    </div>

    <!-- Kártya nézet -->
    <div v-else-if="viewMode === 'cards'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <LocationCard
        v-for="loc in locations"
        :key="loc.id"
        :location="loc"
        @edit="onEditLocation"
        @delete="onDeleteLocation"
        @open="onOpenLocation"
      />
    </div>

    <!-- Lista nézet -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ t('locations.code') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ t('locations.name') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ t('locations.type') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ t('locations.city') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ t('locations.status') }}
              </th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="loc in locations"
              :key="loc.id"
              @click="onOpenLocation(loc)"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer"
              :class="loc.is_deleted ? 'opacity-60' : ''"
            >
              <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ loc.code }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div v-if="loc.thumb_url" class="shrink-0 w-10 h-10 rounded bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <img :src="loc.thumb_url" :alt="loc.name" class="w-full h-full object-cover" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ loc.name }}</p>
                    <p v-if="loc.address" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ loc.address }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                {{ loc.type?.name ?? '—' }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ loc.city ?? '—' }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs">
                  <span class="w-2 h-2 rounded-full" :class="statusDotClass(loc.is_active)"></span>
                  <span class="text-gray-700 dark:text-gray-300">{{ statusLabel(loc.is_active) }}</span>
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  @click.stop="onEditLocation(loc)"
                  class="text-teal-600 hover:text-teal-700 text-xs font-medium"
                >
                  {{ t('locations.action_edit') }}
                </button>
                <button
                  v-if="loc.is_active === 1"
                  @click.stop="onSetStatus(loc, 0)"
                  class="ml-3 text-amber-600 hover:text-amber-700 text-xs font-medium"
                >
                  {{ t('locations.action_archive') }}
                </button>
                <button
                  v-if="loc.is_active === 0"
                  @click.stop="onSetStatus(loc, 1)"
                  class="ml-3 text-green-600 hover:text-green-700 text-xs font-medium"
                >
                  {{ t('locations.action_unarchive') }}
                </button>
                <button
                  @click.stop="onDeleteLocation(loc)"
                  class="ml-3 text-red-600 hover:text-red-700 text-xs font-medium"
                >
                  {{ t('locations.action_delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Lapozás -->
      <div v-if="meta && meta.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm">
        <p class="text-gray-500 dark:text-gray-400">{{ meta.from }}–{{ meta.to }} / {{ meta.total }}</p>
        <div class="flex items-center gap-1">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1 || loading"
            class="px-3 py-1.5 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg disabled:opacity-50"
          >
            {{ t('common.previous') }}
          </button>
          <span class="px-3 text-gray-500 dark:text-gray-400">{{ meta.current_page }} / {{ meta.last_page }}</span>
          <button
            @click="currentPage = Math.min(meta.last_page, currentPage + 1)"
            :disabled="currentPage === meta.last_page || loading"
            class="px-3 py-1.5 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg disabled:opacity-50"
          >
            {{ t('common.next') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <LocationFormModal
      v-if="showFormModal"
      :location="editingLocation"
      @close="showFormModal = false; editingLocation = null"
      @saved="onLocationSaved"
    />
  </div>
</template>
