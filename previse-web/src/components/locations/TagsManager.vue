<script setup lang="ts">
/**
 * Helyszín-címke katalógus admin felület.
 * Jellemzően a Helyszínek oldalon egy "Címkék kezelése" gombbal indítható modal-ban
 * vagy a beállítások képernyőn jelenik meg.
 */
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToastStore } from '@/stores/toast'
import { useConfirmStore } from '@/stores/confirm'
import {
  fetchTags,
  createTag,
  updateTag,
  deleteTag,
  tagBadgeClass,
  type LocationTag,
  type TagColor,
} from '@/services/locationTags'
import TagFormModal from './TagFormModal.vue'

interface Props {
  canManage: boolean
}
defineProps<Props>()

const { t } = useI18n()
const toast = useToastStore()
const confirmStore = useConfirmStore()

const tags = ref<LocationTag[]>([])
const loading = ref(false)
const showModal = ref(false)
const editing = ref<LocationTag | null>(null)

async function load() {
  loading.value = true
  try {
    tags.value = await fetchTags()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  } finally {
    loading.value = false
  }
}

function openNew() {
  editing.value = null
  showModal.value = true
}

function openEdit(tag: LocationTag) {
  editing.value = tag
  showModal.value = true
}

async function handleSave(payload: { name: string; color: TagColor }) {
  try {
    if (editing.value) {
      await updateTag(editing.value.id, payload)
      toast.success(t('locations.tag_updated'))
    } else {
      await createTag(payload)
      toast.success(t('locations.tag_created'))
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  }
}

async function handleDelete(tag: LocationTag) {
  const ok = await confirmStore.ask({
    title: t('locations.tag_deleted'),
    message: t('locations.tag_delete_confirm', { name: tag.name }),
    confirmText: t('common.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteTag(tag.id)
    toast.success(t('locations.tag_deleted'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? t('common.error_generic'))
  }
}

onMounted(load)
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('locations.tags_section') }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('locations.tags_section_desc') }}</p>
      </div>
      <button
        v-if="canManage"
        @click="openNew"
        class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-sm rounded-lg"
      >
        + {{ t('locations.tag_new') }}
      </button>
    </div>

    <div v-if="loading" class="p-10 text-center">
      <div class="animate-spin w-6 h-6 border-2 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
    </div>

    <div v-else-if="!tags.length" class="p-10 text-center text-sm text-gray-500 dark:text-gray-400">
      {{ t('locations.tags_empty') }}
    </div>

    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
      <li v-for="tag in tags" :key="tag.id" class="p-4 flex items-center gap-3">
        <span
          class="text-xs font-medium px-2.5 py-1 rounded-full"
          :class="tagBadgeClass(tag.color)"
        >
          {{ tag.name }}
        </span>
        <span class="ml-auto text-xs text-gray-400">{{ tag.color }}</span>
        <div v-if="canManage" class="flex items-center gap-1">
          <button @click="openEdit(tag)" class="p-2 text-gray-400 hover:text-teal-600" :title="t('common.edit')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </button>
          <button @click="handleDelete(tag)" class="p-2 text-gray-400 hover:text-red-600" :title="t('common.delete')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
            </svg>
          </button>
        </div>
      </li>
    </ul>

    <TagFormModal
      v-if="showModal"
      :tag="editing"
      @close="showModal = false"
      @save="handleSave"
    />
  </div>
</template>
