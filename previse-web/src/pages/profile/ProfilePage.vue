<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SecurityTab from '@/components/profile/SecurityTab.vue'

const { t } = useI18n()

type TabKey = 'profile' | 'security' | 'appearance' | 'notifications' | '2fa'

const activeTab = ref<TabKey>('security')

const tabs: { key: TabKey; label: string; available: boolean }[] = [
  { key: 'profile', label: 'profile.tab_profile', available: false },
  { key: 'security', label: 'profile.tab_security', available: true },
  { key: 'appearance', label: 'profile.tab_appearance', available: false },
  { key: 'notifications', label: 'profile.tab_notifications', available: false },
  { key: '2fa', label: 'profile.tab_2fa', available: false },
]
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-6">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500 dark:text-gray-400">
      <ol class="flex items-center space-x-2">
        <li>
          <router-link to="/" class="hover:text-teal-600 dark:hover:text-teal-400">
            {{ t('nav.home', 'Főoldal') }}
          </router-link>
        </li>
        <li><span class="mx-1">/</span></li>
        <li class="text-teal-600 dark:text-teal-400 font-medium">{{ t('profile.title') }}</li>
      </ol>
    </nav>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">{{ t('profile.title') }}</h1>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
      <nav class="-mb-px flex gap-6 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="tab.available && (activeTab = tab.key)"
          :disabled="!tab.available"
          class="whitespace-nowrap py-3 px-1 border-b-2 text-sm font-medium transition-colors"
          :class="[
            activeTab === tab.key
              ? 'border-teal-500 text-teal-600 dark:text-teal-400'
              : tab.available
                ? 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'
                : 'border-transparent text-gray-300 dark:text-gray-600 cursor-not-allowed',
          ]"
        >
          {{ t(tab.label) }}
          <span v-if="!tab.available" class="ml-1 text-[10px] text-gray-400">(hamarosan)</span>
        </button>
      </nav>
    </div>

    <!-- Content -->
    <SecurityTab v-if="activeTab === 'security'" />
    <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500">
      Ez a szekció egy későbbi fázisban készül el.
    </div>
  </div>
</template>
