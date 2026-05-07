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
      { text: 'Arquitectura', link: '/arquitectura' },
      { text: 'Rúbrica y Defensa', link: '/defensa' }
    ],
    sidebar: [
      {
        text: 'Arquitectura',
        items: [
          { text: 'Arquitectura del sistema', link: '/arquitectura' }
        ]
      },
      {
        text: 'Rúbrica y Defensa',
        items: [
          { text: 'Por asignatura (DSW/DEW/DPL/DOR/SSG)', link: '/defensa' }
        ]
      }
    ],
    search: {
      provider: 'local'
    }
  }
})
