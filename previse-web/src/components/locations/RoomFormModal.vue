<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FloorItem } from '@/services/floors'
import type { RoomItem } from '@/services/rooms'
import { fetchRoomTypeCatalog, type RoomTypeItem } from '@/services/roomTypes'

interface Props {
  locationId: number
  room?: RoomItem | null
  floors: FloorItem[]
  defaultFloorId?: number | null
}
const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: {
    floor_id: number | null
    name: string
    number: string | null
    type: string | null
    area_sqm: number | null
    description: string | null
  }): void
}>()

const { t } = useI18n()

const floorId = ref<number | null>(
  props.room?.floor_id ?? props.defaultFloorId ?? null,
)
const name = ref(props.room?.name ?? '')
const number = ref(props.room?.number ?? '')
const type = ref(props.room?.type ?? '')
const areaSqm = ref<number | null>(
  props.room?.area_sqm != null ? Number(props.room.area_sqm) : null,
)
const description = ref(props.room?.description ?? '')

const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const typeCatalog = ref<RoomTypeItem[]>([])

async function loadTypeCatalog() {
  try {
    typeCatalog.value = await fetchRoomTypeCatalog()
  } catch {
    /* silent */
  }
}

async function submit() {
  errors.value = {}
  loading.value = true
  try {
    emit('save', {
      floor_id: floorId.value,
      name: name.value.trim(),
      number: number.value.trim() || null,
      type: type.value.trim() || null,
      area_sqm: areaSqm.value !== null && !Number.isNaN(areaSqm.value) ? Number(areaSqm.value) : null,
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

onMounted(loadTypeCatalog)
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl">
      <div class="p-5 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ room ? t('locations.room_edit') : t('locations.room_new') }}
        </h2>
      </div>
      <form @submit.prevent="submit" class="p-5 space-y-4">
        <!-- Szint választó -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.room_floor') }}
          </label>
          <select
            v-model="floorId"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          >
            <option :value="null">{{ t('locations.room_floor_none') }}</option>
            <option v-for="f in floors" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
          <p v-if="errors.floor_id" class="mt-1 text-xs text-red-600">{{ errors.floor_id[0] }}</p>
        </div>

        <!-- Név -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ t('locations.room_name') }} <span class="text-red-500">*</span>
          </label>
          <input
            v-model="name"
            type="text"
            required
            :placeholder="t('locations.room_name_placeholder')"
            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
        </div>

        <!-- Szám / Típus -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('locations.room_number') }}</label>
            <input
              v-model="number"
              type="text"
              :placeholder="t('locations.room_number_placeholder')"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('locations.room_type') }}</label>
            <select
              v-model="type"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
            >
              <option value="">{{ t('locations.room_type_none') }}</option>
              <option v-for="rt in typeCatalog" :key="rt.id" :value="rt.name">{{ rt.name }}</option>
              <!-- Legacy érték: ha a meglévő helyiség típusa nincs a katalógusban (pl. mert törölték), tartsuk meg az értéket -->
              <option
                v-if="type && !typeCatalog.some(rt => rt.name === type)"
                :value="type"
              >
                {{ type }} ({{ t('locations.room_type_legacy') }})
              </option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('locations.room_type_hint') }}</p>
          </div>
        </div>

        <!-- Alapterület -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('locations.room_area') }}</label>
          <input
            v-model.number="areaSqm"
            type="number"
            step="0.01"
            min="0"
            class="block w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
          />
        </div>

        <!-- Leírás -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('locations.room_description') }}</label>
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
