import { ref, onBeforeUnmount, onMounted } from 'vue'

/**
 * Inaktivitás-figyelő composable.
 *
 * A megadott eseményeket figyeli (egér, billentyűzet, scroll, érintés).
 * Ha az adott időn belül nem történik aktivitás, meghívja az `onTimeout` callback-et.
 *
 * Fontos: amíg pause állapotban van, az aktivitás NEM reseteli a timert.
 * Erre akkor van szükség, amikor pl. egy figyelmeztető modal látszik és nem
 * akarjuk, hogy a modal gombjainak mozgatása reset-elje az eredeti timert.
 *
 * @param timeoutMs - inaktivitási időkorlát ms-ban
 * @param onTimeout - callback, amikor lejár az idő
 */
export function useInactivityTimer(timeoutMs: number, onTimeout: () => void) {
  let timerId: ReturnType<typeof setTimeout> | null = null
  const isPaused = ref(false)

  const events: (keyof DocumentEventMap)[] = [
    'mousedown',
    'mousemove',
    'keypress',
    'scroll',
    'touchstart',
    'click',
  ]

  function resetTimer() {
    if (timerId !== null) {
      clearTimeout(timerId)
      timerId = null
    }
    if (!isPaused.value) {
      timerId = setTimeout(() => {
        onTimeout()
      }, timeoutMs)
    }
  }

  function handleActivity() {
    if (!isPaused.value) {
      resetTimer()
    }
  }

  /**
   * Szünetelteti az aktivitás figyelést és leállítja a timert.
   * Amíg pause-olva van, az aktivitás nem resetel.
   */
  function pause() {
    isPaused.value = true
    if (timerId !== null) {
      clearTimeout(timerId)
      timerId = null
    }
  }

  /**
   * Feloldja a szüneteltetést és újraindítja a timert.
   */
  function resume() {
    isPaused.value = false
    resetTimer()
  }

  onMounted(() => {
    events.forEach((event) => {
      document.addEventListener(event, handleActivity, { passive: true })
    })
    resetTimer()
  })

  onBeforeUnmount(() => {
    events.forEach((event) => {
      document.removeEventListener(event, handleActivity)
    })
    if (timerId !== null) {
      clearTimeout(timerId)
    }
  })

  return {
    resetTimer,
    pause,
    resume,
    isPaused,
  }
}
