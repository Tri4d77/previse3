<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { fetchUsers, fetchRoles, fetchOrganizations, type UsersListParams, type SimpleRole, type SimpleOrganization } from '@/services/users'
import { useAuthStore } from '@/stores/auth'
import type { User, PaginatedResponse } from '@/types'
import InviteUserModal from '@/components/common/InviteUserModal.vue'
import InvitationSuccessModal from '@/components/common/InvitationSuccessModal.vue'

const { t } = useI18n()
const authStore = useAuthStore()

// State
const users = ref<User[]>([])
const pagination = ref<PaginatedResponse<User>['meta'] | null>(null)
const roles = ref<SimpleRole[]>([])
const organizations = ref<SimpleOrganization[]>([])
const loading = ref(false)
const loadError = ref('')

// Szűrők
const search = ref('')
const filterRole = ref<string>('')
const filterStatus = ref<string>('') // '', 'active', 'inactive'
const filterOrganizationId = ref<number | ''>('')
const currentPage = ref(1)
const perPage = ref(25)
const sortField = ref('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

// A szervezet szűrő csak akkor látszik, ha több mint 1 szervezet elérhető
const showOrganizationFilter = computed(() => organizations.value.length > 1)

// Modal state
const showInviteModal = ref(false)
const invitationSuccess = ref<{
  invitationUrl: string
  userName: string
  userEmail: string
} | null>(null)

// ========== METÓDUSOK ==========

async function loadUsers() {
  loading.value = true
  loadError.value = ''

  try {
    const params: UsersListParams = {
      page: currentPage.value,
      per_page: perPage.value,
      sort: sortField.value,
      order: sortOrder.value,
    }
    if (search.value) params.search = search.value
    if (filterRole.value) params.role = filterRole.value
    if (filterStatus.value === 'active') params.is_active = true
    else if (filterStatus.value === 'inactive') params.is_active = false
    if (filterOrganizationId.value !== '') params.organization_id = Number(filterOrganizationId.value)

    const response = await fetchUsers(params)
    users.value = response.data
    pagination.value = response.meta
  } catch (err: any) {
    loadError.value = err.response?.data?.message || 'Hiba a felhasználók betöltésekor.'
  } finally {
    loading.value = false
  }
}

async function loadRoles() {
  try {
    roles.value = await fetchRoles()
  } catch {
    // Silent fail
  }
}

async function loadOrganizations() {
  try {
    organizations.value = await fetchOrganizations()
  } catch {
    // Silent fail
  }
}

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadUsers()
  }, 400)
})

watch([filterRole, filterStatus, filterOrganizationId], () => {
  currentPage.value = 1
  loadUsers()
})

watch(currentPage, loadUsers)

function onSort(field: string) {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = 'asc'
  }
  loadUsers()
}

function clearFilters() {
  search.value = ''
  filterRole.value = ''
  filterStatus.value = ''
  filterOrganizationId.value = ''
  currentPage.value = 1
  loadUsers()
}

function onInviteSuccess(data: { invitationUrl: string; userName: string; userEmail: string }) {
  showInviteModal.value = false
  invitationSuccess.value = data
  loadUsers() // Újratöltjük a listát
}

// ========== SZÁMÍTOTT ÉRTÉKEK ==========

const stats = computed(() => {
  const total = pagination.value?.total || 0
  const active = users.value.filter(u => u.is_active).length
  const inactive = users.value.filter(u => !u.is_active).length
  return { total, active, inactive }
})

const hasActiveFilters = computed(() =>
  search.value !== '' || filterRole.value !== '' || filterStatus.value !== '' || filterOrganizationId.value !== ''
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

onMounted(() => {
  loadUsers()
  loadRoles()
  loadOrganizations()
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
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('users.total') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wider font-medium">{{ t('users.active') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.active }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('users.inactive') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.inactive }}</p>
      </div>
    </div>

    <!-- Szűrők -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Keresés -->
        <div class="relative" :class="showOrganizationFilter ? '' : 'lg:col-span-2'">
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

        <!-- Szervezet szűrő (csak több szervezet esetén: szuper-admin / ügyfelekkel rendelkező előfizető) -->
        <select
          v-if="showOrganizationFilter"
          v-model="filterOrganizationId"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option :value="''">Minden szervezet</option>
          <option v-for="o in organizations" :key="o.id" :value="o.id">
            {{ o.name }}{{ o.type === 'platform' ? ' (Platform)' : (o.type === 'client' ? ' — ügyfél' : '') }}
          </option>
        </select>

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
          <option value="active">{{ t('users.active') }}</option>
          <option value="inactive">{{ t('users.inactive') }}</option>
        </select>
      </div>
      <div v-if="hasActiveFilters" class="mt-3">
        <button @click="clearFilters" class="text-xs text-teal-600 dark:text-teal-400 hover:underline">
          Szűrők törlése
        </button>
      </div>
    </div>

    <!-- Táblázat -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div v-if="loadError" class="p-6 text-center text-red-600 dark:text-red-400">
        {{ loadError }}
      </div>

      <div v-else-if="loading && users.length === 0" class="p-12 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
        <p class="text-sm text-gray-500 mt-3">{{ t('common.loading') }}</p>
      </div>

      <div v-else-if="users.length === 0" class="p-12 text-center text-gray-500 dark:text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
        <p class="text-sm">{{ t('common.no_data') }}</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-white" @click="onSort('name')">
                <div class="flex items-center gap-1">
                  Felhasználó
                  <svg v-if="sortField === 'name'" class="w-3 h-3" :class="sortOrder === 'asc' ? '' : 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                  </svg>
                </div>
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ t('common.role') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ t('common.status') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-white" @click="onSort('last_login_at')">
                <div class="flex items-center gap-1">
                  {{ t('users.last_login') }}
                  <svg v-if="sortField === 'last_login_at'" class="w-3 h-3" :class="sortOrder === 'asc' ? '' : 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                  </svg>
                </div>
              </th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="user in users"
              :key="user.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
            >
              <!-- Felhasználó -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-teal-600 flex items-center justify-center text-xs font-semibold text-white flex-shrink-0">
                    {{ user.initials }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                  </div>
                </div>
              </td>

              <!-- Szerepkör -->
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="roleBadgeClass(user.role.slug)"
                >
                  {{ user.role.name }}
                </span>
              </td>

              <!-- Státusz -->
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs">
                  <span
                    class="w-2 h-2 rounded-full"
                    :class="user.is_active ? 'bg-green-500' : 'bg-gray-400'"
                  ></span>
                  <span class="text-gray-700 dark:text-gray-300">
                    {{ user.is_active ? t('users.active') : t('users.inactive') }}
                  </span>
                </span>
              </td>

              <!-- Utolsó belépés -->
              <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                {{ formatDate(user.last_login_at) }}
              </td>

              <!-- Műveletek (placeholder - következő lépés) -->
              <td class="px-4 py-3 text-right">
                <button class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" title="Következő lépésben implementáljuk">
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

    <!-- Invitation URL success modal -->
    <InvitationSuccessModal
      v-if="invitationSuccess"
      :invitation-url="invitationSuccess.invitationUrl"
      :user-name="invitationSuccess.userName"
      :user-email="invitationSuccess.userEmail"
      @close="invitationSuccess = null"
    />
  </div>
</template>
