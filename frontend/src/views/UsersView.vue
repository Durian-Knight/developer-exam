<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { VForm } from 'vuetify/components'
import { usersApi } from '@/api'
import { toApiError } from '@/api/http'
import { useNotify } from '@/stores/notify'
import type { NewUserPayload, User } from '@/types'

const notify = useNotify()

const users = ref<User[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'Role', key: 'role' },
]

const dialog = ref(false)
const formRef = ref<InstanceType<typeof VForm> | null>(null)
const form = reactive<NewUserPayload>({ name: '', email: '', password: '', role: 'user' })
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})

const required = (v: string) => !!v || 'Required'
const minPassword = (v: string) => v.length >= 8 || 'At least 8 characters'

async function load() {
  loading.value = true
  loadError.value = null
  try {
    users.value = await usersApi.list()
  } catch (e) {
    loadError.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}

function openDialog() {
  Object.assign(form, { name: '', email: '', password: '', role: 'user' })
  fieldErrors.value = {}
  dialog.value = true
}

async function save() {
  const result = await formRef.value?.validate()
  if (!result?.valid) return

  saving.value = true
  fieldErrors.value = {}
  try {
    const user = await usersApi.create({ ...form })
    notify.success(`User ${user.name} created`)
    dialog.value = false
    load()
  } catch (e) {
    const apiError = toApiError(e)
    fieldErrors.value = apiError.fieldErrors
    if (apiError.status !== 422) notify.error(apiError.message)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Users</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-account-plus" @click="openDialog">New user</v-btn>
    </div>

    <v-alert v-if="loadError" type="error" variant="tonal" class="mb-4">{{ loadError }}</v-alert>

    <v-card>
      <v-data-table :headers="headers" :items="users" :loading="loading" item-value="id">
        <template #[`item.role`]="{ item }">
          <v-chip size="small" :color="item.role === 'admin' ? 'amber-darken-2' : 'grey'" label>
            {{ item.role }}
          </v-chip>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="480" persistent>
      <v-card title="New user">
        <v-card-text>
          <v-form ref="formRef" @submit.prevent="save">
            <v-text-field v-model="form.name" label="Name" :rules="[required]" :error-messages="fieldErrors.name" />
            <v-text-field
              v-model="form.email"
              label="Email"
              type="email"
              :rules="[required]"
              :error-messages="fieldErrors.email"
            />
            <v-text-field
              v-model="form.password"
              label="Password"
              type="password"
              autocomplete="new-password"
              :rules="[required, minPassword]"
              :error-messages="fieldErrors.password"
            />
            <v-select
              v-model="form.role"
              label="Role"
              :items="[
                { title: 'User', value: 'user' },
                { title: 'Admin', value: 'admin' },
              ]"
              :error-messages="fieldErrors.role"
            />
            <div class="d-flex justify-end ga-2">
              <v-btn variant="text" :disabled="saving" @click="dialog = false">Cancel</v-btn>
              <v-btn type="submit" color="primary" :loading="saving">Create</v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>
