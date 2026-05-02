<template>
  <!-- Mobile sidebar overlay -->
  <div
    v-if="sidebarOpenMobile"
    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
    @click="sidebarOpenMobile = false"
  />

  <div class="min-h-screen flex bg-gray-50 dark:bg-gray-900">
    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-50 bg-slate-800 flex flex-col transition-all duration-300 lg:translate-x-0 w-64"
      :class="[
        isSidebarExpanded ? 'lg:w-64' : 'lg:w-16',
        sidebarOpenMobile ? 'translate-x-0' : '-translate-x-full'
      ]"
      @mouseenter="sidebarCollapsed && (sidebarHover = true)"
      @mouseleave="sidebarHover = false"
    >
      <!-- Logo + Collapse button -->
      <div class="h-16 flex items-center gap-3 px-4 border-b border-slate-700 shrink-0">
        <svg class="w-8 h-8 text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
        </svg>
        <span
          v-show="isSidebarExpanded"
          class="text-xl font-bold text-white tracking-tight flex-1 whitespace-nowrap"
        >Previse</span>
        <!-- Desktop collapse button -->
        <button
          class="hidden lg:flex items-center justify-center w-7 h-7 rounded text-slate-400 hover:text-white hover:bg-slate-700 shrink-0"
          @click="sidebarCollapsed = !sidebarCollapsed"
          :title="sidebarCollapsed ? 'Sidebar kinyitása' : 'Sidebar becsukása'"
        >
          <svg v-if="!sidebarCollapsed" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
          </svg>
          <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
          </svg>
        </button>
      </div>

      <!-- Organization switcher -->
      <div v-show="isSidebarExpanded" class="px-4 py-3 shrink-0 relative">
        <button
          @click="toggleOrgSwitcher"
          class="w-full rounded-lg bg-slate-700/50 hover:bg-slate-700 px-3 py-2 transition-colors text-left"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
              <p class="text-sm font-medium text-white truncate">{{ authStore.currentOrganizationName }}</p>
              <div class="flex items-center gap-1 mt-1">
                <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-teal-600/30 text-teal-300">
                  {{ orgTypeLabel(authStore.currentOrganizationType) }}
                </span>
                <span v-if="authStore.isImpersonation" class="inline-block text-xs px-2 py-0.5 rounded-full bg-amber-500/30 text-amber-200">
                  szuper-admin
                </span>
              </div>
            </div>
            <svg v-if="hasSwitcher" class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
            </svg>
          </div>
        </button>

        <!-- Org switcher dropdown -->
        <div
          v-if="orgSwitcherOpen && hasSwitcher"
          class="absolute left-4 right-4 top-full mt-1 z-50 bg-slate-900 border border-slate-700 rounded-lg shadow-xl max-h-96 overflow-y-auto custom-scrollbar"
        >
          <!-- Szuper-admin: fa-struktúra -->
          <div v-if="authStore.isSuperAdmin">
            <div class="p-2 border-b border-slate-700">
              <input
                v-model="orgSearch"
                type="text"
                placeholder="Szervezet keresése..."
                class="w-full px-2 py-1 text-sm bg-slate-800 text-white placeholder-slate-500 rounded border border-slate-700 focus:outline-none focus:border-teal-500"
              />
            </div>
            <div v-if="orgTreeLoading" class="p-3 text-center">
              <div class="animate-spin w-5 h-5 border-2 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
            </div>
            <div v-else class="p-1">
              <!-- Vissza a Platform-ra, ha impersonation-ben van -->
              <button
                v-if="authStore.isImpersonation && platformNode"
                @click="handleExitOrg"
                class="w-full text-left px-3 py-2 text-sm text-teal-400 hover:bg-slate-800 rounded flex items-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
                Vissza a Platform-ra
              </button>
              <!-- Fa -->
              <OrgTreeNode
                v-for="root in filteredTree"
                :key="root.id"
                :node="root"
                :current-org-id="currentOrgId"
                :depth="0"
                @select="handleSelectOrg"
              />
              <div v-if="filteredTree.length === 0 && orgSearch" class="p-3 text-sm text-slate-500 text-center">
                Nincs találat
              </div>
            </div>
          </div>

          <!-- Normál user: egyszerű lista -->
          <div v-else class="p-1">
            <button
              v-for="m in authStore.memberships"
              :key="m.id"
              @click="handleSwitchMembership(m.id)"
              class="w-full text-left px-3 py-2 text-sm rounded flex items-center gap-2"
              :class="m.id === authStore.currentMembership?.id ? 'bg-teal-600/20 text-white' : 'text-slate-300 hover:bg-slate-800'"
            >
              <svg v-if="m.id === authStore.currentMembership?.id" class="w-4 h-4 text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              <span v-else class="w-4 h-4 shrink-0"></span>
              <div class="min-w-0">
                <p class="font-medium truncate">{{ m.organization.name }}</p>
                <p class="text-xs text-slate-500">{{ m.role.name }}</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Menu Search -->
      <div v-show="isSidebarExpanded" class="px-3 pb-2 shrink-0">
        <div class="relative">
          <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
          <input
            v-model="menuSearch"
            type="text"
            placeholder="Menüpont keresése..."
            class="w-full pl-8 pr-3 py-1.5 text-sm bg-slate-700/50 text-white placeholder-slate-400 rounded-lg border border-slate-600/50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent"
          />
        </div>
      </div>

      <!-- Navigation -->
      <nav
        ref="navRef"
        class="flex-1 overflow-y-auto overflow-x-hidden custom-scrollbar px-3 py-2 space-y-0.5"
      >
        <!-- Főmenü -->
        <p v-show="isSidebarExpanded && showSection(mainMenuItems)" class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.main_menu') }}
        </p>
        <router-link
          v-for="item in filteredItems(mainMenuItems)"
          :key="item.route"
          :to="{ name: item.route }"
          class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="isActive(item.route) ? 'bg-teal-600/20 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
          @click="sidebarOpenMobile = false"
          :title="sidebarCollapsed && !sidebarHover ? t(item.label) : ''"
        >
          <span v-html="item.icon" class="shrink-0" :class="isActive(item.route) ? 'text-teal-400' : 'text-slate-400 group-hover:text-white'" />
          <span v-show="isSidebarExpanded" class="truncate">{{ t(item.label) }}</span>
        </router-link>

        <!-- Üzleti (disabled placeholderek) -->
        <p v-show="isSidebarExpanded && showSection(businessItems)" class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.business') }}
        </p>
        <a
          v-for="item in filteredItems(businessItems)"
          :key="item.route"
          href="#"
          @click.prevent=""
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-500 cursor-not-allowed opacity-60"
          :title="sidebarCollapsed && !sidebarHover ? t(item.label) + ' (hamarosan)' : 'Hamarosan (későbbi fázis)'"
        >
          <span v-html="item.icon" class="shrink-0 text-slate-500" />
          <span v-show="isSidebarExpanded" class="truncate">{{ t(item.label) }}</span>
        </a>

        <!-- Épület -->
        <p v-show="isSidebarExpanded && showSection(facilityItems)" class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.facility') }}
        </p>
        <template v-for="item in filteredItems(facilityItems)" :key="item.route">
          <!-- Élesített: locations -->
          <router-link
            v-if="item.available"
            :to="{ name: item.route }"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="$route.name === item.route
              ? 'bg-teal-600/20 text-teal-300'
              : 'text-slate-300 hover:bg-slate-700/50 hover:text-white'"
            :title="sidebarCollapsed && !sidebarHover ? t(item.label) : ''"
          >
            <span v-html="item.icon" class="shrink-0" />
            <span v-show="isSidebarExpanded" class="truncate">{{ t(item.label) }}</span>
          </router-link>
          <!-- Még nem készült el: disabled placeholder -->
          <a
            v-else
            href="#"
            @click.prevent=""
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-500 cursor-not-allowed opacity-60"
            :title="sidebarCollapsed && !sidebarHover ? t(item.label) + ' (' + t('profile.coming_soon') + ')' : t('profile.placeholder_section')"
          >
            <span v-html="item.icon" class="shrink-0 text-slate-500" />
            <span v-show="isSidebarExpanded" class="truncate">{{ t(item.label) }}</span>
          </a>
        </template>

        <!-- Kiegészítő (disabled) -->
        <p v-show="isSidebarExpanded && showSection(extraItems)" class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ t('nav.extra') }}
        </p>
        <a
          v-for="item in filteredItems(extraItems)"
          :key="item.route"
          href="#"
          @click.prevent=""
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-500 cursor-not-allowed opacity-60"
          :title="sidebarCollapsed && !sidebarHover ? t(item.label) + ' (hamarosan)' : 'Hamarosan (későbbi fázis)'"
        >
          <span v-html="item.icon" class="shrink-0 text-slate-500" />
          <span v-show="isSidebarExpanded" class="truncate">{{ t(item.label) }}</span>
        </a>

        <!-- Admin -->
        <template v-if="authStore.hasAnyPermission('users.read', 'users.manage_roles', 'settings.manage_organization')">
          <p v-show="isSidebarExpanded && showSection(adminItems)" class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
            {{ t('nav.admin') }}
          </p>
          <router-link
            v-for="item in filteredItems(adminItems)"
            :key="item.route"
            :to="{ name: item.route }"
            v-show="item.permission ? authStore.hasPermission(item.permission) : true"
            class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="isActive(item.route) ? 'bg-teal-600/20 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
            @click="sidebarOpenMobile = false"
            :title="sidebarCollapsed && !sidebarHover ? t(item.label) : ''"
          >
            <span v-html="item.icon" class="shrink-0" :class="isActive(item.route) ? 'text-teal-400' : 'text-slate-400 group-hover:text-white'" />
            <span v-show="isSidebarExpanded" class="truncate">{{ t(item.label) }}</span>
          </router-link>
        </template>

        <!-- Szervezet-kezelés (szuper-admin + subscriber admin) -->
        <template v-if="authStore.canManageOrganizations">
          <p v-show="isSidebarExpanded && showSection(superAdminItems)" class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider"
             :class="authStore.isSuperAdmin || authStore.isImpersonation ? 'text-amber-400' : 'text-slate-400'">
            {{ authStore.isSuperAdmin || authStore.isImpersonation ? 'Szuper-admin' : 'Szervezet-kezelés' }}
          </p>
          <router-link
            v-for="item in filteredItems(superAdminItems)"
            :key="item.route"
            :to="{ name: item.route }"
            class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="[
              isActive(item.route)
                ? (authStore.isSuperAdmin || authStore.isImpersonation ? 'bg-amber-600/20 text-white' : 'bg-teal-600/20 text-white')
                : 'text-slate-300 hover:bg-white/10 hover:text-white',
            ]"
            @click="sidebarOpenMobile = false"
            :title="sidebarCollapsed && !sidebarHover ? item.label : ''"
          >
            <span v-html="item.icon" class="shrink-0"
                  :class="isActive(item.route)
                    ? (authStore.isSuperAdmin || authStore.isImpersonation ? 'text-amber-400' : 'text-teal-400')
                    : 'text-slate-400 group-hover:text-white'" />
            <span v-show="isSidebarExpanded" class="truncate">{{ item.label }}</span>
          </router-link>
        </template>

        <!-- Keresés üres eredmény -->
        <div v-if="menuSearch && noResults" class="px-3 py-4 text-center text-sm text-slate-500">
          Nincs találat
        </div>
      </nav>
    </aside>

    <!-- Main wrapper -->
    <div
      class="flex-1 flex flex-col transition-all duration-300"
      :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'"
    >
      <!-- Impersonation banner -->
      <div
        v-if="authStore.isImpersonation && authStore.contextOrganization"
        class="bg-amber-500/90 dark:bg-amber-600/90 text-white px-4 py-2 flex items-center justify-between text-sm"
      >
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
          </svg>
          <span>
            <strong>Szuper-admin mód:</strong> {{ authStore.contextOrganization.name }} megtekintése
          </span>
        </div>
        <button
          @click="handleExitOrg"
          class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded text-xs font-medium transition-colors"
        >
          Vissza a Platform-ra
        </button>
      </div>

      <!-- Header -->
      <header class="sticky top-0 z-30 h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-4 sm:px-6">
        <!-- Left side -->
        <div class="flex items-center gap-3">
          <!-- Hamburger (mobile) -->
          <button
            class="lg:hidden p-2 -ml-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
            @click="sidebarOpenMobile = !sidebarOpenMobile"
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
            <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
            </svg>
            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
            </svg>
          </button>

          <!-- Messages -->
          <button class="relative p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
          </button>

          <!-- Notifications -->
          <button class="relative p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
          </button>

          <!-- Nyelvválasztó -->
          <LanguageSwitcher class="ml-1" />

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
                <button
                  class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                  @click="handleLock"
                >
                  <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                  </svg>
                  Képernyő zárolása
                </button>
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
          <span>{{ authStore.currentOrganizationName }}</span>
        </div>
      </footer>
    </div>

    <!-- Inaktivitás figyelmeztető modal -->
    <InactivityWarningModal
      v-if="showWarningModal"
      :countdown-seconds="WARNING_COUNTDOWN_SECONDS"
      @lock="handleLockNow"
      @continue="handleContinueWorking"
      @timeout="handleWarningTimeout"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter, RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useInactivityTimer } from '@/composables/useInactivityTimer'
import InactivityWarningModal from '@/components/common/InactivityWarningModal.vue'
import OrgTreeNode from '@/components/common/OrgTreeNode.vue'
import LanguageSwitcher from '@/components/common/LanguageSwitcher.vue'
import api from '@/services/api'
import type { OrganizationNode } from '@/types'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

// ========== INAKTIVITÁS KEZELÉS ==========
// Teljes zárolási idő: 30 perc
// A zárolás előtt 30 másodperccel megjelenik a figyelmeztető modal
// Később a felhasználó beállításaiból fog jönni (profil/beállítások oldal)
const TOTAL_TIMEOUT_MS = 30 * 60 * 1000 // 30 perc
const WARNING_COUNTDOWN_SECONDS = 30
const WARNING_AFTER_MS = TOTAL_TIMEOUT_MS - WARNING_COUNTDOWN_SECONDS * 1000 // 29:30

const showWarningModal = ref(false)

const { resetTimer: resetInactivityTimer, pause: pauseInactivity, resume: resumeInactivity } =
  useInactivityTimer(WARNING_AFTER_MS, () => {
    if (authStore.isAuthenticated && !authStore.isLocked) {
      // 2:30 inaktivitás után megjelenik a figyelmeztetés
      showWarningModal.value = true
      // Az aktivitás-figyelőt szüneteltetjük, amíg a modal látszik
      pauseInactivity()
    }
  })

// Amikor a warning countdown lejár (30 sec) -> automatikus zárolás
function handleWarningTimeout() {
  lockNow()
}

// Felhasználó a "Zárolás most" gombra kattintott
function handleLockNow() {
  lockNow()
}

// Felhasználó a "Folytatom a munkát" gombra kattintott
function handleContinueWorking() {
  showWarningModal.value = false
  resumeInactivity() // Újra figyeljük az aktivitást, timer resetel
}

function lockNow() {
  showWarningModal.value = false
  authStore.lock()
  router.push({ name: 'lock' })
}

// UI state
const sidebarOpenMobile = ref(false)

// ========== ORG SWITCHER ==========
const orgSwitcherOpen = ref(false)
const orgTree = ref<OrganizationNode[]>([])
const orgTreeLoading = ref(false)
const orgSearch = ref('')

// Van-e mit váltani?
const hasSwitcher = computed(() => {
  if (authStore.isSuperAdmin) return true
  return authStore.memberships.length > 1
})

const currentOrgId = computed(() => {
  if (authStore.contextOrganization) return authStore.contextOrganization.id
  return authStore.currentMembership?.organization.id
})

const platformNode = computed<OrganizationNode | null>(() => {
  return orgTree.value.find(n => n.type === 'platform') || null
})

// Szűrt fa (keresés alapján)
const filteredTree = computed<OrganizationNode[]>(() => {
  if (!orgSearch.value.trim()) return orgTree.value
  const q = orgSearch.value.toLowerCase()
  return orgTree.value.map(n => filterTreeNode(n, q)).filter((n): n is OrganizationNode => n !== null)
})

function filterTreeNode(node: OrganizationNode, query: string): OrganizationNode | null {
  const matches = node.name.toLowerCase().includes(query)
  const filteredChildren = node.children
    .map(c => filterTreeNode(c, query))
    .filter((c): c is OrganizationNode => c !== null)

  if (matches || filteredChildren.length > 0) {
    return { ...node, children: filteredChildren }
  }
  return null
}

function orgTypeLabel(type: string): string {
  const map: Record<string, string> = {
    platform: 'Platform',
    subscriber: 'Előfizető',
    client: 'Ügyfél',
  }
  return map[type] || type
}

async function toggleOrgSwitcher() {
  if (!hasSwitcher.value) return
  orgSwitcherOpen.value = !orgSwitcherOpen.value
  if (orgSwitcherOpen.value && authStore.isSuperAdmin && orgTree.value.length === 0) {
    await loadOrgTree()
  }
}

async function loadOrgTree() {
  orgTreeLoading.value = true
  try {
    const response = await api.get('/admin/organizations-tree')
    orgTree.value = response.data.data
  } catch {
    // silent
  } finally {
    orgTreeLoading.value = false
  }
}

/**
 * Egységes szervezet-váltó handler.
 *
 * Logika:
 *  - Ha a usernek van valódi tagsága ebben a szervezetben → switchOrganization
 *  - Ha nincs ÉS szuper-admin → enterOrganization (impersonation)
 *  - Ha nincs ÉS nem szuper-admin → nem történhet (védelemként visszatér)
 *
 * Ugyanez a függvény kezeli:
 *  - Szuper-admin fa-struktúra választást
 *  - Normál lista választást
 *  - Impersonation banner "Vissza a Platform-ra" gombját (Platform org id-val hívódik)
 */
async function handleSelectOrg(orgId: number) {
  orgSwitcherOpen.value = false

  try {
    // Van-e valódi tagsága ehhez a szervezethez?
    const existingMembership = authStore.memberships.find(
      m => m.organization.id === orgId
    )

    if (existingMembership) {
      await authStore.switchOrganization(existingMembership.id)
    } else if (authStore.isSuperAdmin) {
      // Nincs tagság, de szuper-admin → impersonation
      await authStore.enterOrganization(orgId)
    } else {
      return
    }

    await router.push({ name: 'dashboard' })
  } catch (err) {
    console.error(err)
  }
}

/**
 * "Vissza a Platform-ra" - impersonation banner gomb.
 */
async function handleExitOrg() {
  orgSwitcherOpen.value = false

  try {
    // Van-e Platform membership-je? Ha igen → switchOrganization, ha nincs → exit
    const platformMembership = authStore.memberships.find(
      m => m.organization.type === 'platform'
    )

    if (platformMembership) {
      await authStore.switchOrganization(platformMembership.id)
    } else {
      // Tisztán impersonation (nincs Platform membership) → exit
      await authStore.exitOrganization()
    }

    await router.push({ name: 'dashboard' })
  } catch (err) {
    console.error(err)
  }
}

/**
 * Normál lista elem (membership_id alapján) kattintás.
 * Delegálja a handleSelectOrg-ra.
 */
async function handleSwitchMembership(membershipId: number) {
  const membership = authStore.memberships.find(m => m.id === membershipId)
  if (!membership) return
  await handleSelectOrg(membership.organization.id)
}
const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')
const sidebarHover = ref(false)
const profileOpen = ref(false)
const isDark = ref(document.documentElement.classList.contains('dark'))
const menuSearch = ref('')
const isMobile = ref(window.innerWidth < 1024) // lg breakpoint

function handleResize() {
  isMobile.value = window.innerWidth < 1024
}

// Mentsük el a sidebar állapotot
function saveSidebarState() {
  localStorage.setItem('sidebar_collapsed', String(sidebarCollapsed.value))
}

// A sidebar feliratok mikor látszódnak:
// - Mobil nézeten: mindig (a hamburger menüvel nyitható)
// - Desktop: ha nincs összecsukva VAGY ha összecsukva van de hover alatt van
const isSidebarExpanded = computed(() => {
  if (isMobile.value) return true
  return !sidebarCollapsed.value || sidebarHover.value
})

// Figyelemfigyelő - a sidebar szélesség mentése
const unwatchSidebar = ref<(() => void) | null>(null)

interface NavItem {
  route: string
  label: string
  icon: string
  permission?: string
  available?: boolean   // ha true, élesített router-link; ha hiányzik vagy false → disabled placeholder
  requiresBusinessOrg?: boolean  // ha true, csak subscriber/client szervezet kontextusában jelenik meg (Platform-on rejtve)
}

// Menü elemek
const mainMenuItems: NavItem[] = [
  { route: 'dashboard', label: 'nav.dashboard', icon: svgIcon('dashboard') },
]

const businessItems: NavItem[] = [
  { route: 'tickets', label: 'nav.tickets', icon: svgIcon('tickets') },
  { route: 'tasks', label: 'nav.tasks', icon: svgIcon('tasks') },
  { route: 'projects', label: 'nav.projects', icon: svgIcon('projects') },
  { route: 'issues', label: 'nav.issues', icon: svgIcon('issues') },
  { route: 'suggestions', label: 'nav.suggestions', icon: svgIcon('suggestions') },
  { route: 'documents', label: 'nav.documents', icon: svgIcon('documents') },
]

const facilityItems: NavItem[] = [
  { route: 'locations', label: 'nav.locations', icon: svgIcon('locations'), available: true, requiresBusinessOrg: true },
  { route: 'assets', label: 'nav.assets', icon: svgIcon('assets'), requiresBusinessOrg: true },
  { route: 'maintenance', label: 'nav.maintenance', icon: svgIcon('maintenance'), requiresBusinessOrg: true },
]

const extraItems: NavItem[] = [
  { route: 'contracts', label: 'nav.contracts', icon: svgIcon('contracts') },
  { route: 'messages', label: 'nav.messages', icon: svgIcon('messages') },
]

const adminItems: NavItem[] = [
  { route: 'admin-users', label: 'nav.users', icon: svgIcon('users'), permission: 'users.read' },
  { route: 'admin-roles', label: 'nav.roles', icon: svgIcon('roles'), permission: 'users.manage_roles' },
  { route: 'admin-organization', label: 'nav.settings', icon: svgIcon('settings'), permission: 'settings.manage_organization' },
]

// Szuper-admin-only menüpontok (csak ha isSuperAdmin = true)
const superAdminItems: NavItem[] = [
  { route: 'admin-organizations', label: 'Szervezetek', icon: svgIcon('organizations') },
]

// SVG ikonok
function svgIcon(name: string): string {
  const icons: Record<string, string> = {
    dashboard: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>',
    tickets: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" /></svg>',
    tasks: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    projects: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>',
    issues: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>',
    suggestions: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>',
    documents: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
    locations: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>',
    assets: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122" /></svg>',
    maintenance: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" /></svg>',
    contracts: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
    messages: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>',
    users: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>',
    roles: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>',
    settings: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
    organizations: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" /></svg>',
  }
  return icons[name] || ''
}

// Navigation helpers
function isActive(routeName: string): boolean {
  return route.name === routeName
}

// Üzleti modulok csak subscriber/client szervezet kontextusában jelennek meg
// (Platform szervezet esetén elrejtjük — a super-admin impersonationnel léphet be)
const isBusinessOrgContext = computed(() => {
  const type = authStore.currentOrganizationType
  return type === 'subscriber' || type === 'client'
})

// Keresés szűrés - a label lehet fordítási kulcs (nav.xxx) vagy közvetlen szöveg
function filteredItems(items: NavItem[]): NavItem[] {
  // Először szűrjük ki a Platform-on rejtett (üzleti) elemeket
  let result = items.filter(item => {
    if (item.requiresBusinessOrg && !isBusinessOrgContext.value) return false
    return true
  })

  if (!menuSearch.value) return result
  const q = menuSearch.value.toLowerCase()
  return result.filter(item => {
    const label = item.label.startsWith('nav.') || item.label.startsWith('common.') || item.label.startsWith('users.') || item.label.startsWith('roles.')
      ? t(item.label)
      : item.label
    return label.toLowerCase().includes(q)
  })
}

function showSection(items: NavItem[]): boolean {
  return filteredItems(items).length > 0
}

const noResults = computed(() => {
  return !showSection(mainMenuItems) &&
    !showSection(businessItems) &&
    !showSection(facilityItems) &&
    !showSection(extraItems) &&
    !showSection(adminItems) &&
    !showSection(superAdminItems)
})

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

// Lock screen
function handleLock(): void {
  profileOpen.value = false
  authStore.lock()
  router.push('/lock')
}

// Logout
async function handleLogout(): Promise<void> {
  profileOpen.value = false
  await authStore.logout()
  router.push('/login')
}

// Click outside handler
function handleClickOutside(event: MouseEvent): void {
  const target = event.target as HTMLElement
  if (profileOpen.value && !target.closest('.relative')) {
    profileOpen.value = false
  }
}

// Sidebar state save
import { watch } from 'vue'
watch(sidebarCollapsed, saveSidebarState)

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  window.addEventListener('resize', handleResize)

  const savedTheme = localStorage.getItem('theme')
  if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
    isDark.value = true
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
  window.removeEventListener('resize', handleResize)
})
</script>

<style>
/* Egyedi scrollbar a sidebar-hoz - elegánsabb megjelenés */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
  border-radius: 10px;
  transition: background 0.2s;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.5);
}
/* Firefox */
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
}
</style>
