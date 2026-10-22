export type TaskStatus = 'todo' | 'in_progress' | 'done'
export type UserRole = 'admin' | 'user'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
}

export interface Task {
  id: number
  title: string
  description: string | null
  status: TaskStatus
  due_date: string | null // YYYY-MM-DD
  assigned_to: number
  assignee?: User
  created_at: string
  updated_at: string
}

export interface TaskPayload {
  title: string
  description: string | null
  status: TaskStatus
  due_date: string | null
  assigned_to: number | null
}

export interface TaskQuery {
  page?: number
  per_page?: number
  status?: TaskStatus | null
  assigned_to?: number | null
  search?: string | null
  sort_by?: 'id' | 'title' | 'status' | 'due_date' | 'created_at'
  sort_dir?: 'asc' | 'desc'
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface LoginResponse {
  token: string
  token_type: 'Bearer'
  user: User
}

export interface NewUserPayload {
  name: string
  email: string
  password: string
  role: UserRole
}
