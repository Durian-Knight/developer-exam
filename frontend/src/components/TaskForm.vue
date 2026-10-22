<script setup lang="ts">
/**
 * Reusable create/edit form.
 *  - No `task` prop  -> create mode (POST /api/tasks)
 *  - With `task`     -> edit mode   (PUT  /api/tasks/{id})
 * Emits `saved` with the API response, or `cancel`.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { VForm } from 'vuetify/components'
import { tasksApi, usersApi } from '@/api'
import { toApiError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import type { Task, TaskPayload, User } from '@/types'
import { STATUS_OPTIONS, fromYmd, toYmd } from '@/utils/task'

const props = withDefaults(
  defineProps<{
    task?: Task | null
    /** Show the Cancel button (e.g. when used inside a dialog). */
    cancellable?: boolean
  }>(),
  { task: null, cancellable: true },
)

const emit = defineEmits<{
  saved: [task: Task]
  cancel: []
}>()

const auth = useAuthStore()
const isEdit = computed(() => props.task !== null)

const formRef = ref<InstanceType<typeof VForm> | null>(null)
const form = reactive<TaskPayload>(emptyForm())
const users = ref<User[]>([])
const loadingUsers = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const dateMenu = ref(false)

function emptyForm(): TaskPayload {
  return {
    title: '',
    description: null,
    status: 'todo',
    due_date: null,
    assigned_to: auth.user?.id ?? null,
  }
}

function fillFrom(task: Task | null) {
  Object.assign(
    form,
    task
      ? {
          title: task.title,
          description: task.description,
          status: task.status,
          due_date: task.due_date,
          assigned_to: task.assigned_to,
        }
      : emptyForm(),
  )
  fieldErrors.value = {}
  errorMessage.value = null
}

watch(() => props.task, fillFrom, { immediate: true })

const rules = {
  required: (v: unknown) => (v !== null && v !== undefined && String(v).trim() !== '') || 'Required',
  max255: (v: string) => !v || v.length <= 255 || 'Max 255 characters',
}

// Clear a server-side error as soon as the user edits that field.
watch(
  () => ({ ...form }),
  (now, before) => {
    for (const key of Object.keys(now) as (keyof TaskPayload)[]) {
      if (now[key] !== before[key]) delete fieldErrors.value[key]
    }
  },
)

const dueDate = computed(() => fromYmd(form.due_date))

function onPickDate(value: unknown) {
  if (value instanceof Date) {
    form.due_date = toYmd(value)
    dateMenu.value = false
  }
}

async function loadUsers() {
  loadingUsers.value = true
  try {
    users.value = await usersApi.list()
  } catch (e) {
    errorMessage.value = `Could not load users: ${toApiError(e).message}`
  } finally {
    loadingUsers.value = false
  }
}

onMounted(loadUsers)

async function submit() {
  const result = await formRef.value?.validate()
  if (!result?.valid) return

  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  const payload: TaskPayload = {
    ...form,
    title: form.title.trim(),
    description: form.description?.trim() || null,
  }

  try {
    const saved = props.task ? await tasksApi.update(props.task.id, payload) : await tasksApi.create(payload)
    emit('saved', saved)
    if (!props.task) fillFrom(null)
  } catch (e) {
    const apiError = toApiError(e)
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.fieldErrors
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <v-form ref="formRef" @submit.prevent="submit">
    <v-alert v-if="errorMessage" type="error" variant="tonal" density="compact" class="mb-4">
      {{ errorMessage }}
    </v-alert>

    <v-text-field
      v-model="form.title"
      label="Title *"
      counter="255"
      :rules="[rules.required, rules.max255]"
      :error-messages="fieldErrors.title"
      autofocus
    />

    <v-textarea
      v-model="form.description"
      label="Description"
      rows="3"
      auto-grow
      :error-messages="fieldErrors.description"
    />

    <v-row dense>
      <v-col cols="12" sm="6">
        <v-select
          v-model="form.status"
          label="Status"
          :items="STATUS_OPTIONS"
          :error-messages="fieldErrors.status"
        />
      </v-col>

      <v-col cols="12" sm="6">
        <v-select
          v-model="form.assigned_to"
          label="Assignee"
          :items="users"
          item-title="name"
          item-value="id"
          :loading="loadingUsers"
          :disabled="!auth.isAdmin"
          :hint="auth.isAdmin ? '' : 'You can only assign tasks to yourself'"
          :persistent-hint="!auth.isAdmin"
          :rules="[rules.required]"
          :error-messages="fieldErrors.assigned_to"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps" :subtitle="item.raw.email" />
          </template>
        </v-select>
      </v-col>

      <v-col cols="12" sm="6">
        <v-menu v-model="dateMenu" :close-on-content-click="false" location="bottom start">
          <template #activator="{ props: menuProps }">
            <v-text-field
              v-bind="menuProps"
              :model-value="form.due_date ?? ''"
              label="Due date"
              placeholder="YYYY-MM-DD"
              prepend-inner-icon="mdi-calendar"
              readonly
              clearable
              :error-messages="fieldErrors.due_date"
              @click:clear="form.due_date = null"
            />
          </template>
          <v-date-picker
            :model-value="dueDate"
            show-adjacent-months
            @update:model-value="onPickDate"
          />
        </v-menu>
      </v-col>
    </v-row>

    <div class="d-flex justify-end ga-2 mt-2">
      <v-btn v-if="cancellable" variant="text" :disabled="submitting" @click="emit('cancel')">Cancel</v-btn>
      <v-btn type="submit" color="primary" :loading="submitting" prepend-icon="mdi-content-save">
        {{ isEdit ? 'Save changes' : 'Create task' }}
      </v-btn>
    </div>
  </v-form>
</template>
