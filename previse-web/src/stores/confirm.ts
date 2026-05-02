import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ConfirmVariant = 'danger' | 'warning' | 'info'

export interface ConfirmRequest {
  title: string
  message?: string
  confirmText?: string
  cancelText?: string
  variant?: ConfirmVariant
}

interface PendingRequest extends ConfirmRequest {
  id: number
  resolve: (value: boolean) => void
}

/**
 * Globális megerősítő-dialog store. A dialog komponens (ConfirmDialog.vue)
 * az App.vue-ban van mountolva, és innen kapja meg az aktuális kérést.
 *
 * Használat: const ok = await useConfirmStore().ask({...})
 */
export const useConfirmStore = defineStore('confirm', () => {
  const pending = ref<PendingRequest | null>(null)
  let nextId = 1

  function ask(req: ConfirmRequest): Promise<boolean> {
    return new Promise((resolve) => {
      pending.value = {
        ...req,
        id: nextId++,
        resolve,
      }
    })
  }

  function answer(value: boolean) {
    if (!pending.value) return
    const { resolve } = pending.value
    pending.value = null
    resolve(value)
  }

  return { pending, ask, answer }
})
