import { ref } from 'vue'
import imageCompression from 'browser-image-compression'

export function useImageField() {
  const DEFAULT_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
  const DEFAULT_MAX_SIZE_BYTES = 5 * 1024 * 1024
  const COMPRESSIBLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp']
  const COMPRESSION_OPTIONS = {
    maxSizeMB: 1,
    maxWidthOrHeight: 1600,
    useWebWorker: true,
    initialQuality: 0.8,
  }

  const inputRef = ref(null)
  const file = ref(null)
  const preview = ref(null)
  const remove = ref(false)

  function clearInput() {
    if (inputRef.value) inputRef.value.value = ''
  }

  function reset() {
    file.value = null
    preview.value = null
    remove.value = false
    clearInput()
  }

  function setPreview(url = null) {
    file.value = null
    preview.value = url || null
    remove.value = false
    clearInput()
  }

  async function handleChange(event, options = {}) {
    const allowedMimeTypes = options.allowedMimeTypes || DEFAULT_ALLOWED_MIME_TYPES
    const maxSizeBytes = options.maxSizeBytes || DEFAULT_MAX_SIZE_BYTES

    const selectedFile = event?.target?.files?.[0] || null
    if (!selectedFile) return { ok: false, error: null }

    const mimeType = selectedFile.type || ''
    if (!allowedMimeTypes.includes(mimeType)) {
      clearInput()
      return { ok: false, error: 'Formato no permitido. Usa JPG, PNG, GIF o WEBP.' }
    }

    let processedFile = selectedFile
    if (COMPRESSIBLE_MIME_TYPES.includes(mimeType)) {
      try { processedFile = await imageCompression(selectedFile, COMPRESSION_OPTIONS) } catch { processedFile = selectedFile }
    }

    if (processedFile.size > maxSizeBytes) {
      clearInput()
      return { ok: false, error: 'La imagen sigue siendo demasiado grande. Máximo 5MB.' }
    }

    file.value = processedFile
    remove.value = false

    const reader = new FileReader()
    reader.onload = (loadEvent) => { preview.value = loadEvent?.target?.result || null }
    reader.readAsDataURL(selectedFile)

    return { ok: true, error: null }
  }

  function removeSelection() {
    file.value = null
    preview.value = null
    remove.value = true
    clearInput()
  }

  return { inputRef, file, preview, remove, reset, setPreview, handleChange, removeSelection }
}
