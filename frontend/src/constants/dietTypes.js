export const DIET_TYPE_OPTIONS = [
  { code: 'vegan', label: 'Vegano', symbol: '🌿' },
  { code: 'vegetarian', label: 'Vegetariano', symbol: '🥦' },
  { code: 'gluten_free', label: 'Sin Gluten', symbol: '✳️' },
  { code: 'lactose_free', label: 'Sin Lactosa', symbol: '🥛' },
  { code: 'keto', label: 'Keto', symbol: '🥑' },
  { code: 'halal', label: 'Halal', symbol: '☪️' },
  { code: 'spicy', label: 'Picante', symbol: '🌶️' },
  { code: 'low_calorie', label: 'Bajo en calorías', symbol: '🏃' },
]

export const DIET_TYPE_MAP = Object.fromEntries(
  DIET_TYPE_OPTIONS.map(d => [d.code, d])
)

export function getDietTypeMeta(code) {
  return DIET_TYPE_MAP[code] || { code, label: code, symbol: '❓' }
}
