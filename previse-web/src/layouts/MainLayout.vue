<template>
  <!-- Mobile sidebar overlay -->
  <div
    v-if="sidebarOpen"
    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
    @click="sidebarOpen = false"
  />

  <div class="min-h-screen flex bg-gray-50 dark:bg-gray-900">
    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-800 flex flex-col transition-transform duration-300 lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-700">
        <svg class="w-8 h-8 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
        </svg>
        <span class="text-xl font-bold text-white tracking-tight">Previse</span>
      </div>

      <!-- Organization info -->
      <div class="px-4 py-3">
        <div class="rounded-lg bg-slate-700/50 px-3 py-2">
          <p class="text-sm font-medium text-white truncate">{{ authStore.organizationName }}</p>
          <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-teal-600/30 text-teal-300">
            {{ authStore.organizationType }}
          </span>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-1">
        <!-- Fomenu -->
        <p class="px-3 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.main_menu') }}
        </p>
        <router-link
          v-for="item in mainMenuItems"
          :key="item.route"
          :to="{ name: item.route }"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="isActive(item.route) ? 'bg-teal-600/20 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
          @click="sidebarOpen = false"
        >
          <span v-html="item.icon" class="w-5 h-5 shrink-0" :class="isActive(item.route) ? 'text-teal-400' : 'text-slate-400'" />
          <span class="truncate">{{ t(item.label) }}</span>
          <span
            v-if="item.badge"
            class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-semibold rounded-full bg-teal-500 text-white"
          >
            {{ item.badge }}
          </span>
        </router-link>

        <!-- Uzleti -->
        <p class="px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.business') }}
        </p>
        <a
          v-for="item in businessItems"
          :key="item.route"
          href="#"
          @click.prevent=""
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-500 cursor-not-allowed opacity-60"
          :title="'Hamarosan (későbbi fázis)'"
        >
          <span v-html="item.icon" class="w-5 h-5 shrink-0 text-slate-500" />
          <span class="truncate">{{ t(item.label) }}</span>
        </a>

        <!-- Epulet -->
        <p class="px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.facility') }}
        </p>
        <a
          v-for="item in facilityItems"
          :key="item.route"
          href="#"
          @click.prevent=""
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-500 cursor-not-allowed opacity-60"
          :title="'Hamarosan (későbbi fázis)'"
        >
          <span v-html="item.icon" class="w-5 h-5 shrink-0 text-slate-500" />
          <span class="truncate">{{ t(item.label) }}</span>
        </a>

        <!-- Kiegeszito -->
        <p class="px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.extra') }}
        </p>
        <a
          v-for="item in extraItems"
          :key="item.route"
          href="#"
          @click.prevent=""
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-500 cursor-not-allowed opacity-60"
          :title="'Hamarosan (későbbi fázis)'"
        >
          <span v-html="item.icon" class="w-5 h-5 shrink-0 text-slate-500" />
          <span class="truncate">{{ t(item.label) }}</span>
        </a>

        <!-- Admin -->
        <template v-if="authStore.hasAnyPermission('users.read', 'users.manage_roles', 'settings.manage_organization')">
          <p class="px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
            {{ t('nav.admin') }}
          </p>
          <router-link
            v-if="authStore.hasPermission('users.read')"
            :to="{ name: 'admin-users' }"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin-users') ? 'bg-teal-600/20 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
            @click="sidebarOpen = false"
          >
            <svg class="w-5 h-5 shrink-0" :class="isActive('admin-users') ? 'text-teal-400' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            <span class="truncate">{{ t('nav.users') }}</span>
          </router-link>
          <router-link
            v-if="authStore.hasPermission('users.manage_roles')"
            :to="{ name: 'admin-roles' }"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin-roles') ? 'bg-teal-600/20 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
            @click="sidebarOpen = false"
          >
            <svg class="w-5 h-5 shrink-0" :class="isActive('admin-roles') ? 'text-teal-400' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
            <span class="truncate">{{ t('nav.roles') }}</span>
          </router-link>
          <router-link
            v-if="authStore.hasPermission('settings.manage_organization')"
            :to="{ name: 'admin-organization' }"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin-organization') ? 'bg-teal-600/20 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
            @click="sidebarOpen = false"
          >
            <svg class="w-5 h-5 shrink-0" :class="isActive('admin-organization') ? 'text-teal-400' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="truncate">{{ t('nav.settings') }}</span>
          </router-link>
        </template>
      </nav>
    </aside>

    <!-- Main wrapper -->
    <div class="flex-1 flex flex-col lg:ml-64">
      <!-- Header -->
      <header class="sticky top-0 z-30 h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-4 sm:px-6">
        <!-- Left side -->
        <div class="flex items-center gap-3">
          <!-- Hamburger (mobile) -->
          <button
            class="lg:hidden p-2 -ml-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
            @click="sidebarOpen = !sidebarOpen"
          >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>

          <!-- Search -->
          <div class="hidden sm:flex items-center">
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <input
                type="text"
                :placeholder="t('common.search')"
                class="w-64 pl-10 pr-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent"
              />
            </div>
          </div>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-2">
          <!-- Theme toggle -->
          <button
            class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
            @click="toggleTheme"
          >
            <!-- Sun icon (shown in dark mode) -->
            <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
            </svg>
            <!-- Moon icon (shown in light mode) -->
            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
            </svg>
          </button>

          <!-- Messages -->
          <button class="relative p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
            <span class="absolute top-1 right-1 w-2 h-2 bg-teal-500 rounded-full" />
          </button>

          <!-- Notifications -->
          <button class="relative p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <span class="absolute top-1 right-1 flex items-center justify-center min-w-[16px] h-4 px-1 text-[10px] font-bold rounded-full bg-red-500 text-white">3</span>
          </button>

          <!-- Profile dropdown -->
          <div class="relative ml-1">
            <button
              class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
              @click="profileOpen = !profileOpen"
            >
              <div class="w-8 h-8 rounded-full bg-teal-600 flex items-center justify-center text-sm font-semibold text-white">
                {{ authStore.userInitials }}
              </div>
              <div class="hidden md:block text-left">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate max-w-[120px]">{{ authStore.userName }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[120px]">{{ authStore.userRole }}</p>
              </div>
              <svg class="hidden md:block w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <!-- Dropdown menu -->
            <Transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <div
                v-if="profileOpen"
                class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 py-1 z-50"
              >
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">{{ authStore.userName }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ authStore.userRole }}</p>
                </div>
                <router-link
                  :to="{ name: 'profile' }"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                  @click="profileOpen = false"
                >
                  <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                  </svg>
                  {{ t('profile.tab_profile') }}
                </router-link>
                <div class="border-t border-gray-100 dark:border-gray-700 my-1" />
                <button
                  class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700"
                  @click="handleLogout"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                  </svg>
                  {{ t('auth.logout') }}
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </header>

      <!-- Main content -->
      <main class="flex-1 p-4 sm:p-6">
        <RouterView />
      </main>

      <!-- Footer -->
      <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 sm:px-6 py-3">
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
          <span>{{ t('app.copyright') }}</span>
          <span>{{ authStore.organizationName }}</span>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter, RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

// UI state
const sidebarOpen = ref(false)
const profileOpen = ref(false)
const isDark = ref(document.documentElement.classList.contains('dark'))

// Navigation helpers
function isActive(routeName: string): boolean {
  return route.name === routeName
}

// Icon SVGs as raw strings for v-html usage
const icons = {
  dashboard: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>',
  tickets: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" /></svg>',
  tasks: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
  projects: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>',
  issues: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>',
  suggestions: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>',
  documents: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
  locations: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>',
  assets: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.1-3.06a1.5 1.5 0 010-2.58l5.1-3.06a1.5 1.5 0 011.639.02l4.907 3.06a1.5 1.5 0 010 2.56l-4.907 3.06a1.5 1.5 0 01-1.639.02zM5.25 12.75l5.1 3.06a1.5 1.5 0 001.639-.02l4.907-3.06" /><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 15.75l5.1 3.06a1.5 1.5 0 001.639-.02l4.907-3.06" /></svg>',
  maintenance: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.1-3.06M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>',
  contracts: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
  messages: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>',
}

// Replace maintenance icon with wrench
icons.maintenance = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75a4.5 4.5 0 01-4.884 4.484c-1.076-.091-2.264.071-2.95.904l-7.152 8.684a2.548 2.548 0 11-3.586-3.586l8.684-7.152c.833-.686.995-1.874.904-2.95a4.5 4.5 0 016.336-4.486l-3.276 3.276a3.004 3.004 0 002.25 2.25l3.276-3.276c.256.565.398 1.192.398 1.852z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.867 19.125h.008v.008h-.008v-.008z" /></svg>'

interface NavItem {
  route: string
  label: string
  icon: string
  badge?: number
}

const mainMenuItems: NavItem[] = [
  { route: 'dashboard', label: 'nav.dashboard', icon: icons.dashboard },
]

const businessItems: NavItem[] = [
  { route: 'tickets', label: 'nav.tickets', icon: icons.tickets },
  { route: 'tasks', label: 'nav.tasks', icon: icons.tasks },
  { route: 'projects', label: 'nav.projects', icon: icons.projects },
  { route: 'issues', label: 'nav.issues', icon: icons.issues },
  { route: 'suggestions', label: 'nav.suggestions', icon: icons.suggestions },
  { route: 'documents', label: 'nav.documents', icon: icons.documents },
]

const facilityItems: NavItem[] = [
  { route: 'locations', label: 'nav.locations', icon: icons.locations },
  { route: 'assets', label: 'nav.assets', icon: icons.assets },
  { route: 'maintenance', label: 'nav.maintenance', icon: icons.maintenance },
]

const extraItems: NavItem[] = [
  { route: 'contracts', label: 'nav.contracts', icon: icons.contracts },
  { route: 'messages', label: 'nav.messages', icon: icons.messages },
]

// Theme toggle
function toggleTheme(): void {
  isDark.value = !isDark.value
  if (isDark.value) {
    document.documentElement.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  } else {
    document.documentElement.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  }
}

// Logout
async function handleLogout(): Promise<void> {
  profileOpen.value = false
  await authStore.logout()
  router.push('/login')
}

// Click outside handler to close dropdowns
function handleClickOutside(event: MouseEvent): void {
  const target = event.target as HTMLElement
  if (profileOpen.value && !target.closest('.relative')) {
    profileOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)

  // Initialize theme from localStorage
  const savedTheme = localStorage.getItem('theme')
  if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
    isDark.value = true
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
