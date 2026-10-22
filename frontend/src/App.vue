<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotify } from '@/stores/notify'

const auth = useAuthStore()
const notify = useNotify()
const route = useRoute()
const router = useRouter()

const drawer = ref(true)
const isBlankLayout = computed(() => route.meta.layout === 'blank')

const navItems = computed(() => [
  { title: 'Tasks', icon: 'mdi-format-list-checks', to: '/tasks' },
  ...(auth.isAdmin ? [{ title: 'Users', icon: 'mdi-account-group', to: '/users' }] : []),
])

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <v-app>
    <template v-if="isBlankLayout">
      <v-main>
        <router-view />
      </v-main>
    </template>

    <template v-else>
      <v-app-bar color="primary" flat>
        <v-app-bar-nav-icon @click="drawer = !drawer" />
        <v-app-bar-title>Team Task Manager</v-app-bar-title>

        <template v-if="auth.user" #append>
          <div class="d-flex align-center ga-2 mr-2">
            <v-icon icon="mdi-account-circle" />
            <span class="text-body-2 d-none d-sm-inline">{{ auth.user.name }}</span>
            <v-chip
              size="small"
              variant="elevated"
              :color="auth.isAdmin ? 'amber' : 'white'"
              class="text-uppercase"
            >
              {{ auth.user.role }}
            </v-chip>
            <v-btn icon="mdi-logout" variant="text" title="Log out" @click="logout" />
          </div>
        </template>
      </v-app-bar>

      <v-navigation-drawer v-model="drawer">
        <v-list nav density="comfortable">
          <v-list-item
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            :prepend-icon="item.icon"
            :title="item.title"
          />
        </v-list>
      </v-navigation-drawer>

      <v-main>
        <v-container fluid class="pa-4 pa-md-6">
          <router-view />
        </v-container>
      </v-main>
    </template>

    <v-snackbar v-model="notify.visible" :color="notify.color" timeout="3000" location="bottom right">
      {{ notify.message }}
    </v-snackbar>
  </v-app>
</template>
