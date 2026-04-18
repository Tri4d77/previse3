<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  password: string
}

const props = defineProps<Props>()

interface Requirement {
  label: string
  met: boolean
}

const requirements = computed<Requirement[]>(() => [
  { label: 'Legalább 8 karakter', met: props.password.length >= 8 },
  { label: 'Tartalmaz kisbetűt (a-z)', met: /[a-z]/.test(props.password) },
  { label: 'Tartalmaz nagybetűt (A-Z)', met: /[A-Z]/.test(props.password) },
  { label: 'Tartalmaz számot (0-9)', met: /\d/.test(props.password) },
  { label: 'Tartalmaz speciális karaktert (!@#$%^&*)', met: /[!@#$%^&*(),.?":{}|<>_\-+=/\\[\]~`';]/.test(props.password) },
])

// Erősség pont: 0-5
const strengthScore = computed(() => requirements.value.filter(r => r.met).length)

// Üzenet és szín
const strength = computed(() => {
  if (props.password.length === 0) {
    return { label: '', color: 'bg-gray-200 dark:bg-gray-600', text: '' }
  }
  if (strengthScore.value <= 1) {
    return { label: 'Nagyon gyenge', color: 'bg-red-500', text: 'text-red-600 dark:text-red-400' }
  }
  if (strengthScore.value === 2) {
    return { label: 'Gyenge', color: 'bg-orange-500', text: 'text-orange-600 dark:text-orange-400' }
  }
  if (strengthScore.value === 3) {
    return { label: 'Közepes', color: 'bg-amber-500', text: 'text-amber-600 dark:text-amber-400' }
  }
  if (strengthScore.value === 4) {
    return { label: 'Erős', color: 'bg-green-500', text: 'text-green-600 dark:text-green-400' }
  }
  return { label: 'Nagyon erős', color: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400' }
})

// Progress bar kitöltés (0-5 szegmens)
function segmentColor(index: number): string {
  if (index >= strengthScore.value) {
    return 'bg-gray-200 dark:bg-gray-600'
  }
  return strength.value.color
}

// Valid-e a jelszó (a minimum követelmények: 8+ karakter, kisbetű, nagybetű, szám)
// A speciális karakter bonus, nem kötelező
const isValid = computed(() => {
  return requirements.value.slice(0, 4).every(r => r.met)
})

defineExpose({ isValid })
</script>

<template>
  <div v-if="password.length > 0" class="space-y-2">
    <!-- Progress bar -->
    <div class="space-y-1.5">
      <div class="flex gap-1">
        <div
          v-for="i in 5"
          :key="i"
          class="h-1.5 flex-1 rounded-full transition-colors duration-200"
          :class="segmentColor(i - 1)"
        ></div>
      </div>
      <div class="flex justify-between items-center text-xs">
        <span :class="strength.text" class="font-medium">{{ strength.label }}</span>
        <span class="text-gray-500 dark:text-gray-400">{{ strengthScore }} / 5</span>
      </div>
    </div>

    <!-- Követelmények listája -->
    <ul class="space-y-1 text-xs">
      <li
        v-for="req in requirements"
        :key="req.label"
        class="flex items-center gap-2"
        :class="req.met ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
      >
        <svg v-if="req.met" class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
        <svg v-else class="w-3.5 h-3.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" />
        </svg>
        <span>{{ req.label }}</span>
      </li>
    </ul>
  </div>
</template>
