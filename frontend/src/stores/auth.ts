import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api'
import { tokenStorage } from '@/api/http'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(tokenStorage.get())
  const user = ref<User | null>(null)
  let loading: Promise<void> | null = null

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function login(email: string, password: string) {
    const res = await authApi.login(email, password)
    token.value = res.token
    user.value = res.user
    tokenStorage.set(res.token)
  }

  async function logout() {
    try {
      if (token.value) await authApi.logout()
    } finally {
      clear()
    }
  }

  function clear() {
    token.value = null
    user.value = null
    tokenStorage.clear()
  }

  /** Restore the session after a page reload (token in storage, user not yet loaded). */
  function ensureLoaded(): Promise<void> {
    if (!token.value || user.value) return Promise.resolve()
    loading ??= authApi
      .me()
      .then((me) => {
        user.value = me
      })
      .catch(() => clear())
      .finally(() => {
        loading = null
      })
    return loading
  }

  return { token, user, isAuthenticated, isAdmin, login, logout, clear, ensureLoaded }
})
