<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { VForm } from 'vuetify/components'
import { useAuthStore } from '@/stores/auth'
import { toApiError } from '@/api/http'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const formRef = ref<InstanceType<typeof VForm> | null>(null)
const form = reactive({ email: '', password: '' })
const showPassword = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const rules = {
  required: (v: string) => !!v || 'Required',
  email: (v: string) => /.+@.+\..+/.test(v) || 'Invalid email',
}

async function submit() {
  const result = await formRef.value?.validate()
  if (!result?.valid) return

  submitting.value = true
  error.value = null
  fieldErrors.value = {}
  try {
    await auth.login(form.email, form.password)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/tasks'
    router.replace(redirect.startsWith('/') ? redirect : '/tasks')
  } catch (e) {
    const apiError = toApiError(e)
    // Backend reports bad credentials as a 422 on "email" - show it as a banner.
    error.value = apiError.fieldErrors.email?.[0] ?? apiError.message
    fieldErrors.value = apiError.fieldErrors
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <v-container class="fill-height" fluid>
    <v-row justify="center" align="center">
      <v-col cols="12" sm="8" md="5" lg="4">
        <v-card elevation="4" class="pa-2">
          <v-card-item>
            <v-card-title class="text-h5">Sign in</v-card-title>
            <v-card-subtitle>Team Task Manager</v-card-subtitle>
          </v-card-item>

          <v-card-text>
            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
              {{ error }}
            </v-alert>

            <v-form ref="formRef" @submit.prevent="submit">
              <v-text-field
                v-model="form.email"
                label="Email"
                type="email"
                autocomplete="username"
                prepend-inner-icon="mdi-email-outline"
                :rules="[rules.required, rules.email]"
                class="mb-2"
              />
              <v-text-field
                v-model="form.password"
                label="Password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                :rules="[rules.required]"
                :error-messages="fieldErrors.password"
                @click:append-inner="showPassword = !showPassword"
              />
              <v-btn type="submit" color="primary" block size="large" class="mt-2" :loading="submitting">
                Sign in
              </v-btn>
            </v-form>
          </v-card-text>

          <v-card-text class="text-caption text-medium-emphasis pt-0">
            Demo: admin@example.com / alice@example.com — password: <code>password</code>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
