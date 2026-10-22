import { http } from './http'
import type {
  LoginResponse,
  NewUserPayload,
  Paginated,
  Task,
  TaskPayload,
  TaskQuery,
  User,
} from '@/types'

/** Drop null/empty params so the query string stays clean. */
function clean<T extends object>(params: T): Partial<T> {
  return Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== ''),
  ) as Partial<T>
}

export const authApi = {
  login: (email: string, password: string) =>
    http.post<LoginResponse>('/login', { email, password }).then((r) => r.data),
  logout: () => http.post('/logout'),
  me: () => http.get<User>('/me').then((r) => r.data),
}

export const tasksApi = {
  list: (query: TaskQuery) => http.get<Paginated<Task>>('/tasks', { params: clean(query) }).then((r) => r.data),
  get: (id: number) => http.get<Task>(`/tasks/${id}`).then((r) => r.data),
  create: (payload: TaskPayload) => http.post<Task>('/tasks', clean(payload)).then((r) => r.data),
  update: (id: number, payload: Partial<TaskPayload>) => http.put<Task>(`/tasks/${id}`, payload).then((r) => r.data),
  remove: (id: number) => http.delete(`/tasks/${id}`),
}

export const usersApi = {
  list: () => http.get<User[]>('/users').then((r) => r.data),
  create: (payload: NewUserPayload) => http.post<User>('/users', payload).then((r) => r.data),
}
