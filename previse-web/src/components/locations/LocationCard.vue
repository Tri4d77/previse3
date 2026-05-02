<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LocationItem } from '@/services/locations'

interface Props {
  location: LocationItem
}
const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'edit', l: LocationItem): void
  (e: 'delete', l: LocationItem): void
  (e: 'open', l: LocationItem): void
}>()

const { t } = useI18n()

const statusBadgeClass = computed(() => {
  switch (props.location.is_active) {
    case 1: return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
    case 0: return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'
    case 2: return 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
    default: return 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
  }
})

const statusLabel = computed(() => {
  switch (props.location.is_active) {
    case 1: return t('locations.status_active')
    case 0: return t('locations.status_archived')
    case 2: return t('locations.status_terminated')
    default: return ''
  }
})
</script>

<template>
  <div
    @click="emit('open', location)"
    class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition-shadow cursor-pointer"
  >
    <!-- Kép -->
    <div class="aspect-[4/3] bg-gray-100 dark:bg-gray-700 relative overflow-hidden">
      <img
        v-if="location.thumb_url || location.image_url"
        :src="location.thumb_url || location.image_url || ''"
        :alt="location.name"
        class="w-full h-full object-cover"
      />
      <div v-else class="w-full h-full flex items-center justify-center">
        <svg class="w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h6.75v-12L13.5 3 6.75 9V21" />
        </svg>
      </div>
      <!-- Státusz badge -->
      <span
        class="absolute top-2 right-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
        :class="statusBadgeClass"
      >
        {{ statusLabel }}
      </span>
    </div>

    <!-- Tartalom -->
    <div class="p-4">
      <div class="flex items-start justify-between gap-2 mb-2">
        <div class="min-w-0 flex-1">
          <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ location.code }}</p>
          <h3 class="font-semibold text-gray-900 dark:text-white truncate">{{ location.name }}</h3>
        </div>
        <button
          @click.stop="emit('edit', location)"
          class="shrink-0 p-1.5 text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 rounded hover:bg-gray-100 dark:hover:bg-gray-700 opacity-0 group-hover:opacity-100 transition-opacity"
          :title="t('locations.action_edit')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
          </svg>
        </button>
      </div>

      <div class="text-sm text-gray-600 dark:text-gray-400 space-y-0.5">
        <p v-if="location.type" class="text-xs uppercase tracking-wide text-teal-600 dark:text-teal-400 font-medium">
          {{ location.type.name }}
        </p>
        <p v-if="location.city || location.address" class="truncate">
          {{ [location.zip_code, location.city, location.address].filter(Boolean).join(', ') }}
        </p>
      </div>
    </div>
  </div>
</template>
