import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: number
  type: ToastType
  title: string
  message?: string
  duration: number // ms, 0 = nem tűnik el automatikusan
}

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  let nextId = 1

  function show(options: {
    type: ToastType
    title: string
    message?: string
    duration?: number
  }): number {
    const id = nextId++
    const toast: Toast = {
      id,
      type: options.type,
      title: options.title,
      message: options.message,
      duration: options.duration ?? 4000,
    }
    toasts.value.push(toast)

    if (toast.duration > 0) {
      setTimeout(() => dismiss(id), toast.duration)
    }

    return id
  }

  function success(title: string, message?: string, duration?: number): number {
    return show({ type: 'success', title, message, duration })
  }

  function error(title: string, message?: string, duration?: number): number {
    return show({ type: 'error', title, message, duration: duration ?? 6000 })
  }

  function warning(title: string, message?: string, duration?: number): number {
    return show({ type: 'warning', title, message, duration })
  }

  function info(title: string, message?: string, duration?: number): number {
    return show({ type: 'info', title, message, duration })
  }

  function dismiss(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  function clear() {
    toasts.value = []
  }

  return { toasts, show, success, error, warning, info, dismiss, clear }
})
