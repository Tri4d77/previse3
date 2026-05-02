<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FloorItem } from '@/services/floors'

interface Props {
  floor?: FloorItem | null
}
const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: { name: string; level: number; description: string | null }): void
}>()

const { t } = useI18n()

const name = ref(props.floor?.name ?? '')
const level = ref<number>(props.floor?.level ?? 0)
const description = ref(props.floor?.description ?? '')

const errors = ref<Record<string, string[]>>({})
const loading = ref(false)

async function submit() {
  errors.value = {}
  loading.value = true
  try {
    emit('save', {
      name: name.value.trim(),
      level: Number(level.value),
      description: description.value.trim() || null,
    })
  } catch (err: any) {
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl">
      <div class="p-5 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ floor ? t('locations.floor_edit') : t('locations.floor_new') }}
        </h2>
      </div>
      <form @submit.prevent="submit" class="p-5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.floor_name') }} <span class="text-red-500">*</span>
          </label>
          <input
            v-model="name"
            type="text"
            required
            :placeholder="t('locations.floor_name_placeholder')"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.floor_level') }} <span class="text-red-500">*</span>
          </label>
          <input
            v-model.number="level"
            type="number"
            required
            class="block w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('locations.floor_level_hint') }}</p>
          <p v-if="errors.level" class="mt-1 text-xs text-red-600">{{ errors.level[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.floor_description') }}
          </label>
          <textarea
            v-model="description"
            rows="2"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="loading || !name.trim()"
            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white text-sm font-medium rounded-lg"
          >
            {{ t('common.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
