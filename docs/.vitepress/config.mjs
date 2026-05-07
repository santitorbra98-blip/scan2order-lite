import { defineConfig } from 'vitepress'

const repoName = process.env.GITHUB_REPOSITORY?.split('/')[1] || 'scan2order-lite'
const docsBase = process.env.DOCS_BASE || `/${repoName}/`

export default defineConfig({
  title: 'Scan2Order Lite',
  description: 'Documentacion oficial de arquitectura, despliegue y operacion',
  lang: 'es-ES',
  base: docsBase,
  cleanUrls: true,
  lastUpdated: true,
  themeConfig: {
    nav: [
      { text: 'Inicio', link: '/' },
      { text: 'Paso a Paso', link: '/paso-a-paso-completo' },
      { text: 'Guia Rapida', link: '/guia-rapida' },
      { text: 'Produccion', link: '/produccion-checklist' },
      { text: 'Render', link: '/deploy-render' },
      { text: 'Smoke Tests', link: '/smoke-tests' },
      {
        text: 'Rubrica',
        items: [
          { text: 'DEW — Entorno Cliente', link: '/dew' },
          { text: 'DSW — Entorno Servidor', link: '/dsw' },
          { text: 'DPL — Despliegue', link: '/dpl' },
          { text: 'DOR — Diseño de Interfaces', link: '/dor' },
          { text: 'Guia para profesor', link: '/guia-profesor' }
        ]
      }
    ],
    sidebar: [
      {
        text: 'Proyecto',
        items: [
          { text: 'Paso a paso completo', link: '/paso-a-paso-completo' },
          { text: 'Guia Rapida', link: '/guia-rapida' },
          { text: 'Arquitectura', link: '/arquitectura' },
          { text: 'API Auth', link: '/api-auth' }
        ]
      },
      {
        text: 'Operaciones',
        items: [
          { text: 'Checklist de Produccion', link: '/produccion-checklist' },
          { text: 'Despliegue en Render', link: '/deploy-render' },
          { text: 'Smoke Tests Post Deploy', link: '/smoke-tests' }
        ]
      },
      {
        text: 'Rubrica del Proyecto',
        items: [
          { text: 'DEW — Entorno Cliente', link: '/dew' },
          { text: 'DSW — Entorno Servidor', link: '/dsw' },
          { text: 'DPL — Despliegue', link: '/dpl' },
          { text: 'DOR — Diseño de Interfaces', link: '/dor' }
        ]
      },
      {
        text: 'Evaluacion',
        items: [
          { text: 'Guia para profesor', link: '/guia-profesor' }
        ]
      }
    ],
    search: {
      provider: 'local'
    }
  }
})
