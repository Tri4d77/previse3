<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  fetchMemberships,
  fetchRoles,
  type MembershipItem,
  type MembershipsListParams,
  type SimpleRole,
} from '@/services/memberships'
import { useAuthStore } from '@/stores/auth'
import type { PaginatedResponse } from '@/types'
import InviteUserModal from '@/components/common/InviteUserModal.vue'
import InvitationSuccessModal from '@/components/common/InvitationSuccessModal.vue'

const { t } = useI18n()
const authStore = useAuthStore()

// State
const memberships = ref<MembershipItem[]>([])
const pagination = ref<PaginatedResponse<MembershipItem>['meta'] | null>(null)
const roles = ref<SimpleRole[]>([])
const loading = ref(false)
const loadError = ref('')

// Szűrők
const search = ref('')
const filterRole = ref<string>('')
const filterStatus = ref<string>('') // '', 'active', 'inactive', 'pending'
const includeDeleted = ref(false)
const currentPage = ref(1)
const perPage = ref(25)
const sortField = ref('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

// Modal state
const showInviteModal = ref(false)
const invitationSuccess = ref<{
  invitationUrl: string
  userName: string
  userEmail: string
  isExistingUser: boolean
} | null>(null)

// ========== METÓDUSOK ==========

async function loadMemberships() {
  loading.value = true
  loadError.value = ''

  try {
    const params: MembershipsListParams = {
      page: currentPage.value,
      per_page: perPage.value,
      sort: sortField.value,
      order: sortOrder.value,
      include_deleted: includeDeleted.value,
    }
    if (search.value) params.search = search.value
    if (filterRole.value) params.role = filterRole.value
    if (filterStatus.value) params.status = filterStatus.value as any

    const response = await fetchMemberships(params)
    memberships.value = response.data
    pagination.value = response.meta
  } catch (err: any) {
    loadError.value = err.response?.data?.message || 'Hiba a tagok betöltésekor.'
  } finally {
    loading.value = false
  }
}

async function loadRoles() {
  try {
    roles.value = await fetchRoles()
  } catch {
    // silent
  }
}

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadMemberships()
  }, 400)
})

watch([filterRole, filterStatus, includeDeleted], () => {
  currentPage.value = 1
  loadMemberships()
})

watch(currentPage, loadMemberships)

function clearFilters() {
  search.value = ''
  filterRole.value = ''
  filterStatus.value = ''
  includeDeleted.value = false
  currentPage.value = 1
  loadMemberships()
}

function onInviteSuccess(data: { invitationUrl: string; userName: string; userEmail: string; isExistingUser: boolean }) {
  showInviteModal.value = false
  invitationSuccess.value = data
  loadMemberships()
}

// ========== SZÁMÍTOTT ÉRTÉKEK ==========

const stats = computed(() => {
  const total = pagination.value?.total || 0
  const active = memberships.value.filter(m => m.status === 'active').length
  const inactive = memberships.value.filter(m => m.status === 'inactive').length
  const pending = memberships.value.filter(m => m.status === 'pending' || m.status === 'expired').length
  return { total, active, inactive, pending }
})

const hasActiveFilters = computed(() =>
  search.value !== '' || filterRole.value !== '' || filterStatus.value !== '' || includeDeleted.value
)

// Dátum formázás
function formatDate(dateString: string | null): string {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('hu-HU', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })
}

// Szerepkör badge szín
function roleBadgeClass(slug: string): string {
  const map: Record<string, string> = {
    admin: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
    dispatcher: 'bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300',
    user: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    maintainer: 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300',
    recorder: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
  }
  return map[slug] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    active: 'Aktív',
    inactive: 'Inaktív',
    pending: 'Meghívva',
    expired: 'Lejárt meghívó',
    deleted: 'Törölt',
  }
  return map[status] || status
}

function statusDotClass(status: string): string {
  const map: Record<string, string> = {
    active: 'bg-green-500',
    inactive: 'bg-gray-400',
    pending: 'bg-amber-500',
    expired: 'bg-red-500',
    deleted: 'bg-gray-500',
  }
  return map[status] || 'bg-gray-400'
}

onMounted(() => {
  loadMemberships()
  loadRoles()
})
</script>

<template>
  <div>
    <!-- Oldalcím + akciógomb -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('users.title') }}</h1>
        <nav class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          <span>{{ t('common.home') }}</span>
          <span class="mx-1">/</span>
          <span>{{ t('nav.admin') }}</span>
          <span class="mx-1">/</span>
          <span class="text-gray-700 dark:text-gray-200">{{ t('users.title') }}</span>
        </nav>
      </div>
      <button
        @click="showInviteModal = true"
        class="mt-3 sm:mt-0 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg shadow-sm flex items-center gap-2 text-sm transition-colors"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        {{ t('users.invite') }}
      </button>
    </div>

    <!-- Stats kártyák -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Összes tag</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wider font-medium">Aktív</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.active }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider font-medium">Meghívva</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.pending }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Inaktív</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.inactive }}</p>
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
            placeholder="Keresés név, email..."
            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
          />
        </div>

        <!-- Szerepkör szűrő -->
        <select
          v-model="filterRole"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option value="">Összes szerepkör</option>
          <option v-for="r in roles" :key="r.id" :value="r.slug">{{ r.name }}</option>
        </select>

        <!-- Státusz szűrő -->
        <select
          v-model="filterStatus"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option value="">Minden státusz</option>
          <option value="active">Aktív</option>
          <option value="inactive">Inaktív</option>
          <option value="pending">Meghívva</option>
        </select>
      </div>

      <div class="mt-3 flex items-center justify-between">
        <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
          <input type="checkbox" v-model="includeDeleted" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
          Törölt tagok megjelenítése
        </label>
        <button v-if="hasActiveFilters" @click="clearFilters" class="text-xs text-teal-600 dark:text-teal-400 hover:underline">
          Szűrők törlése
        </button>
      </div>
    </div>

    <!-- Táblázat -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div v-if="loadError" class="p-6 text-center text-red-600 dark:text-red-400">
        {{ loadError }}
      </div>

      <div v-else-if="loading && memberships.length === 0" class="p-12 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
        <p class="text-sm text-gray-500 mt-3">{{ t('common.loading') }}</p>
      </div>

      <div v-else-if="memberships.length === 0" class="p-12 text-center text-gray-500 dark:text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
        <p class="text-sm">{{ t('common.no_data') }}</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Felhasználó</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ t('common.role') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ t('common.status') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Csatlakozott</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="m in memberships"
              :key="m.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
              :class="m.status === 'deleted' ? 'opacity-60' : ''"
            >
              <!-- Felhasználó -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-teal-600 flex items-center justify-center text-xs font-semibold text-white flex-shrink-0">
                    {{ m.user.initials }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate" :class="m.status === 'deleted' ? 'line-through' : ''">{{ m.user.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ m.user.email }}</p>
                  </div>
                </div>
              </td>

              <!-- Szerepkör -->
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="roleBadgeClass(m.role.slug)"
                >
                  {{ m.role.name }}
                </span>
              </td>

              <!-- Státusz -->
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs">
                  <span class="w-2 h-2 rounded-full" :class="statusDotClass(m.status)"></span>
                  <span class="text-gray-700 dark:text-gray-300">{{ statusLabel(m.status) }}</span>
                </span>
              </td>

              <!-- Csatlakozott -->
              <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                {{ m.joined_at ? formatDate(m.joined_at) : '-' }}
              </td>

              <!-- Műveletek (M3-ban készül) -->
              <td class="px-4 py-3 text-right">
                <button
                  class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                  title="Műveletek (M3-ban készül)"
                  disabled
                >
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Lapozás -->
        <div v-if="pagination && pagination.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm">
          <p class="text-gray-500 dark:text-gray-400">
            {{ pagination.from }}–{{ pagination.to }} / {{ pagination.total }}
          </p>
          <div class="flex items-center gap-1">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1 || loading"
              class="px-3 py-1.5 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Előző
            </button>
            <span class="px-3 text-gray-500 dark:text-gray-400">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              @click="currentPage = Math.min(pagination.last_page, currentPage + 1)"
              :disabled="currentPage === pagination.last_page || loading"
              class="px-3 py-1.5 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Következő
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Invite modal -->
    <InviteUserModal
      v-if="showInviteModal"
      @close="showInviteModal = false"
      @success="onInviteSuccess"
    />

    <!-- Success URL modal -->
    <InvitationSuccessModal
      v-if="invitationSuccess"
      :invitation-url="invitationSuccess.invitationUrl"
      :user-name="invitationSuccess.userName"
      :user-email="invitationSuccess.userEmail"
      :is-existing-user="invitationSuccess.isExistingUser"
      @close="invitationSuccess = null"
    />
  </div>
</template>
