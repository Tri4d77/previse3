<script setup lang="ts">
import { useLocale, type SupportedLocale } from '@/composables/useLocale'

const { currentLocale, setLocale, supportedLocales } = useLocale()

async function choose(locale: SupportedLocale) {
  if (locale === currentLocale.value) return
  await setLocale(locale)
}
</script>

<template>
  <div class="inline-flex items-center gap-1.5">
    <button
      v-for="loc in supportedLocales"
      :key="loc.code"
      @click="choose(loc.code)"
      type="button"
      class="relative flex items-center gap-1.5 px-2 py-1 rounded-md overflow-hidden border transition-all focus:outline-none focus:ring-2 focus:ring-teal-500"
      :class="loc.code === currentLocale
        ? 'border-teal-500 shadow-sm scale-105'
        : 'border-gray-200 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-400'"
      :aria-label="loc.label"
      :aria-pressed="loc.code === currentLocale"
      :title="loc.label"
    >
      <!-- Zászló (aktív: színes, inaktív: szürke + halvány) -->
      <span
        class="fi shrink-0 w-5 h-4 rounded-sm transition-all"
        :class="[
          `fi-${loc.country}`,
          loc.code === currentLocale ? '' : 'grayscale opacity-60',
        ]"
        aria-hidden="true"
      ></span>

      <!-- Nyelvkód (aktív: teal, inaktív: szürke) -->
      <span
        class="text-xs font-semibold uppercase transition-colors"
        :class="loc.code === currentLocale
          ? 'text-teal-700 dark:text-teal-300'
          : 'text-gray-500 dark:text-gray-400'"
      >
        {{ loc.code }}
      </span>
    </button>
  </div>
</template>
