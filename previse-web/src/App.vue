<script setup lang="ts">
import { RouterView } from 'vue-router'
import { watch, onMounted } from 'vue'
import ToastContainer from '@/components/common/ToastContainer.vue'
import GlobalConfirmDialog from '@/components/common/GlobalConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useLocale } from '@/composables/useLocale'

const authStore = useAuthStore()
const { syncLocaleFromUser } = useLocale()

// Bejelentkezés után sync-eljük a locale-t a user beállításából
onMounted(() => {
  if (authStore.isAuthenticated) {
    syncLocaleFromUser()
  }
})

watch(
  () => authStore.user,
  (user) => {
    if (user) syncLocaleFromUser()
  },
)
</script>

<template>
  <RouterView />
  <ToastContainer />
  <GlobalConfirmDialog />
</template>
