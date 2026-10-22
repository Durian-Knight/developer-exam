import type { TaskStatus } from '@/types'

export const STATUS_META: Record<TaskStatus, { label: string; color: string }> = {
  todo: { label: 'To do', color: 'grey' },
  in_progress: { label: 'In progress', color: 'blue' },
  done: { label: 'Done', color: 'green' },
}

export const STATUS_OPTIONS = (Object.keys(STATUS_META) as TaskStatus[]).map((value) => ({
  value,
  title: STATUS_META[value].label,
}))

/** Date -> 'YYYY-MM-DD' using local time (toISOString would shift by the UTC offset). */
export function toYmd(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** 'YYYY-MM-DD' -> local Date */
export function fromYmd(value: string | null): Date | null {
  if (!value) return null
  const [y, m, d] = value.split('-').map(Number) as [number, number, number]
  return new Date(y, m - 1, d)
}

export function formatDate(value: string | null): string {
  const date = fromYmd(value)
  return date ? date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '—'
}

export function isOverdue(value: string | null, status: TaskStatus): boolean {
  const date = fromYmd(value)
  if (!date || status === 'done') return false
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return date < today
}
