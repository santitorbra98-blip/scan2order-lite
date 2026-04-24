export const ALLERGEN_OPTIONS = [
  { code: 'gluten', label: 'Gluten', symbol: '🌾' },
  { code: 'crustaceans', label: 'Crustáceos', symbol: '🦐' },
  { code: 'eggs', label: 'Huevos', symbol: '🥚' },
  { code: 'fish', label: 'Pescado', symbol: '🐟' },
  { code: 'peanuts', label: 'Cacahuetes', symbol: '🥜' },
  { code: 'soy', label: 'Soja', symbol: '🫘' },
  { code: 'milk', label: 'Lácteos', symbol: '🥛' },
  { code: 'nuts', label: 'Frutos secos', symbol: '🌰' },
  { code: 'celery', label: 'Apio', symbol: '🌿' },
  { code: 'mustard', label: 'Mostaza', symbol: '🟡' },
  { code: 'sesame', label: 'Sésamo', symbol: '⚪' },
  { code: 'sulfites', label: 'Sulfitos', symbol: '🧂' },
  { code: 'lupins', label: 'Altramuces', symbol: '🌱' },
  { code: 'mollusks', label: 'Moluscos', symbol: '🦑' },
]

export const ALLERGEN_MAP = Object.fromEntries(
  ALLERGEN_OPTIONS.map(a => [a.code, a])
)

export function getAllergenMeta(code) {
  return ALLERGEN_MAP[code] || { code, label: code, symbol: '❓' }
}
