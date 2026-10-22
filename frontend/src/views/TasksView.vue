<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { tasksApi, usersApi } from '@/api'
import { toApiError } from '@/api/http'
import StatusChip from '@/components/StatusChip.vue'
import TaskForm from '@/components/TaskForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useNotify } from '@/stores/notify'
import type { Task, TaskQuery, TaskStatus, User } from '@/types'
import { STATUS_OPTIONS, formatDate, isOverdue } from '@/utils/task'

/** Shape of the options object emitted by <v-data-table-server>. */
interface TableOptions {
  page: number
  itemsPerPage: number
  sortBy: { key: string; order?: boolean | 'asc' | 'desc' }[]
}

const auth = useAuthStore()
const notify = useNotify()
const router = useRouter()

const headers = [
  { title: 'Title', key: 'title', sortable: true },
  { title: 'Assignee', key: 'assignee', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Due date', key: 'due_date', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' as const },
]

const SORTABLE = ['title', 'status', 'due_date'] as const
type SortKey = (typeof SORTABLE)[number]

const tasks = ref<Task[]>([])
const total = ref(0)
const loading = ref(false)
const loadError = ref<string | null>(null)

const filters = reactive<{ status: TaskStatus | null; assigned_to: number | null; search: string }>({
  status: null,
  assigned_to: null,
  search: '',
})
const options = ref<TableOptions>({ page: 1, itemsPerPage: 10, sortBy: [] })

// Admin-only "filter by assignee" dropdown.
const users = ref<User[]>([])

// Dialog state
const formDialog = ref(false)
const deleteTarget = ref<Task | null>(null)
const deleting = ref(false)

let requestSeq = 0

async function loadTasks() {
  const seq = ++requestSeq
  loading.value = true
  loadError.value = null

  const sort = options.value.sortBy[0]
  const query: TaskQuery = {
    page: options.value.page,
    per_page: options.value.itemsPerPage,
    status: filters.status,
    assigned_to: filters.assigned_to,
    search: filters.search.trim() || null,
  }
  if (sort && SORTABLE.includes(sort.key as SortKey)) {
    query.sort_by = sort.key as SortKey
    query.sort_dir = sort.order === 'asc' ? 'asc' : 'desc'
  }

  try {
    const res = await tasksApi.list(query)
    if (seq !== requestSeq) return // a newer request already started; ignore stale response
    tasks.value = res.data
    total.value = res.meta.total
  } catch (e) {
    if (seq !== requestSeq) return
    loadError.value = toApiError(e).message
  } finally {
    if (seq === requestSeq) loading.value = false
  }
}

function onOptionsUpdate(next: TableOptions) {
  options.value = next
  loadTasks()
}

// Filters reset to page 1. Search is debounced so we don't fire a request per keystroke.
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(
  () => filters.search,
  () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(resetToFirstPage, 350)
  },
)
watch(() => [filters.status, filters.assigned_to], resetToFirstPage)

function resetToFirstPage() {
  if (options.value.page !== 1) {
    options.value = { ...options.value, page: 1 } // table emits update:options -> loadTasks
  } else {
    loadTasks()
  }
}

onMounted(async () => {
  if (auth.isAdmin) {
    users.value = await usersApi.list().catch(() => [])
  }
})

function openTask(_: Event, row: { item: Task }) {
  router.push(`/tasks/${row.item.id}`)
}

function onCreated(task: Task) {
  formDialog.value = false
  notify.success(`Task "${task.title}" created`)
  loadTasks()
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await tasksApi.remove(deleteTarget.value.id)
    notify.success('Task deleted')
    deleteTarget.value = null
    // If we deleted the last row on this page, step back a page.
    if (tasks.value.length === 1 && options.value.page > 1) {
      options.value = { ...options.value, page: options.value.page - 1 }
    } else {
      loadTasks()
    }
  } catch (e) {
    notify.error(toApiError(e).message)
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex align-center flex-wrap ga-2 mb-4">
      <h1 class="text-h5">{{ auth.isAdmin ? 'All tasks' : 'My tasks' }}</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="formDialog = true">New task</v-btn>
    </div>

    <v-card>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="5">
            <v-text-field
              v-model="filters.search"
              label="Search by title"
              prepend-inner-icon="mdi-magnify"
              clearable
              hide-details
              @click:clear="filters.search = ''"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select
              v-model="filters.status"
              label="Status"
              :items="STATUS_OPTIONS"
              clearable
              hide-details
            />
          </v-col>
          <v-col v-if="auth.isAdmin" cols="12" sm="6" md="4">
            <v-select
              v-model="filters.assigned_to"
              label="Assignee"
              :items="users"
              item-title="name"
              item-value="id"
              clearable
              hide-details
            />
          </v-col>
        </v-row>
      </v-card-text>

      <v-alert v-if="loadError" type="error" variant="tonal" class="mx-4 mb-4">
        {{ loadError }}
        <template #append>
          <v-btn variant="text" size="small" @click="loadTasks">Retry</v-btn>
        </template>
      </v-alert>

      <v-data-table-server
        :headers="headers"
        :items="tasks"
        :items-length="total"
        :loading="loading"
        :page="options.page"
        :items-per-page="options.itemsPerPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="options.sortBy"
        loading-text="Loading tasks…"
        no-data-text="No tasks match your filters"
        item-value="id"
        hover
        @update:options="onOptionsUpdate"
        @click:row="openTask"
      >
        <template #[`item.title`]="{ item }">
          <div class="font-weight-medium">{{ item.title }}</div>
          <div v-if="item.description" class="text-caption text-medium-emphasis text-truncate" style="max-width: 360px">
            {{ item.description }}
          </div>
        </template>

        <template #[`item.assignee`]="{ item }">
          {{ item.assignee?.name ?? '—' }}
        </template>

        <template #[`item.status`]="{ item }">
          <StatusChip :status="item.status" />
        </template>

        <template #[`item.due_date`]="{ item }">
          <span :class="{ 'text-error font-weight-medium': isOverdue(item.due_date, item.status) }">
            {{ formatDate(item.due_date) }}
          </span>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn
            icon="mdi-pencil"
            size="small"
            variant="text"
            title="Edit"
            :to="`/tasks/${item.id}`"
            @click.stop
          />
          <v-btn
            icon="mdi-delete"
            size="small"
            variant="text"
            color="error"
            title="Delete"
            @click.stop="deleteTarget = item"
          />
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Create dialog -->
    <v-dialog v-model="formDialog" max-width="640" persistent>
      <v-card title="New task">
        <v-card-text>
          <TaskForm v-if="formDialog" @saved="onCreated" @cancel="formDialog = false" />
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Delete confirmation -->
    <v-dialog :model-value="deleteTarget !== null" max-width="420" @update:model-value="deleteTarget = null">
      <v-card title="Delete task?">
        <v-card-text>
          "<strong>{{ deleteTarget?.title }}</strong>" will be deleted.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="deleting" @click="deleteTarget = null">Cancel</v-btn>
          <v-btn color="error" variant="flat" :loading="deleting" @click="confirmDelete">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
