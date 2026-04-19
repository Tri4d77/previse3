<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  fetchOrganizationsTree,
  setOrganizationStatus,
  type OrganizationTreeNode,
  type OrganizationItem,
  type OrganizationStatus,
  type OrganizationType,
} from '@/services/organizations'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import CreateOrganizationModal from '@/components/common/CreateOrganizationModal.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import OrgRow from '@/components/common/OrgRow.vue'

const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToastStore()

const tree = ref<OrganizationTreeNode[]>([])
const loading = ref(false)
const loadError = ref('')

// Szűrők
const searchInput = ref('')
const filterType = ref<'' | OrganizationType>('')
const filterStatus = ref<'' | OrganizationStatus>('')

// Expand state
const expandedNodes = ref<Set<number>>(new Set())

// Create modal
const showCreateModal = ref(false)
const createDefaultType = ref<'subscriber' | 'client'>('subscriber')
const createDefaultParentId = ref<number | null>(null)

// Status change
const statusChangeOrg = ref<OrganizationItem | null>(null)
const statusChangeNew = ref<OrganizationStatus>('active')
const statusChangeLoading = ref(false)
const statusMenuOpenId = ref<number | null>(null)

// ========== LOAD ==========

async function loadTree() {
  loading.value = true
  loadError.value = ''
  try {
    const params: any = {}
    if (searchInput.value) params.search = searchInput.value
    if (filterType.value) params.type = filterType.value
    if (filterStatus.value) params.status = filterStatus.value

    tree.value = await fetchOrganizationsTree(params)

    if (searchInput.value || filterType.value || filterStatus.value) {
      expandAll()
    } else {
      setDefaultExpanded()
    }
  } catch (err: any) {
    loadError.value = err.response?.data?.message || 'Hiba a szervezetek betöltésekor.'
  } finally {
    loading.value = false
  }
}

function setDefaultExpanded() {
  // Csak a root (Platform / fő előfizető) legyen nyitva - a subscriber-ek csukva
  const newSet = new Set<number>()
  for (const root of tree.value) {
    newSet.add(root.id)
  }
  expandedNodes.value = newSet
}

function expandAll() {
  const newSet = new Set<number>()
  const walk = (nodes: OrganizationTreeNode[]) => {
    for (const n of nodes) {
      newSet.add(n.id)
      if (n.children.length) walk(n.children)
    }
  }
  walk(tree.value)
  expandedNodes.value = newSet
}

function toggleExpand(id: number) {
  const newSet = new Set(expandedNodes.value)
  if (newSet.has(id)) newSet.delete(id)
  else newSet.add(id)
  expandedNodes.value = newSet
}

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(searchInput, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadTree(), 400)
})
watch([filterType, filterStatus], () => loadTree())

// ========== CREATE ==========

function openCreate(type: 'subscriber' | 'client' = 'subscriber', parentId: number | null = null) {
  createDefaultType.value = type
  createDefaultParentId.value = parentId
  showCreateModal.value = true
}

function onCreateSuccess(org: OrganizationItem) {
  showCreateModal.value = false
  toast.success('Szervezet létrehozva', org.name)
  loadTree()
}

// ========== STATUS CHANGE ==========

function toggleStatusMenu(id: number) {
  statusMenuOpenId.value = statusMenuOpenId.value === id ? null : id
}

function askStatusChange(org: OrganizationItem, newStatus: OrganizationStatus) {
  statusMenuOpenId.value = null
  if (org.type === 'platform') {
    toast.error('Nem módosítható', 'A Platform szervezet státusza nem módosítható.')
    return
  }
  if (org.status === newStatus) return
  statusChangeOrg.value = org
  statusChangeNew.value = newStatus
}

async function handleStatusChange() {
  if (!statusChangeOrg.value) return
  statusChangeLoading.value = true
  try {
    const updated = await setOrganizationStatus(statusChangeOrg.value.id, statusChangeNew.value)
    const label = statusChangeNew.value === 'active' ? 'Aktív'
      : statusChangeNew.value === 'inactive' ? 'Inaktív'
      : 'Megszűnt'
    toast.success('Státusz frissítve', `${updated.name} → ${label}`)
    statusChangeOrg.value = null
    loadTree()
  } catch (err: any) {
    toast.error('Hiba', err.response?.data?.message || 'A művelet nem sikerült.')
  } finally {
    statusChangeLoading.value = false
  }
}

function clearFilters() {
  searchInput.value = ''
  filterType.value = ''
  filterStatus.value = ''
}

const hasActiveFilters = computed(() =>
  !!searchInput.value || !!filterType.value || !!filterStatus.value
)

// Click outside status menu
function handleClickOutside(event: MouseEvent) {
  if (statusMenuOpenId.value === null) return
  const target = event.target as HTMLElement
  if (!target.closest('.status-menu-wrapper')) {
    statusMenuOpenId.value = null
  }
}

onMounted(() => {
  loadTree()
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div>
    <!-- Cím + akció -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Szervezetek</h1>
        <nav class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          <span>{{ t('common.home') }}</span>
          <span class="mx-1">/</span>
          <span>{{ authStore.isSuperAdmin || authStore.isImpersonation ? 'Szuper-admin' : t('nav.admin') }}</span>
          <span class="mx-1">/</span>
          <span class="text-gray-700 dark:text-gray-200">Szervezetek</span>
        </nav>
      </div>
      <button
        v-if="authStore.canManageOrganizations"
        @click="openCreate(authStore.isSuperAdmin || authStore.isImpersonation ? 'subscriber' : 'client')"
        class="mt-3 sm:mt-0 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg shadow-sm flex items-center gap-2 text-sm transition-colors"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        {{ authStore.isSuperAdmin || authStore.isImpersonation ? 'Új szervezet' : 'Új ügyfél szervezet' }}
      </button>
    </div>

    <!-- Info -->
    <div v-if="authStore.isSuperAdmin || authStore.isImpersonation" class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg flex items-start gap-2">
      <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
      </svg>
      <p class="text-xs text-amber-800 dark:text-amber-200">
        <strong>Szuper-admin</strong> módban látod az összes szervezetet. Új előfizető szervezetet a Platform alá hozhatsz létre, ügyfél szervezeteket pedig az előfizetők alá.
      </p>
    </div>
    <div v-else class="mb-4 p-3 bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-lg flex items-start gap-2">
      <svg class="w-4 h-4 text-teal-600 dark:text-teal-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-xs text-teal-800 dark:text-teal-200">
        Itt kezelheted a saját szervezetedet és az alá tartozó ügyfél-szervezeteket. Új ügyfél-szervezet felvétele, státusz-módosítás.
      </p>
    </div>

    <!-- Szűrők -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <!-- Keresés -->
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
          <input
            v-model="searchInput"
            type="text"
            placeholder="Keresés név alapján..."
            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
          />
        </div>
        <!-- Típus -->
        <select
          v-model="filterType"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option value="">Minden típus</option>
          <option v-if="authStore.isSuperAdmin || authStore.isImpersonation" value="platform">Platform</option>
          <option value="subscriber">Előfizetők</option>
          <option value="client">Ügyfelek</option>
        </select>
        <!-- Státusz -->
        <select
          v-model="filterStatus"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option value="">Minden státusz</option>
          <option value="active">Aktív</option>
          <option value="inactive">Inaktív</option>
          <option value="terminated">Megszűnt</option>
        </select>
      </div>
      <div class="mt-3 flex items-center justify-between">
        <button v-if="hasActiveFilters" @click="clearFilters" class="text-xs text-teal-600 dark:text-teal-400 hover:underline">
          Szűrők törlése
        </button>
        <span v-else></span>
        <div class="flex items-center gap-2 text-xs">
          <button @click="expandAll" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
            Összes kibontása
          </button>
          <span class="text-gray-300 dark:text-gray-600">|</span>
          <button @click="setDefaultExpanded" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
            Alap nézet
          </button>
        </div>
      </div>
    </div>

    <!-- Tartalom -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div v-if="loadError" class="p-6 text-center text-red-600 dark:text-red-400">{{ loadError }}</div>

      <div v-else-if="loading && tree.length === 0" class="p-12 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
        <p class="text-sm text-gray-500 mt-3">{{ t('common.loading') }}</p>
      </div>

      <div v-else-if="tree.length === 0" class="p-12 text-center text-gray-500 dark:text-gray-400">
        <p class="text-sm">{{ hasActiveFilters ? 'Nincs találat a szűrőknek megfelelően.' : 'Nincs megjeleníthető szervezet.' }}</p>
      </div>

      <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
        <OrgRow
          v-for="root in tree"
          :key="root.id"
          :node="root"
          :depth="0"
          :expanded-nodes="expandedNodes"
          :is-super-admin="authStore.isSuperAdmin || authStore.isImpersonation"
          :can-manage="authStore.canManageOrganizations"
          :status-menu-open-id="statusMenuOpenId"
          @toggle-expand="toggleExpand"
          @toggle-status-menu="toggleStatusMenu"
          @ask-status-change="askStatusChange"
          @add-client="(parentId) => openCreate('client', parentId)"
        />
      </div>
    </div>

    <!-- Create modal -->
    <CreateOrganizationModal
      v-if="showCreateModal"
      :default-type="createDefaultType"
      :default-parent-id="createDefaultParentId"
      @close="showCreateModal = false"
      @success="onCreateSuccess"
    />

    <!-- Status change confirm -->
    <ConfirmDialog
      v-if="statusChangeOrg"
      :title="statusChangeNew === 'terminated' ? 'Szervezet megszüntetése' : (statusChangeNew === 'inactive' ? 'Szervezet inaktiválása' : 'Szervezet aktiválása')"
      :message="statusChangeNew === 'terminated'
        ? `Biztosan megszünteted a(z) '${statusChangeOrg.name}' szervezetet?\n\nEz azt jelzi, hogy az előfizetés / kapcsolat lezárult. A felhasználók nem tudnak belépni. Visszaállítható.`
        : statusChangeNew === 'inactive'
          ? `Biztosan inaktiválod a(z) '${statusChangeOrg.name}' szervezetet?\n\nAz inaktív szervezet felhasználói nem tudnak belépni. Átmeneti állapot, bármikor visszaaktiválható.`
          : `Biztosan aktiválod a(z) '${statusChangeOrg.name}' szervezetet?`"
      :confirm-text="statusChangeNew === 'terminated' ? 'Megszüntetés' : (statusChangeNew === 'inactive' ? 'Inaktiválás' : 'Aktiválás')"
      :variant="statusChangeNew === 'terminated' ? 'danger' : (statusChangeNew === 'inactive' ? 'warning' : 'info')"
      :loading="statusChangeLoading"
      @confirm="handleStatusChange"
      @cancel="statusChangeOrg = null"
    />
  </div>
</template>
