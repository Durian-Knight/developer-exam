import { defineStore } from 'pinia'
import { ref } from 'vue'

/** Global snackbar. Usage: useNotify().success('Saved') */
export const useNotify = defineStore('notify', () => {
  const visible = ref(false)
  const message = ref('')
  const color = ref<'success' | 'error' | 'info'>('info')

  function show(text: string, c: typeof color.value = 'info') {
    message.value = text
    color.value = c
    visible.value = true
  }

  return {
    visible,
    message,
    color,
    show,
    success: (text: string) => show(text, 'success'),
    error: (text: string) => show(text, 'error'),
  }
})
