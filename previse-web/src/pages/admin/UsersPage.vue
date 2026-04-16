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
          {{ t('users.title', 'Felhasználók') }}
        </li>
      </ol>
    </nav>

    <!-- Page Title -->
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
      {{ t('users.title', 'Felhasználók') }}
    </h1>

    <!-- Placeholder Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
      <div class="text-teal-500 dark:text-teal-400 mb-4">
        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </div>
      <p class="text-gray-600 dark:text-gray-300 text-lg">
        A felhasználók kezelés felülete hamarosan elkészül.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
