<script setup lang="ts">
import { computed } from 'vue'
import type { TaskStatus } from '@/types'
import { STATUS_META } from '@/utils/task'

const props = defineProps<{ status: TaskStatus }>()
const meta = computed(() => STATUS_META[props.status])
</script>

<template>
  <v-chip :color="meta.color" size="small" variant="flat" label>{{ meta.label }}</v-chip>
</template>
