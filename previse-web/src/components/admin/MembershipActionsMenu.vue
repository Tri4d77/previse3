<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  toggleMembershipActive,
  resendInvitation,
  deleteMembership,
  restoreMembership,
  updateMembership,
  type MembershipItem,
  type SimpleRole,
} from '@/services/memberships'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

interface Props {
  membership: MembershipItem
  roles: SimpleRole[]
}
const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'updated'): void
  (e: 'deleted'): void
  (e: 'restored', invitationUrl: string): void
  (e: 'resent', invitationUrl: string): void
}>()

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()

const isOpen = ref(false)
const triggerRef = ref<HTMLButtonElement | null>(null)
const menuPosition = ref({ top: 0, left: 0 })
const busy = ref(false)
const showRoleEditor = ref(false)
const newRoleId = ref<number | null>(null)

const isSelf = computed(() => props.membership.user.id === auth.user?.id)
const isPending = computed(() => props.membership.status === 'pending' || props.membership.status === 'expired')
const isDeleted = computed(() => props.membership.status === 'deleted')
const isActive = computed(() => props.membership.status === 'active')

async function openMenu() {
  isOpen.value = !isOpen.value
  if (!isOpen.value) return
  await nextTick()
  if (triggerRef.value) {
    const r = triggerRef.value.getBoundingClientRect()
    const menuWidth = 220
    const left = r.right - menuWidth < 0 ? r.left : r.right - menuWidth
    menuPosition.value = {
      top: r.bottom + 4,
      left: Math.max(8, left),
    }
  }
}

function closeMenu() {
  isOpen.value = false
  showRoleEditor.value = false
}

function onScrollOrResize() {
  if (isOpen.value) closeMenu()
}

function onDocumentClick(e: MouseEvent) {
  const target = e.target as Node
  if (triggerRef.value && triggerRef.value.contains(target)) return
  // Teleport-olt menü is benne van a body-ban; a "data-actions-menu" attribute-tal jelöljük
  const menuEl = document.querySelector('[data-actions-menu]')
  if (menuEl && menuEl.contains(target)) return
  closeMenu()
}

onMounted(() => {
  window.addEventListener('scroll', onScrollOrResize, true)
  window.addEventListener('resize', onScrollOrResize)
  document.addEventListener('click', onDocumentClick)
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScrollOrResize, true)
  window.removeEventListener('resize', onScrollOrResize)
  document.removeEventListener('click', onDocumentClick)
})

async function withBusy<T>(fn: () => Promise<T>): Promise<T | null> {
  busy.value = true
  try {
    return await fn()
  } catch (err: any) {
    toast.error(err.response?.data?.message ?? t('common.error_generic'))
    return null
  } finally {
    busy.value = false
  }
}

async function onToggleActive() {
  if (isSelf.value) return
  const r = await withBusy(() => toggleMembershipActive(props.membership.id))
  if (r) {
    toast.success(r.is_active ? t('users.activated') : t('users.deactivated'))
    emit('updated')
  }
  closeMenu()
}

async function onResend() {
  const r = await withBusy(() => resendInvitation(props.membership.id))
  if (r) {
    toast.success(t('users.invitation_resent'))
    emit('resent', r.invitation_url)
  }
  closeMenu()
}

async function onDelete() {
  if (isSelf.value) return
  if (!confirm(t('users.confirm_delete', { name: props.membership.user.name }))) return
  const r = await withBusy(() => deleteMembership(props.membership.id))
  if (r !== null) {
    toast.success(t('users.deleted'))
    emit('deleted')
  }
  closeMenu()
}

async function onRestore() {
  const r = await withBusy(() => restoreMembership(props.membership.id))
  if (r) {
    toast.success(t('users.restored'))
    emit('restored', r.invitation_url)
  }
  closeMenu()
}

function startRoleEdit() {
  newRoleId.value = props.membership.role.id
  showRoleEditor.value = true
}

async function saveNewRole() {
  if (!newRoleId.value || newRoleId.value === props.membership.role.id) {
    showRoleEditor.value = false
    return
  }
  const r = await withBusy(() => updateMembership(props.membership.id, { role_id: newRoleId.value! }))
  if (r) {
    toast.success(t('users.role_updated'))
    emit('updated')
  }
  closeMenu()
}
</script>

<template>
  <div class="inline-block">
    <button
      ref="triggerRef"
      @click.stop="openMenu"
      :disabled="busy"
      class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50"
      :title="t('common.actions')"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
      </svg>
    </button>

    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="isOpen"
          data-actions-menu
          class="fixed w-56 rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 py-1 z-[100]"
          :style="{ top: menuPosition.top + 'px', left: menuPosition.left + 'px' }"
        >
          <!-- Saját magát figyelmeztetjük -->
          <div v-if="isSelf" class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400 italic border-b border-gray-100 dark:border-gray-700">
            {{ t('users.self_notice') }}
          </div>

          <!-- Szerepkör módosítás -->
          <div v-if="!isDeleted && !showRoleEditor">
            <button
              @click.stop="startRoleEdit"
              class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
              :disabled="isSelf"
              :class="isSelf ? 'opacity-40 cursor-not-allowed' : ''"
            >
              <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
              {{ t('users.change_role') }}
            </button>
          </div>

          <!-- Szerepkör szerkesztő -->
          <div v-if="showRoleEditor" class="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
              {{ t('users.new_role') }}
            </label>
            <select
              v-model="newRoleId"
              class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded"
            >
              <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
            <div class="flex gap-2 mt-2">
              <button
                @click.stop="saveNewRole"
                class="flex-1 px-2 py-1 text-xs bg-teal-600 text-white rounded hover:bg-teal-700"
              >
                {{ t('common.save') }}
              </button>
              <button
                @click.stop="showRoleEditor = false"
                class="px-2 py-1 text-xs text-gray-500 hover:text-gray-700"
              >
                {{ t('common.cancel') }}
              </button>
            </div>
          </div>

          <!-- Aktiválás / deaktiválás (nem pending, nem törölt) -->
          <button
            v-if="!isPending && !isDeleted"
            @click.stop="onToggleActive"
            :disabled="isSelf"
            :class="isSelf ? 'opacity-40 cursor-not-allowed' : ''"
            class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
          >
            <svg v-if="isActive" class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728" />
            </svg>
            <svg v-else class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {{ isActive ? t('users.deactivate') : t('users.activate') }}
          </button>

          <!-- Meghívó újraküldése (csak pending) -->
          <button
            v-if="isPending"
            @click.stop="onResend"
            class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
          >
            <svg class="w-4 h-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            {{ t('users.resend_invitation') }}
          </button>

          <!-- Visszaállítás (törölt) -->
          <button
            v-if="isDeleted"
            @click.stop="onRestore"
            class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
          >
            <svg class="w-4 h-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            {{ t('users.restore') }}
          </button>

          <!-- Elválasztó -->
          <div v-if="!isDeleted" class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

          <!-- Törlés (kivéve önmagát és már töröltet) -->
          <button
            v-if="!isDeleted"
            @click.stop="onDelete"
            :disabled="isSelf"
            :class="isSelf ? 'opacity-40 cursor-not-allowed' : ''"
            class="w-full text-left px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3" />
            </svg>
            {{ t('users.delete') }}
          </button>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
