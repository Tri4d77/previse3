<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  invitationUrl: string
  userName: string
  userEmail: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const copied = ref(false)

async function copyToClipboard() {
  try {
    await navigator.clipboard.writeText(props.invitationUrl)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // fallback
    const textarea = document.createElement('textarea')
    textarea.value = props.invitationUrl
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[95] bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 animate-fade-in">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full border border-gray-200 dark:border-gray-700 animate-scale-in">
        <!-- Header -->
        <div class="px-6 py-5 text-center border-b border-gray-200 dark:border-gray-700">
          <div class="inline-flex items-center justify-center w-14 h-14 bg-green-100 dark:bg-green-900/30 rounded-full mb-3">
            <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Meghívó sikeresen létrehozva</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            <strong>{{ props.userName }}</strong> ({{ props.userEmail }})
          </p>
        </div>

        <!-- Body -->
        <div class="px-6 py-5 space-y-4">
          <!-- Fejlesztői figyelmeztetés -->
          <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <div class="flex items-start gap-2">
              <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
              </svg>
              <div class="text-xs text-amber-800 dark:text-amber-200">
                <p class="font-semibold mb-1">Fejlesztői mód</p>
                <p>Az email szerver még nincs konfigurálva, ezért az email nem került kiküldésre. Másold ki a meghívó URL-t, és küldd el kézzel a felhasználónak, vagy teszteld másik böngészőben / inkognitó ablakban.</p>
              </div>
            </div>
          </div>

          <!-- URL mező -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              Meghívó link
            </label>
            <div class="flex gap-2">
              <input
                type="text"
                :value="props.invitationUrl"
                readonly
                class="flex-1 px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white font-mono text-xs"
                @click="($event.target as HTMLInputElement).select()"
              />
              <button
                @click="copyToClipboard"
                class="px-3 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg flex items-center gap-1.5 transition-colors"
              >
                <svg v-if="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                {{ copied ? 'Másolva!' : 'Másolás' }}
              </button>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              A link 7 napig érvényes.
            </p>
          </div>

          <!-- Teszteléshez tipp -->
          <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
            <p class="text-xs text-gray-600 dark:text-gray-400">
              <strong>Tipp:</strong> A teljes flow tesztelésére nyisd meg a linket egy <strong>incognito / privát böngésző</strong> ablakban, hogy ne zavarja a jelenlegi bejelentkezésedet.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 rounded-b-2xl">
          <button
            @click="emit('close')"
            class="px-4 py-2 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg shadow-sm"
          >
            Bezárás
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes scale-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.animate-fade-in { animation: fade-in 0.15s ease-out; }
.animate-scale-in { animation: scale-in 0.15s ease-out; }
</style>
