import api from './api'

export type TagColor =
  | 'slate' | 'gray' | 'red' | 'orange' | 'amber' | 'yellow'
  | 'lime' | 'green' | 'teal' | 'cyan' | 'blue' | 'indigo'
  | 'violet' | 'purple' | 'pink' | 'rose'

export const TAG_COLORS: TagColor[] = [
  'slate', 'gray', 'red', 'orange', 'amber', 'yellow',
  'lime', 'green', 'teal', 'cyan', 'blue', 'indigo',
  'violet', 'purple', 'pink', 'rose',
]

export interface LocationTag {
  id: number
  name: string
  color: TagColor
  sort_order: number
}

export interface LocationTagPayload {
  name: string
  color: TagColor
  sort_order?: number
}

/**
 * Tailwind színosztályok minden engedélyezett tag-színhez.
 * Light + dark mód egyaránt.
 */
export function tagBadgeClass(color: TagColor): string {
  const map: Record<TagColor, string> = {
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-200',
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-700/40 dark:text-gray-200',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    lime: 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    teal: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
    cyan: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
    pink: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
  }
  return map[color] ?? map.teal
}

export function tagSwatchClass(color: TagColor): string {
  const map: Record<TagColor, string> = {
    slate: 'bg-slate-500',
    gray: 'bg-gray-500',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    amber: 'bg-amber-500',
    yellow: 'bg-yellow-500',
    lime: 'bg-lime-500',
    green: 'bg-green-500',
    teal: 'bg-teal-500',
    cyan: 'bg-cyan-500',
    blue: 'bg-blue-500',
    indigo: 'bg-indigo-500',
    violet: 'bg-violet-500',
    purple: 'bg-purple-500',
    pink: 'bg-pink-500',
    rose: 'bg-rose-500',
  }
  return map[color] ?? map.teal
}

// ---- Catalog (org) ----

export async function fetchTags(): Promise<LocationTag[]> {
  const r = await api.get('/location-tags')
  return r.data.data
}

export async function createTag(payload: LocationTagPayload): Promise<LocationTag> {
  const r = await api.post('/location-tags', payload)
  return r.data.data
}

export async function updateTag(id: number, payload: Partial<LocationTagPayload>): Promise<LocationTag> {
  const r = await api.put(`/location-tags/${id}`, payload)
  return r.data.data
}

export async function deleteTag(id: number): Promise<void> {
  await api.delete(`/location-tags/${id}`)
}

export async function reorderTags(ids: number[]): Promise<LocationTag[]> {
  const r = await api.post('/location-tags/reorder', { ids })
  return r.data.data
}

// ---- Per-location assignment ----

export async function fetchLocationTags(locationId: number): Promise<LocationTag[]> {
  const r = await api.get(`/locations/${locationId}/tags`)
  return r.data.data
}

export async function syncLocationTags(
  locationId: number,
  tagIds: number[],
): Promise<LocationTag[]> {
  const r = await api.put(`/locations/${locationId}/tags`, { tag_ids: tagIds })
  return r.data.data
}
