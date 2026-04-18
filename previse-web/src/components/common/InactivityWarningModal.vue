<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'

interface Props {
  countdownSeconds: number
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'lock'): void
  (e: 'continue'): void
  (e: 'timeout'): void
}>()

const remaining = ref(props.countdownSeconds)
let intervalId: ReturnType<typeof setInterval> | null = null

// Kör progress (a circular progress ring-hez)
const progressPercent = computed(() =>
  (remaining.value / props.countdownSeconds) * 100
)

// Körkörös progress ring hossza (SVG circle path)
const CIRCUMFERENCE = 2 * Math.PI * 28 // sugár = 28
const strokeDashoffset = computed(() =>
  CIRCUMFERENCE - (progressPercent.value / 100) * CIRCUMFERENCE
)

onMounted(() => {
  intervalId = setInterval(() => {
    remaining.value--
    if (remaining.value <= 0) {
      if (intervalId !== null) {
        clearInterval(intervalId)
        intervalId = null
      }
      emit('timeout')
    }
  }, 1000)
})

onBeforeUnmount(() => {
  if (intervalId !== null) {
    clearInterval(intervalId)
  }
})

function handleLock() {
  emit('lock')
}

function handleContinue() {
  emit('continue')
}
</script>

<template>
  <Teleport to="body">
    <!-- Overlay -->
    <div class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 animate-fade-in">
      <!-- Modal -->
      <div
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700 animate-scale-in"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="warning-title"
      >
        <!-- Fejléc: körkörös visszaszámláló -->
        <div class="flex flex-col items-center mb-4">
          <div class="relative w-20 h-20 mb-3">
            <!-- SVG progress ring -->
            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 64 64">
              <!-- Háttér kör -->
              <circle
                cx="32"
                cy="32"
                r="28"
                fill="none"
                class="stroke-gray-200 dark:stroke-gray-700"
                stroke-width="4"
              />
              <!-- Progress kör -->
              <circle
                cx="32"
                cy="32"
                r="28"
                fill="none"
                class="stroke-amber-500 transition-all duration-1000 ease-linear"
                stroke-width="4"
                stroke-linecap="round"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="strokeDashoffset"
              />
            </svg>
            <!-- Középső szám -->
            <div class="absolute inset-0 flex items-center justify-center">
              <span class="text-2xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">
                {{ remaining }}
              </span>
            </div>
          </div>

          <!-- Figyelmeztető ikon -->
          <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 mb-1">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <h3 id="warning-title" class="text-lg font-semibold">Hamarosan zárolás</h3>
          </div>
        </div>

        <!-- Szöveg -->
        <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-6">
          Inaktivitás miatt a képernyő
          <strong class="text-amber-600 dark:text-amber-400 tabular-nums">{{ remaining }}</strong>
          másodperc múlva automatikusan zárolódik.
          <br>
          Szeretnéd folytatni a munkát?
        </p>

        <!-- Gombok -->
        <div class="flex flex-col sm:flex-row gap-2">
          <button
            @click="handleLock"
            class="flex-1 py-2.5 px-4 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium rounded-lg transition-colors text-sm flex items-center justify-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            Zárolás most
          </button>
          <button
            @click="handleContinue"
            class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md focus:ring-4 focus:ring-teal-300 dark:focus:ring-teal-800 transition-all duration-200 text-sm"
            autofocus
          >
            Folytatom a munkát
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes scale-in {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.animate-fade-in {
  animation: fade-in 0.2s ease-out;
}

.animate-scale-in {
  animation: scale-in 0.2s ease-out;
}
</style>
