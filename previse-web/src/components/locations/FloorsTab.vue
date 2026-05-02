<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToastStore } from '@/stores/toast'
import { useConfirmStore } from '@/stores/confirm'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import {
  fetchFloors,
  createFloor,
  updateFloor,
  deleteFloor,
  type FloorItem,
} from '@/services/floors'
import {
  fetchRoomsByLocation,
  createRoom,
  updateRoom,
  deleteRoom,
  type RoomItem,
} from '@/services/rooms'
import FloorFormModal from './FloorFormModal.vue'
import RoomFormModal from './RoomFormModal.vue'

interface Props {
  locationId: number
  canManageFloors: boolean
  canManageRooms: boolean
}
const props = defineProps<Props>()

const { t } = useI18n()
const toast = useToastStore()
const confirmStore = useConfirmStore()
const authStore = useAuthStore()

// ----- State -----
const floors = ref<FloorItem[]>([])
const rooms = ref<RoomItem[]>([])
const loading = ref(false)

// Sort preferenciák — user_settings-ből töltődnek, és változáskor visszamentődnek
type FloorSort = 'name' | 'level'
type RoomSort = 'name' | 'number' | 'type'
const floorSort = ref<FloorSort>(
  (authStore.user?.settings?.locations_floors_sort as FloorSort) ?? 'level',
)
const roomSort = ref<RoomSort>(
  (authStore.user?.settings?.locations_rooms_sort as RoomSort) ?? 'name',
)

// Per-session összecsukási állapot (NEM perzisztáljuk)
const collapsedFloorIds = ref<Set<number>>(new Set())

// Gyorskereső a helyiségek között (név/szám/típus)
const roomSearch = ref('')

function matchesSearch(r: RoomItem, q: string): boolean {
  if (!q) return true
  const fields = [r.name, r.number, r.type].filter(Boolean) as string[]
  return fields.some((f) => f.toLowerCase().includes(q))
}

const filteredRooms = computed<RoomItem[]>(() => {
  const q = roomSearch.value.trim().toLowerCase()
  if (!q) return rooms.value
  return rooms.value.filter((r) => matchesSearch(r, q))
})

const hasAnySearchMatch = computed(() => filteredRooms.value.length > 0)

// ----- Sort persistence -----
async function setFloorSort(value: FloorSort) {
  floorSort.value = value
  await persistSetting('locations_floors_sort', value)
}

async function setRoomSort(value: RoomSort) {
  roomSort.value = value
  await persistSetting('locations_rooms_sort', value)
}

async function persistSetting(key: string, value: string) {
  try {
    await api.put('/settings', { [key]: value })
    if (authStore.user?.settings) {
      ;(authStore.user.settings as any)[key] = value
    }
  } catch {
    /* silent: lokálisan már érvényben van */
  }
}

// ----- Sort logic -----
const sortedFloors = computed<FloorItem[]>(() => {
  let arr = [...floors.value]
  if (floorSort.value === 'name') {
    arr.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }))
  } else {
    arr.sort((a, b) => a.level - b.level || a.name.localeCompare(b.name))
  }
  // Keresésnél csak azokat a szinteket mutatjuk, ahol van találat
  if (roomSearch.value.trim()) {
    arr = arr.filter((f) => (roomsByFloor.value[f.id]?.length ?? 0) > 0)
  }
  return arr
})

function sortRooms(list: RoomItem[]): RoomItem[] {
  const arr = [...list]
  if (roomSort.value === 'number') {
    arr.sort((a, b) => {
      const an = a.number ?? ''
      const bn = b.number ?? ''
      return an.localeCompare(bn, undefined, { numeric: true, sensitivity: 'base' })
        || a.name.localeCompare(b.name)
    })
  } else if (roomSort.value === 'type') {
    arr.sort((a, b) => {
      const at = a.type ?? ''
      const bt = b.type ?? ''
      return at.localeCompare(bt) || a.name.localeCompare(b.name)
    })
  } else {
    arr.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }))
  }
  return arr
}

const roomsByFloor = computed<Record<number, RoomItem[]>>(() => {
  const map: Record<number, RoomItem[]> = {}
  for (const r of filteredRooms.value) {
    if (r.floor_id == null) continue
    if (!map[r.floor_id]) map[r.floor_id] = []
    map[r.floor_id].push(r)
  }
  for (const k of Object.keys(map)) {
    map[Number(k)] = sortRooms(map[Number(k)])
  }
  return map
})
const unassignedRooms = computed(() => sortRooms(filteredRooms.value.filter(r => r.floor_id == null)))

// ----- Collapse -----
function toggleFloor(floorId: number) {
  if (collapsedFloorIds.value.has(floorId)) {
    collapsedFloorIds.value.delete(floorId)
  } else {
    collapsedFloorIds.value.add(floorId)
  }
  collapsedFloorIds.value = new Set(collapsedFloorIds.value)
}

function isCollapsed(floorId: number): boolean {
  return collapsedFloorIds.value.has(floorId)
}

function expandAll() {
  collapsedFloorIds.value = new Set()
}

function collapseAll() {
  collapsedFloorIds.value = new Set(floors.value.map(f => f.id))
}

const allCollapsed = computed(() =>
  floors.value.length > 0 && floors.value.every(f => collapsedFloorIds.value.has(f.id)),
)

// ----- Modal state -----
const editingFloor = ref<FloorItem | null>(null)
const showFloorModal = ref(false)
const editingRoom = ref<RoomItem | null>(null)
const showRoomModal = ref(false)
const roomModalDefaultFloorId = ref<number | null>(null)

async function load() {
  loading.value = true
  try {
    const [fl, rm] = await Promise.all([
      fetchFloors(props.locationId),
      fetchRoomsByLocation(props.locationId),
    ])
    floors.value = fl
    rooms.value = rm
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  } finally {
    loading.value = false
  }
}

// ----- Floor műveletek -----
function onAddFloor() {
  editingFloor.value = null
  showFloorModal.value = true
}

function onEditFloor(floor: FloorItem) {
  editingFloor.value = floor
  showFloorModal.value = true
}

async function onSaveFloor(payload: { name: string; level: number; description: string | null }) {
  try {
    if (editingFloor.value) {
      await updateFloor(editingFloor.value.id, payload)
      toast.success(t('locations.floor_updated'))
    } else {
      await createFloor(props.locationId, payload)
      toast.success(t('locations.floor_created'))
    }
    showFloorModal.value = false
    await load()
  } catch (err: any) {
    if (err.response?.data?.errors) throw err
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  }
}

async function onDeleteFloor(floor: FloorItem) {
  const ok = await confirmStore.ask({
    title: t('locations.floor_deleted'),
    message: t('locations.floor_delete_confirm', { name: floor.name }),
    confirmText: t('common.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteFloor(floor.id)
    toast.success(t('locations.floor_deleted'))
    await load()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  }
}

// ----- Room műveletek -----
function onAddRoom(floorId: number | null = null) {
  editingRoom.value = null
  roomModalDefaultFloorId.value = floorId
  showRoomModal.value = true
}

function onEditRoom(room: RoomItem) {
  editingRoom.value = room
  showRoomModal.value = true
}

async function onSaveRoom(payload: {
  floor_id: number | null
  name: string
  number: string | null
  type: string | null
  area_sqm: number | null
  description: string | null
}) {
  try {
    if (editingRoom.value) {
      await updateRoom(editingRoom.value.id, payload)
      toast.success(t('locations.room_updated'))
    } else {
      await createRoom(props.locationId, payload)
      toast.success(t('locations.room_created'))
    }
    showRoomModal.value = false
    await load()
  } catch (err: any) {
    if (err.response?.data?.errors) throw err
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  }
}

async function onDeleteRoom(room: RoomItem) {
  const ok = await confirmStore.ask({
    title: t('locations.room_deleted'),
    message: t('locations.room_delete_confirm', { name: room.name }),
    confirmText: t('common.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteRoom(room.id)
    toast.success(t('locations.room_deleted'))
    await load()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <!-- Fejléc: cím + sort/expand/collapse + akciógombok -->
    <div class="flex items-start justify-between flex-wrap gap-3">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('locations.floors_section') }}</h2>
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Floor sort toggle -->
        <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-xs">
          <span class="px-2 py-1.5 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 border-r border-gray-300 dark:border-gray-600">
            {{ t('locations.floors_sort_label') }}
          </span>
          <button
            type="button"
            @click="setFloorSort('level')"
            class="px-2 py-1.5 transition-colors"
            :class="floorSort === 'level'
              ? 'bg-teal-600 text-white'
              : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
          >
            {{ t('locations.floors_sort_level') }}
          </button>
          <button
            type="button"
            @click="setFloorSort('name')"
            class="px-2 py-1.5 border-l border-gray-300 dark:border-gray-600 transition-colors"
            :class="floorSort === 'name'
              ? 'bg-teal-600 text-white'
              : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
          >
            {{ t('locations.floors_sort_name') }}
          </button>
        </div>

        <!-- Mind ki/be gomb -->
        <button
          v-if="floors.length > 1"
          type="button"
          @click="allCollapsed ? expandAll() : collapseAll()"
          class="text-xs px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
        >
          {{ allCollapsed ? t('locations.expand_all') : t('locations.collapse_all') }}
        </button>

        <!-- Akciógombok -->
        <button
          v-if="canManageRooms"
          type="button"
          @click="onAddRoom(null)"
          class="text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
        >
          + {{ t('locations.room_new') }}
        </button>
        <button
          v-if="canManageFloors"
          type="button"
          @click="onAddFloor"
          class="text-sm px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg"
        >
          + {{ t('locations.floor_new') }}
        </button>
      </div>
    </div>

    <!-- Helyiség-rendezés (csak ha van legalább egy helyiség) -->
    <div v-if="rooms.length > 0" class="flex items-center gap-2">
      <span class="text-xs text-gray-500 dark:text-gray-400">{{ t('locations.rooms_sort_label') }}</span>
      <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-xs">
        <button
          type="button"
          @click="setRoomSort('name')"
          class="px-2 py-1 transition-colors"
          :class="roomSort === 'name'
            ? 'bg-teal-600 text-white'
            : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
        >
          {{ t('locations.rooms_sort_name') }}
        </button>
        <button
          type="button"
          @click="setRoomSort('number')"
          class="px-2 py-1 border-l border-gray-300 dark:border-gray-600 transition-colors"
          :class="roomSort === 'number'
            ? 'bg-teal-600 text-white'
            : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
        >
          {{ t('locations.rooms_sort_number') }}
        </button>
        <button
          type="button"
          @click="setRoomSort('type')"
          class="px-2 py-1 border-l border-gray-300 dark:border-gray-600 transition-colors"
          :class="roomSort === 'type'
            ? 'bg-teal-600 text-white'
            : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
        >
          {{ t('locations.rooms_sort_type') }}
        </button>
      </div>
    </div>

    <!-- Helyiség-gyorskereső (csak ha legalább 4 helyiség van) -->
    <div v-if="rooms.length >= 4" class="relative">
      <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <input
        v-model="roomSearch"
        type="text"
        :placeholder="t('locations.rooms_search_placeholder')"
        class="block w-full pl-9 pr-9 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500"
      />
      <button
        v-if="roomSearch"
        @click="roomSearch = ''"
        class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
      <div class="animate-spin w-6 h-6 border-4 border-teal-500 border-t-transparent rounded-full mx-auto"></div>
    </div>

    <!-- Üres állapot (semmi sincs) -->
    <div
      v-else-if="floors.length === 0 && rooms.length === 0"
      class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center"
    >
      <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
          d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
      </svg>
      <p class="text-sm text-gray-600 dark:text-gray-300 font-medium">{{ t('locations.floors_empty') }}</p>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ t('locations.floors_empty_hint') }}</p>
    </div>

    <!-- Keresés - nincs találat -->
    <div
      v-else-if="roomSearch && !hasAnySearchMatch"
      class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-10 text-center text-sm text-gray-500 dark:text-gray-400"
    >
      {{ t('locations.search_no_match') }}
    </div>

    <template v-else>
      <!-- Szintek + bennük helyiségek -->
      <div
        v-for="floor in sortedFloors"
        :key="floor.id"
        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
      >
        <!-- Szint header (kattintható összecsukáshoz) -->
        <div
          class="p-4 flex items-center justify-between gap-3 flex-wrap hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
          :class="!isCollapsed(floor.id) ? 'border-b border-gray-200 dark:border-gray-700' : ''"
        >
          <button
            type="button"
            @click="toggleFloor(floor.id)"
            class="flex items-center gap-3 min-w-0 flex-1 text-left"
          >
            <svg
              class="w-4 h-4 text-gray-400 transition-transform shrink-0"
              :class="isCollapsed(floor.id) ? '' : 'rotate-90'"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <div class="shrink-0 w-10 h-10 rounded-lg bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
              <span class="text-sm font-mono font-bold text-teal-700 dark:text-teal-300">{{ floor.level }}</span>
            </div>
            <div class="min-w-0">
              <p class="font-medium text-gray-900 dark:text-white truncate">{{ floor.name }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                <template v-if="(roomsByFloor[floor.id] ?? []).length > 0">
                  {{ t('locations.floor_rooms_count', { n: (roomsByFloor[floor.id] ?? []).length }) }}
                </template>
                <template v-else>
                  {{ t('locations.floor_rooms_count_zero') }}
                </template>
              </p>
            </div>
          </button>
          <div class="flex items-center gap-1">
            <button
              v-if="canManageRooms"
              type="button"
              @click="onAddRoom(floor.id)"
              class="text-xs px-2.5 py-1 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              + {{ t('locations.room_new') }}
            </button>
            <button
              v-if="canManageFloors"
              type="button"
              @click="onEditFloor(floor)"
              class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded"
              :title="t('locations.floor_edit')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
            </button>
            <button
              v-if="canManageFloors"
              type="button"
              @click="onDeleteFloor(floor)"
              class="p-1.5 text-red-400 hover:text-red-600 rounded"
              :title="t('common.delete')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Helyiségek a szinten (ha nincs összecsukva) -->
        <div
          v-show="!isCollapsed(floor.id) && (roomsByFloor[floor.id] ?? []).length > 0"
          class="divide-y divide-gray-100 dark:divide-gray-700/50"
        >
          <div
            v-for="room in roomsByFloor[floor.id]"
            :key="room.id"
            class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/30"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium text-gray-900 dark:text-white truncate">{{ room.name }}</span>
                <span v-if="room.number" class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ room.number }}</span>
                <span v-if="room.type" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                  {{ room.type }}
                </span>
                <span v-if="room.area_sqm" class="text-xs text-gray-500 dark:text-gray-400">{{ room.area_sqm }} m²</span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button
                v-if="canManageRooms"
                type="button"
                @click="onEditRoom(room)"
                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </button>
              <button
                v-if="canManageRooms"
                type="button"
                @click="onDeleteRoom(room)"
                class="p-1 text-red-400 hover:text-red-600 rounded"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Szint nélküli helyiségek -->
      <div
        v-if="unassignedRooms.length > 0"
        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
      >
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <p class="font-medium text-gray-900 dark:text-white">{{ t('locations.room_unassigned_section') }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('locations.room_unassigned_hint') }}</p>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
          <div
            v-for="room in unassignedRooms"
            :key="room.id"
            class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/30"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium text-gray-900 dark:text-white truncate">{{ room.name }}</span>
                <span v-if="room.number" class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ room.number }}</span>
                <span v-if="room.type" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                  {{ room.type }}
                </span>
                <span v-if="room.area_sqm" class="text-xs text-gray-500 dark:text-gray-400">{{ room.area_sqm }} m²</span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button
                v-if="canManageRooms"
                type="button"
                @click="onEditRoom(room)"
                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </button>
              <button
                v-if="canManageRooms"
                type="button"
                @click="onDeleteRoom(room)"
                class="p-1 text-red-400 hover:text-red-600 rounded"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modálok -->
    <FloorFormModal
      v-if="showFloorModal"
      :floor="editingFloor"
      @close="showFloorModal = false"
      @save="onSaveFloor"
    />
    <RoomFormModal
      v-if="showRoomModal"
      :location-id="locationId"
      :room="editingRoom"
      :floors="floors"
      :default-floor-id="roomModalDefaultFloorId"
      @close="showRoomModal = false"
      @save="onSaveRoom"
    />
  </div>
</template>
