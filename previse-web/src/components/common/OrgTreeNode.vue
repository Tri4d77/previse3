<script setup lang="ts">
import { ref } from 'vue'
import type { OrganizationNode } from '@/types'

interface Props {
  node: OrganizationNode
  currentOrgId?: number
  depth: number
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'select', orgId: number): void
}>()

const isExpanded = ref(true) // alapból kibontva
const isActive = props.currentOrgId === props.node.id
const hasChildren = props.node.children && props.node.children.length > 0

function handleClick() {
  emit('select', props.node.id)
}

function toggleExpand(event: Event) {
  event.stopPropagation()
  isExpanded.value = !isExpanded.value
}

function iconForType(type: string): string {
  const map: Record<string, string> = {
    platform: '🏛️',
    subscriber: '🏢',
    client: '🏬',
  }
  return map[type] || '🏢'
}
</script>

<template>
  <div>
    <button
      @click="handleClick"
      class="w-full text-left flex items-center gap-1.5 py-1.5 pr-2 text-sm rounded transition-colors"
      :class="[
        isActive ? 'bg-teal-600/30 text-white' : 'text-slate-300 hover:bg-slate-800',
      ]"
      :style="{ paddingLeft: (depth * 16 + 8) + 'px' }"
    >
      <!-- Expand gomb -->
      <span
        v-if="hasChildren"
        @click="toggleExpand"
        class="w-4 h-4 flex items-center justify-center text-slate-400 hover:text-white shrink-0"
      >
        <svg v-if="isExpanded" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
        <svg v-else class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
        </svg>
      </span>
      <span v-else class="w-4 h-4 shrink-0"></span>

      <!-- Ikon típus szerint -->
      <span class="text-sm shrink-0">{{ iconForType(node.type) }}</span>

      <!-- Név -->
      <span class="truncate flex-1">{{ node.name }}</span>

      <!-- Aktív jelölő -->
      <svg v-if="isActive" class="w-4 h-4 text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
      </svg>
    </button>

    <!-- Gyerekek rekurzívan -->
    <div v-if="hasChildren && isExpanded">
      <OrgTreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :current-org-id="currentOrgId"
        :depth="depth + 1"
        @select="(id) => emit('select', id)"
      />
    </div>
  </div>
</template>
