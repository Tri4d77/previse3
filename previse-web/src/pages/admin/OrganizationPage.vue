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
          {{ t('nav.settings', 'Beállítások') }}
        </li>
      </ol>
    </nav>

    <!-- Page Title -->
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
      {{ t('nav.settings', 'Beállítások') }}
    </h1>

    <!-- Placeholder Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
      <div class="text-teal-500 dark:text-teal-400 mb-4">
        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </div>
      <p class="text-gray-600 dark:text-gray-300 text-lg">
        A szervezeti beállítások felülete hamarosan elkészül.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
