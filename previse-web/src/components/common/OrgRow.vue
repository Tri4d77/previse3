<script setup lang="ts">
import { ref, computed, onBeforeUnmount, nextTick } from 'vue'
import type { OrganizationTreeNode, OrganizationItem, OrganizationStatus } from '@/services/organizations'

interface Props {
  node: OrganizationTreeNode
  depth: number
  expandedNodes: Set<number>
  isSuperAdmin?: boolean
  canManage?: boolean
  statusMenuOpenId?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  isSuperAdmin: false,
  canManage: false,
  statusMenuOpenId: null,
})

const emit = defineEmits<{
  (e: 'toggleExpand', id: number): void
  (e: 'toggleStatusMenu', id: number): void
  (e: 'askStatusChange', org: OrganizationItem, status: OrganizationStatus): void
  (e: 'addClient', parentId: number): void
}>()

const statusLabels: Record<string, string> = {
  active: 'Aktív',
  inactive: 'Inaktív',
  terminated: 'Megszűnt',
}

const typeIcons: Record<string, string> = {
  platform: '🏛️',
  subscriber: '🏢',
  client: '🏬',
}

const typeLabels: Record<string, string> = {
  platform: 'Platform',
  subscriber: 'Előfizető',
  client: 'Ügyfél',
}

const typeBadge: Record<string, string> = {
  platform: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
  subscriber: 'bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300',
  client: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
}

const statusBadge: Record<string, string> = {
  active: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
  inactive: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
  terminated: 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400',
}

const statusDot: Record<string, string> = {
  active: 'bg-green-500',
  inactive: 'bg-amber-500',
  terminated: 'bg-red-400',
}

// Referencia a status menü gombjára - a Teleport-os menü pozicionálásához
const statusButtonRef = ref<HTMLElement | null>(null)
const menuPosition = ref({ top: 0, left: 0 })

const isMenuOpen = computed(() => props.statusMenuOpenId === props.node.id)

async function handleStatusMenuClick() {
  emit('toggleStatusMenu', props.node.id)
  await nextTick()
  // Pozíció számítás a menü számára (a body-ba lesz teleportálva)
  if (statusButtonRef.value) {
    const rect = statusButtonRef.value.getBoundingClientRect()
    const menuWidth = 176 // w-44
    const viewportWidth = window.innerWidth
    // Ha nem férne el jobbra, balra mutatjuk
    const left = rect.right - menuWidth < 0
      ? rect.left
      : rect.right - menuWidth
    menuPosition.value = {
      top: rect.bottom + 4,
      left: Math.max(8, left),
    }
  }
}

function isOpen(): boolean {
  return props.expandedNodes.has(props.node.id)
}

function rowBgClass(): string {
  const map: Record<number, string> = {
    0: '',
    1: 'bg-gray-50/50 dark:bg-gray-700/20',
    2: 'bg-gray-50/80 dark:bg-gray-700/30',
  }
  return map[props.depth] ?? 'bg-gray-50 dark:bg-gray-700/40'
}

// Scroll / resize esetén zárjuk be a menüt
function handleScroll() {
  if (isMenuOpen.value) {
    emit('toggleStatusMenu', props.node.id) // bezárás
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('scroll', handleScroll, true)
  window.addEventListener('resize', handleScroll)
}

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('scroll', handleScroll, true)
    window.removeEventListener('resize', handleScroll)
  }
})
</script>

<template>
  <div>
    <!-- Row -->
    <div
      class="p-4 flex items-center gap-3"
      :class="rowBgClass()"
      :style="{ paddingLeft: (depth * 24 + 16) + 'px' }"
    >
      <!-- Expand gomb -->
      <button
        v-if="node.children && node.children.length > 0"
        @click="emit('toggleExpand', node.id)"
        class="p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 rounded"
      >
        <svg class="w-4 h-4 transition-transform" :class="isOpen() ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
      </button>
      <span v-else class="w-6 shrink-0"></span>

      <!-- Ikon -->
      <span class="text-xl shrink-0">{{ typeIcons[node.type] }}</span>

      <!-- Adatok -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ node.name }}</h3>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="typeBadge[node.type]">
            {{ typeLabels[node.type] }}
          </span>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge[node.status]">
            <span class="w-1.5 h-1.5 rounded-full" :class="statusDot[node.status]"></span>
            {{ statusLabels[node.status] }}
          </span>
        </div>
        <p v-if="node.email" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ node.email }}</p>
      </div>

      <!-- Műveletek -->
      <div class="flex items-center gap-1">
        <!-- + Ügyfél (csak subscriber alatt) -->
        <button
          v-if="node.type === 'subscriber' && canManage"
          @click="emit('addClient', node.id)"
          class="text-xs px-2 py-1 text-teal-600 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-900/20 rounded"
          title="Ügyfél-szervezet hozzáadása"
        >
          + Ügyfél
        </button>

        <!-- Status menu trigger -->
        <div v-if="node.type !== 'platform' && canManage" class="status-menu-wrapper relative inline-block">
          <button
            ref="statusButtonRef"
            @click="handleStatusMenuClick"
            class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
            title="Státusz módosítása"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Teleport-ált kontextus menü - a body-ba kerül, így nem vágódik el -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="transform opacity-0 scale-95"
        enter-to-class="transform opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="transform opacity-100 scale-100"
        leave-to-class="transform opacity-0 scale-95"
      >
        <div
          v-if="isMenuOpen"
          class="status-menu-wrapper fixed w-44 rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 py-1 z-[100]"
          :style="{ top: menuPosition.top + 'px', left: menuPosition.left + 'px' }"
        >
          <button
            @click="emit('askStatusChange', node, 'active')"
            :disabled="node.status === 'active'"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
            :class="node.status === 'active' ? 'opacity-40 cursor-not-allowed text-gray-500' : 'text-gray-700 dark:text-gray-200'"
          >
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
            Aktiválás
          </button>
          <button
            @click="emit('askStatusChange', node, 'inactive')"
            :disabled="node.status === 'inactive'"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
            :class="node.status === 'inactive' ? 'opacity-40 cursor-not-allowed text-gray-500' : 'text-gray-700 dark:text-gray-200'"
          >
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            Inaktiválás
          </button>
          <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
          <button
            @click="emit('askStatusChange', node, 'terminated')"
            :disabled="node.status === 'terminated'"
            class="w-full text-left px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2"
            :class="node.status === 'terminated' ? 'opacity-40 cursor-not-allowed' : ''"
          >
            <span class="w-2 h-2 rounded-full bg-red-400"></span>
            Megszüntetés
          </button>
        </div>
      </Transition>
    </Teleport>

    <!-- Gyerekek rekurzívan -->
    <template v-if="isOpen() && node.children && node.children.length > 0">
      <OrgRow
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :expanded-nodes="expandedNodes"
        :is-super-admin="isSuperAdmin"
        :can-manage="canManage"
        :status-menu-open-id="statusMenuOpenId"
        @toggle-expand="(id) => emit('toggleExpand', id)"
        @toggle-status-menu="(id) => emit('toggleStatusMenu', id)"
        @ask-status-change="(org, status) => emit('askStatusChange', org, status)"
        @add-client="(parentId) => emit('addClient', parentId)"
      />
    </template>
  </div>
</template>
