<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createLocation,
  updateLocation,
  uploadLocationImage,
  fetchLocationTypes,
  type LocationItem,
  type LocationType,
} from '@/services/locations'
import { useToastStore } from '@/stores/toast'

interface Props {
  /** Ha edit-elsz, ide adod a meglévő LocationItem-et. Új létrehozáshoz null. */
  location: LocationItem | null
}
const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'close'): void
  (e: 'saved', l: LocationItem): void
}>()

const { t } = useI18n()
const toast = useToastStore()

// Form state
const code = ref('')
const name = ref('')
const typeId = ref<number | null>(null)
const address = ref('')
const city = ref('')
const zipCode = ref('')
const latitude = ref<number | null>(null)
const longitude = ref<number | null>(null)
const description = ref('')

// Image
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const existingImageUrl = ref<string | null>(null)

// Types
const types = ref<LocationType[]>([])

const loading = ref(false)
const errors = ref<Record<string, string[]>>({})

const isEdit = ref(false)

onMounted(async () => {
  // Helyszín-típusok
  try {
    types.value = await fetchLocationTypes()
  } catch {/* silent */ }

  // Edit állapot
  if (props.location) {
    isEdit.value = true
    code.value = props.location.code
    name.value = props.location.name
    typeId.value = props.location.type?.id ?? null
    address.value = props.location.address ?? ''
    city.value = props.location.city ?? ''
    zipCode.value = props.location.zip_code ?? ''
    latitude.value = props.location.latitude
    longitude.value = props.location.longitude
    description.value = props.location.description ?? ''
    existingImageUrl.value = props.location.image_url
  }
})

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    imageFile.value = target.files[0]
    imagePreview.value = URL.createObjectURL(target.files[0])
  }
}

function clearImageSelection() {
  imageFile.value = null
  imagePreview.value = null
}

async function submit() {
  errors.value = {}
  loading.value = true

  try {
    const payload = {
      code: code.value || undefined,
      name: name.value,
      type_id: typeId.value || null,
      address: address.value || null,
      city: city.value || null,
      zip_code: zipCode.value || null,
      latitude: latitude.value,
      longitude: longitude.value,
      description: description.value || null,
    }

    let saved: LocationItem
    if (isEdit.value && props.location) {
      saved = await updateLocation(props.location.id, payload)
    } else {
      saved = await createLocation(payload)
    }

    // Ha új képet választott, töltsük is fel
    if (imageFile.value) {
      saved = await uploadLocationImage(saved.id, imageFile.value)
    }

    toast.success(isEdit.value ? t('locations.updated') : t('locations.created'))
    emit('saved', saved)
  } catch (err: any) {
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    } else {
      toast.error(err.response?.data?.message ?? t('common.error_generic'))
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="emit('close')">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
          {{ isEdit ? t('locations.edit') : t('locations.new') }}
        </h2>
      </div>

      <form @submit.prevent="submit" class="p-6 space-y-4">
        <!-- Kép -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.image') }}
          </label>
          <div class="flex items-start gap-4">
            <div class="shrink-0 w-32 h-24 rounded-lg bg-gray-100 dark:bg-gray-700 overflow-hidden flex items-center justify-center">
              <img v-if="imagePreview" :src="imagePreview" alt="" class="w-full h-full object-cover" />
              <img v-else-if="existingImageUrl && !imageFile" :src="existingImageUrl" alt="" class="w-full h-full object-cover" />
              <svg v-else class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
              </svg>
            </div>
            <div class="flex-1">
              <input
                type="file"
                accept="image/jpeg,image/png"
                @change="onFileChange"
                class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-teal-50 dark:file:bg-teal-900/30 file:text-teal-700 dark:file:text-teal-300 file:cursor-pointer"
              />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('locations.image_hint') }}</p>
              <button
                v-if="imageFile"
                @click="clearImageSelection"
                type="button"
                class="mt-1 text-xs text-red-600 hover:underline"
              >
                {{ t('common.cancel') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Code + Name -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.code') }}
            </label>
            <input
              v-model="code"
              type="text"
              maxlength="50"
              :placeholder="isEdit ? '' : 'auto-generált'"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-teal-500"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('locations.code_hint') }}</p>
            <p v-if="errors.code" class="mt-1 text-xs text-red-600">{{ errors.code[0] }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.name') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="name"
              type="text"
              required
              maxlength="255"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-teal-500"
            />
            <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
          </div>
        </div>

        <!-- Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.type') }}
          </label>
          <select
            v-model="typeId"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-teal-500"
          >
            <option :value="null">—</option>
            <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
          </select>
        </div>

        <!-- Address -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.address') }}
            </label>
            <input
              v-model="address"
              type="text"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-teal-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.zip_code') }}
            </label>
            <input
              v-model="zipCode"
              type="text"
              maxlength="20"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-teal-500"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.city') }}
          </label>
          <input
            v-model="city"
            type="text"
            maxlength="100"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-teal-500"
          />
        </div>

        <!-- GPS -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.latitude') }}
            </label>
            <input
              v-model.number="latitude"
              type="number"
              step="0.00000001"
              min="-90"
              max="90"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-teal-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ t('locations.longitude') }}
            </label>
            <input
              v-model.number="longitude"
              type="number"
              step="0.00000001"
              min="-180"
              max="180"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-teal-500"
            />
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.description') }}
          </label>
          <textarea
            v-model="description"
            rows="3"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-teal-500"
          ></textarea>
        </div>

        <!-- Submit -->
        <div class="flex gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
          <button
            type="submit"
            :disabled="loading"
            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white text-sm font-medium rounded-lg"
          >
            {{ loading ? '…' : t('common.save') }}
          </button>
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
          >
            {{ t('common.cancel') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
