<script setup lang="ts">
/**
 * Globális megerősítő dialog. Az App.vue-ban van egyszer mountolva, és a
 * useConfirmStore() Pinia store-ra hallgat. A meglévő ConfirmDialog komponenst
 * használja vizuális megjelenítéshez.
 *
 * Használat tetszőleges komponensben:
 *   const confirm = useConfirmStore()
 *   const ok = await confirm.ask({ title: '...', message: '...', variant: 'danger' })
 */
import { onMounted, onBeforeUnmount } from 'vue'
import { useConfirmStore } from '@/stores/confirm'
import ConfirmDialog from './ConfirmDialog.vue'

const store = useConfirmStore()

function onKey(e: KeyboardEvent) {
  if (!store.pending) return
  if (e.key === 'Escape') {
    e.stopPropagation()
    store.answer(false)
  } else if (e.key === 'Enter') {
    e.stopPropagation()
    store.answer(true)
  }
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <ConfirmDialog
    v-if="store.pending"
    :title="store.pending.title"
    :message="store.pending.message ?? ''"
    :confirm-text="store.pending.confirmText"
    :cancel-text="store.pending.cancelText"
    :variant="store.pending.variant ?? 'danger'"
    @confirm="store.answer(true)"
    @cancel="store.answer(false)"
  />
</template>
