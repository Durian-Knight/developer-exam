<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { tasksApi } from '@/api'
import { toApiError } from '@/api/http'
import StatusChip from '@/components/StatusChip.vue'
import TaskForm from '@/components/TaskForm.vue'
import { useNotify } from '@/stores/notify'
import type { Task } from '@/types'

const props = defineProps<{ id: number }>()

const router = useRouter()
const notify = useNotify()

const task = ref<Task | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

async function load(id: number) {
  loading.value = true
  error.value = null
  try {
    task.value = await tasksApi.get(id)
  } catch (e) {
    const apiError = toApiError(e)
    error.value =
      apiError.status === 404
        ? 'This task does not exist (or was deleted).'
        : apiError.status === 403
          ? 'You do not have access to this task.'
          : apiError.message
  } finally {
    loading.value = false
  }
}

watch(() => props.id, load, { immediate: true })

function onSaved(updated: Task) {
  task.value = updated
  notify.success('Task saved')
  router.push('/tasks')
}
</script>

<template>
  <div style="max-width: 760px">
    <v-btn variant="text" prepend-icon="mdi-arrow-left" to="/tasks" class="mb-2 px-0">Back to tasks</v-btn>

    <v-skeleton-loader v-if="loading && !task" type="article, actions" />

    <v-alert v-else-if="error" type="error" variant="tonal">{{ error }}</v-alert>

    <v-card v-else-if="task">
      <v-card-item>
        <v-card-title class="d-flex align-center ga-2">
          Edit task #{{ task.id }}
          <StatusChip :status="task.status" />
        </v-card-title>
        <v-card-subtitle>
          Assigned to {{ task.assignee?.name ?? '—' }} · updated {{ new Date(task.updated_at).toLocaleString() }}
        </v-card-subtitle>
      </v-card-item>
      <v-card-text>
        <TaskForm :task="task" @saved="onSaved" @cancel="router.push('/tasks')" />
      </v-card-text>
    </v-card>
  </div>
</template>
