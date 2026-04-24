<template>
  <div class="qr-modal-overlay" @click.self="$emit('close')">
    <div class="qr-modal">
      <div class="qr-header">
        <h2>📱 QR de {{ restaurantName }}</h2>
        <button @click="$emit('close')" class="btn-close">×</button>
      </div>
      <div class="qr-body">
        <div ref="qrContainer" class="qr-canvas"></div>
        <p class="qr-url">{{ menuUrl }}</p>
        <div class="qr-actions">
          <button @click="downloadQr" class="btn-download">⬇️ Descargar PNG</button>
          <button @click="printQr" class="btn-print">🖨️ Imprimir</button>
          <button @click="copyUrl" class="btn-copy">📋 Copiar enlace</button>
        </div>
        <p v-if="copied" class="copied-msg">✅ Enlace copiado</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  restaurantId: { type: [Number, String], required: true },
  restaurantName: { type: String, default: 'Restaurante' },
})

defineEmits(['close'])

const qrContainer = ref(null)
const copied = ref(false)
const menuUrl = computed(() => `${window.location.origin}/restaurant/${props.restaurantId}`)

onMounted(async () => {
  if (!qrContainer.value) return
  const canvas = document.createElement('canvas')
  await QRCode.toCanvas(canvas, menuUrl.value, { width: 280, margin: 2 })
  qrContainer.value.innerHTML = ''
  qrContainer.value.appendChild(canvas)
})

function downloadQr() {
  const canvas = qrContainer.value?.querySelector('canvas')
  if (!canvas) return
  const link = document.createElement('a')
  link.download = `qr-${props.restaurantName}.png`
  link.href = canvas.toDataURL('image/png')
  link.click()
}

function printQr() {
  const canvas = qrContainer.value?.querySelector('canvas')
  if (!canvas) return
  const win = window.open('', '_blank')
  if (!win) return

  const doc = win.document
  doc.open()
  doc.write('<!doctype html><html><head><meta charset="utf-8"><title>QR</title></head><body></body></html>')
  doc.close()

  const style = doc.createElement('style')
  style.textContent = 'body{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;}h2{margin-bottom:1rem;}img{max-width:300px;}p{color:#666;margin-top:1rem;font-size:0.9rem;}'
  doc.head.appendChild(style)

  doc.title = `QR ${props.restaurantName}`

  const title = doc.createElement('h2')
  title.textContent = `🍽️ ${props.restaurantName}`

  const image = doc.createElement('img')
  image.src = canvas.toDataURL('image/png')
  image.alt = 'QR menu'

  const caption = doc.createElement('p')
  caption.textContent = 'Escanea el QR para ver la carta'

  doc.body.appendChild(title)
  doc.body.appendChild(image)
  doc.body.appendChild(caption)

  win.document.close()
  win.onload = () => { win.print(); win.close() }
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(menuUrl.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch { /* ignore */ }
}
</script>

<style scoped>
.qr-modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.qr-modal {
  background: white; border-radius: 16px; width: 100%; max-width: 420px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.qr-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9;
}
.qr-header h2 { margin: 0; font-size: 1.2rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.qr-body { padding: 1.5rem; text-align: center; }
.qr-canvas { display: flex; justify-content: center; margin-bottom: 1rem; }
.qr-url { color: #64748b; font-size: 0.85rem; word-break: break-all; margin: 0 0 1.5rem; }
.qr-actions { display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; }
.qr-actions button {
  padding: 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px;
  background: white; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.15s;
}
.qr-actions button:hover { background: #f8fafc; border-color: #cbd5e1; }
.btn-download { border-color: #667eea; color: #667eea; }
.btn-print { border-color: #f59e0b; color: #f59e0b; }
.btn-copy { border-color: #22c55e; color: #22c55e; }
.copied-msg { color: #16a34a; font-size: 0.9rem; margin-top: 0.75rem; }
</style>
