<script setup lang="ts">
/**
 * Multi-select tag picker. v-model:tagIds (number[]).
 * A katalógusból (org-szintű) választható.
 */
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { fetchTags, tagBadgeClass, type LocationTag } from '@/services/locationTags'

interface Props {
  modelValue: number[]
}
const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: number[]): void
}>()

const { t } = useI18n()

const allTags = ref<LocationTag[]>([])
const loading = ref(false)
const open = ref(false)
const wrapperEl = ref<HTMLElement | null>(null)

const selectedTags = computed(() =>
  allTags.value.filter((t) => props.modelValue.includes(t.id)),
)

const availableTags = computed(() =>
  allTags.value.filter((t) => !props.modelValue.includes(t.id)),
)

function toggleTag(tag: LocationTag) {
  if (props.modelValue.includes(tag.id)) {
    emit(
      'update:modelValue',
      props.modelValue.filter((id) => id !== tag.id),
    )
  } else {
    emit('update:modelValue', [...props.modelValue, tag.id])
  }
}

function onDocClick(e: MouseEvent) {
  if (!open.value) return
  if (wrapperEl.value && !wrapperEl.value.contains(e.target as Node)) {
    open.value = false
  }
}

async function load() {
  loading.value = true
  try {
    allTags.value = await fetchTags()
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  document.addEventListener('click', onDocClick)
})
</script>

<template>
  <div ref="wrapperEl" class="relative">
    <!-- Kiválasztott + add gomb -->
    <div
      class="min-h-[42px] flex flex-wrap items-center gap-1.5 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 cursor-pointer"
      @click="open = !open"
    >
      <span
        v-for="tag in selectedTags"
        :key="tag.id"
        class="text-xs font-medium px-2 py-0.5 rounded-full inline-flex items-center gap-1"
        :class="tagBadgeClass(tag.color)"
      >
        {{ tag.name }}
        <button
          type="button"
          @click.stop="toggleTag(tag)"
          class="hover:opacity-75"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </span>
      <span v-if="!selectedTags.length" class="text-sm text-gray-400">
        {{ t('locations.tags_pick_placeholder') }}
      </span>
      <svg class="w-4 h-4 ml-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </div>

    <!-- Dropdown -->
    <div
      v-if="open"
      class="absolute z-30 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-64 overflow-y-auto"
    >
      <div v-if="loading" class="p-4 text-center text-sm text-gray-500">…</div>
      <div v-else-if="!allTags.length" class="p-4 text-center text-sm text-gray-500">
        {{ t('locations.tags_empty') }}
      </div>
      <div v-else-if="!availableTags.length" class="p-4 text-center text-sm text-gray-500">
        {{ t('locations.tags_all_selected') }}
      </div>
      <ul v-else class="py-1">
        <li
          v-for="tag in availableTags"
          :key="tag.id"
          @click="toggleTag(tag)"
          class="px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer flex items-center gap-2"
        >
          <span
            class="text-xs font-medium px-2 py-0.5 rounded-full"
            :class="tagBadgeClass(tag.color)"
          >
            {{ tag.name }}
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>
