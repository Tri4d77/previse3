<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  TAG_COLORS,
  tagSwatchClass,
  type LocationTag,
  type TagColor,
} from '@/services/locationTags'

interface Props {
  tag?: LocationTag | null
}
const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: { name: string; color: TagColor }): void
}>()

const { t } = useI18n()

const name = ref(props.tag?.name ?? '')
const color = ref<TagColor>((props.tag?.color as TagColor) ?? 'teal')
const errors = ref<Record<string, string[]>>({})
const loading = ref(false)

function submit() {
  errors.value = {}
  loading.value = true
  try {
    emit('save', { name: name.value.trim(), color: color.value })
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
          {{ tag ? t('locations.tag_edit') : t('locations.tag_new') }}
        </h2>
      </div>
      <form @submit.prevent="submit" class="p-5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.tag_name') }} <span class="text-red-500">*</span>
          </label>
          <input
            v-model="name"
            type="text"
            required
            maxlength="50"
            :placeholder="t('locations.tag_name_placeholder')"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('locations.tag_color') }}
          </label>
          <div class="grid grid-cols-8 gap-2">
            <button
              v-for="c in TAG_COLORS"
              :key="c"
              type="button"
              @click="color = c"
              :title="c"
              class="w-8 h-8 rounded-full border-2 transition-all hover:scale-110"
              :class="[
                tagSwatchClass(c),
                color === c
                  ? 'border-gray-900 dark:border-white ring-2 ring-offset-2 ring-gray-400 dark:ring-offset-gray-800'
                  : 'border-transparent',
              ]"
            />
          </div>
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
