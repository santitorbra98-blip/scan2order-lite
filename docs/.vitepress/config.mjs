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
      {
        text: 'Rubrica',
        items: [
          { text: 'DEW — Entorno Cliente', link: '/dew' },
          { text: 'DSW — Entorno Servidor', link: '/dsw' },
          { text: 'DPL — Despliegue', link: '/dpl' },
          { text: 'DOR — Diseño de Interfaces', link: '/dor' }
        ]
      },
      { text: 'Defensa', link: '/defensa' }
    ],
    sidebar: [
      {
        text: 'Arquitectura',
        items: [
          { text: 'Arquitectura del sistema', link: '/arquitectura' }
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
        text: 'Defensa',
        items: [
          { text: 'Guion por asignatura', link: '/defensa' }
        ]
      }
    ],
    search: {
      provider: 'local'
    }
  }
})
