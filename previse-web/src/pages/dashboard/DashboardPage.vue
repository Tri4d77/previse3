<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const authStore = useAuthStore()
</script>

<template>
  <div>
    <!-- Oldalcím -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('nav.dashboard') }}</h1>
        <nav class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          <span>{{ t('common.home') }}</span>
          <span class="mx-1">/</span>
          <span class="text-gray-700 dark:text-gray-200">{{ t('nav.dashboard') }}</span>
        </nav>
      </div>
    </div>

    <!-- Placeholder kártyák -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('nav.tickets') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">-</p>
        <p class="text-xs text-gray-400 mt-2">Fázis 3-ban implementáljuk</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('nav.tasks') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">-</p>
        <p class="text-xs text-gray-400 mt-2">Fázis 4-ben implementáljuk</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('nav.projects') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">-</p>
        <p class="text-xs text-gray-400 mt-2">Fázis 4-ben implementáljuk</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('nav.maintenance') }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">-</p>
        <p class="text-xs text-gray-400 mt-2">Fázis 5-ben implementáljuk</p>
      </div>
    </div>

    <!-- Üdvözlés -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
        Üdvözöllek, {{ authStore.userName }}! 👋
      </h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Szervezet: <strong class="text-gray-700 dark:text-gray-200">{{ authStore.organizationName }}</strong> &middot;
        Szerepkör: <strong class="text-gray-700 dark:text-gray-200">{{ authStore.userRole }}</strong>
      </p>
      <p class="text-sm text-gray-400 dark:text-gray-500 mt-4">
        A dashboard tartalma a későbbi fázisokban kerül implementálásra.
        Jelenleg a Fázis 1 (Auth + Felhasználó-kezelés) funkciók érhetők el.
      </p>
    </div>
  </div>
</template>
