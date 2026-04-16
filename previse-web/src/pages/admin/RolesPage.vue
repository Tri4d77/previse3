<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-6">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500 dark:text-gray-400">
      <ol class="flex items-center space-x-2">
        <li>
          <a href="/" class="hover:text-teal-600 dark:hover:text-teal-400">{{ t('nav.home', 'Főoldal') }}</a>
        </li>
        <li><span class="mx-1">/</span></li>
        <li>
          <span>{{ t('nav.administration', 'Adminisztráció') }}</span>
        </li>
        <li><span class="mx-1">/</span></li>
        <li class="text-teal-600 dark:text-teal-400 font-medium">
          {{ t('roles.title', 'Szerepkörök') }}
        </li>
      </ol>
    </nav>

    <!-- Page Title -->
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
      {{ t('roles.title', 'Szerepkörök') }}
    </h1>

    <!-- Placeholder Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
      <div class="text-teal-500 dark:text-teal-400 mb-4">
        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <p class="text-gray-600 dark:text-gray-300 text-lg">
        A szerepkörök kezelés felülete hamarosan elkészül.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
