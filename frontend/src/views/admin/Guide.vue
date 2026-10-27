<template>
  <div class="guide-page">
    <header class="guide-header">
      <h1>Guía rápida de uso</h1>
      <p>Todo lo básico para tener tu local visible al público, explicado fácil y sin tecnicismos.</p>
    </header>

    <section class="guide-card">
      <h2>Qué puedes hacer aquí</h2>
      <ul>
        <li>Crear tu local y dejarlo activo.</li>
        <li>Montar tu carta por secciones (entrantes, bebidas, postres...).</li>
        <li>Añadir productos con precio, descripción y foto.</li>
        <li>Marcar alérgenos cuando haga falta.</li>
        <li>Generar el QR para compartir la carta con tus clientes.</li>
      </ul>
    </section>

    <section class="guide-card">
      <h2>Paso a paso para publicar tu local</h2>
      <ol>
        <li>Ve a <strong>Restaurantes</strong> y pulsa <strong>Crear restaurante</strong>.</li>
        <li>Rellena nombre, dirección y teléfono.</li>
        <li>Marca el local como <strong>Activo</strong>.</li>
        <li>Decide si quieres horarios ahora o dejarlo sin horarios.</li>
        <li>Entra en <strong>Productos</strong> y crea una carta.</li>
        <li>Dentro de la carta, crea secciones.</li>
        <li>Añade productos y revisa precios.</li>
        <li>Vuelve a <strong>Restaurantes</strong> y pulsa <strong>QR</strong>.</li>
        <li>Comparte o imprime el QR: ese será el acceso público a tu carta.</li>
      </ol>
    </section>

    <section class="guide-card">
      <h2>Checklist antes de abrir</h2>
      <ul>
        <li>Local en estado <strong>Activo</strong>.</li>
        <li>Hay al menos una carta con productos activos.</li>
        <li>Precios revisados.</li>
        <li>El QR abre la carta correcta.</li>
      </ul>
    </section>

    <section class="guide-card">
      <h2>Cambios rápidos del día a día</h2>
      <ul>
        <li>Si se acaba un plato: desactiva ese producto.</li>
        <li>Si vuelve a estar disponible: actívalo de nuevo.</li>
        <li>Si cambias horarios: edita el restaurante.</li>
        <li>Si no quieres mostrar horarios: quítalos al editar.</li>
      </ul>
    </section>
  </div>
</template>

<script setup>
</script>

<style scoped>
.guide-page {
  max-width: 1000px;
  margin: 0 auto;
  display: grid;
  gap: 1rem;
}

.guide-header {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.guide-header h1 {
  margin: 0 0 0.5rem;
  color: #1e293b;
}

.guide-header p {
  margin: 0;
  color: #475569;
}

.guide-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.guide-card h2 {
  margin: 0 0 0.75rem;
  color: #1e293b;
  font-size: 1.2rem;
}

.guide-card ul,
.guide-card ol {
  margin: 0;
  padding-left: 1.2rem;
  color: #334155;
  line-height: 1.6;
}

@media (max-width: 768px) {
  .guide-header,
  .guide-card {
    padding: 1rem;
  }
}
</style>
