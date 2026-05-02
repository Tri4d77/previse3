<script setup lang="ts">
/**
 * ContactCard - Újrafelhasználható popover/kártya kontakt-adatok megjelenítésére.
 *
 * Használat:
 *   <ContactCard :name="..." role="..." phone="..." email="..." note="..." />
 *
 * Default trigger: a slotba helyezett tartalom (vagy ha nincs, akkor a név).
 * Click-popover (NEM hover), kívülre kattintás vagy ESC bezárja.
 */
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToastStore } from '@/stores/toast'

interface Props {
  name: string
  role?: string | null
  phone?: string | null
  email?: string | null
  note?: string | null
  avatarUrl?: string | null
}
const props = defineProps<Props>()

const { t } = useI18n()
const toast = useToastStore()

const open = ref(false)
const wrapperEl = ref<HTMLElement | null>(null)
const popoverEl = ref<HTMLElement | null>(null)
const popoverStyle = ref<Record<string, string>>({})

function toggle() {
  open.value = !open.value
  if (open.value) nextTick(positionPopover)
}

function close() {
  open.value = false
}

function positionPopover() {
  if (!wrapperEl.value || !popoverEl.value) return
  const rect = wrapperEl.value.getBoundingClientRect()
  const pop = popoverEl.value
  const popWidth = pop.offsetWidth
  const popHeight = pop.offsetHeight
  const margin = 8
  let top = rect.bottom + margin
  let left = rect.left
  // jobbra-ki ellenőrzés
  if (left + popWidth > window.innerWidth - margin) {
    left = window.innerWidth - popWidth - margin
  }
  // alulra-ki ellenőrzés -> fölé tesszük
  if (top + popHeight > window.innerHeight - margin) {
    top = rect.top - popHeight - margin
  }
  popoverStyle.value = {
    top: `${Math.max(margin, top)}px`,
    left: `${Math.max(margin, left)}px`,
  }
}

function onDocClick(e: MouseEvent) {
  if (!open.value) return
  const target = e.target as Node
  if (
    wrapperEl.value && !wrapperEl.value.contains(target) &&
    popoverEl.value && !popoverEl.value.contains(target)
  ) {
    close()
  }
}

function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') close()
}

async function copyText(text: string, msg: string) {
  try {
    await navigator.clipboard.writeText(text)
    toast.success(msg)
  } catch {
    /* noop */
  }
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onEsc)
  window.addEventListener('resize', positionPopover)
  window.addEventListener('scroll', positionPopover, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onEsc)
  window.removeEventListener('resize', positionPopover)
  window.removeEventListener('scroll', positionPopover, true)
})

const initials = (s: string): string =>
  s
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((x) => x.charAt(0).toUpperCase())
    .join('')
</script>

<template>
  <span ref="wrapperEl" class="inline-block">
    <button
      type="button"
      @click.stop="toggle"
      class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline focus:outline-none"
    >
      <slot>{{ name }}</slot>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="popoverEl"
        :style="popoverStyle"
        class="fixed z-[60] w-72 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 p-4"
        @click.stop
      >
        <div class="flex items-start gap-3">
          <div
            class="shrink-0 w-12 h-12 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 flex items-center justify-center font-semibold overflow-hidden"
          >
            <img v-if="avatarUrl" :src="avatarUrl" :alt="name" class="w-full h-full object-cover" />
            <span v-else>{{ initials(name) }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-gray-900 dark:text-white truncate">{{ name }}</div>
            <div v-if="role" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ role }}</div>
          </div>
        </div>

        <div class="mt-3 space-y-2 text-sm">
          <div v-if="phone" class="flex items-center gap-2">
            <a :href="`tel:${phone}`" class="flex-1 text-gray-700 dark:text-gray-200 hover:text-teal-600 truncate">
              {{ phone }}
            </a>
            <button
              type="button"
              @click="copyText(phone!, t('locations.contact_copied'))"
              :title="t('locations.contact_copy_phone')"
              class="p-1 text-gray-400 hover:text-teal-600"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
          </div>

          <div v-if="email" class="flex items-center gap-2">
            <a :href="`mailto:${email}`" class="flex-1 text-gray-700 dark:text-gray-200 hover:text-teal-600 truncate">
              {{ email }}
            </a>
            <button
              type="button"
              @click="copyText(email!, t('locations.contact_copied'))"
              :title="t('locations.contact_copy_email')"
              class="p-1 text-gray-400 hover:text-teal-600"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
          </div>

          <div v-if="note" class="pt-2 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-300 whitespace-pre-line">
            {{ note }}
          </div>
        </div>
      </div>
    </Teleport>
  </span>
</template>
