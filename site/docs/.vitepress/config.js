export default {
  title: 'PurePHP',
  description: 'A PHP Template Engine inspired by ReactJS',
  base: '/purephp/',
  defaultLocale: 'en',
  locales: {
    root: {
      label: 'English',
      lang: 'en-US',
      themeConfig: {
        nav: [
          { text: 'Home', link: '/' },
          { text: 'Guide', link: '/guide/' },
          { text: 'API', link: '/api/' }
        ]
      }
    },
    zh: {
      label: '简体中文',
      lang: 'zh-CN',
      themeConfig: {
        nav: [
          { text: '首页', link: '/zh/' },
          { text: '指南', link: '/zh/guide/' },
          { text: 'API', link: '/zh/api/' }
        ]
      }
    }
  },
  themeConfig: {
    sidebar: {
      '/zh/guide/': [
        {
          text: '介绍',
          items: [
            { text: '什么是 PurePHP?', link: '/zh/guide/' },
            { text: '快速开始', link: '/zh/guide/getting-started' }
          ]
        },
        {
          text: '基础',
          items: [
            { text: '基本用法', link: '/zh/guide/basic-usage' },
            { text: '基本概念', link: '/zh/guide/concepts' },
            { text: 'Props 与 Slot', link: '/zh/guide/props' }
          ]
        },
        {
          text: '核心',
          items: [
            { text: '组件', link: '/zh/guide/components' },
            { text: '编译渲染', link: '/zh/guide/compiled' },
            { text: '产物与部署', link: '/zh/guide/artifacts' }
          ]
        },
        {
          text: '进阶',
          items: [
            { text: '事件', link: '/zh/guide/events' },
            { text: 'SVG 和 XML 支持', link: '/zh/guide/svg-xml' },
            { text: '工具函数', link: '/zh/guide/utils' }
          ]
        },
        {
          text: '集成',
          items: [
            { text: 'HTMX', link: '/zh/guide/htmx' },
            { text: 'TailwindCSS', link: '/zh/guide/tailwindcss' }
          ]
        },
        {
          text: 'API 参考',
          items: [
            { text: '核心类', link: '/zh/api/' }
          ]
        }
      ],
      '/zh/api/': [
        {
          text: 'API 参考',
          items: [
            { text: '概览', link: '/zh/api/' },
            { text: 'Tag 类', link: '/zh/api/tag' },
            { text: 'HTML 类', link: '/zh/api/html' },
            { text: 'SVG 类', link: '/zh/api/svg' },
            { text: 'XML 类', link: '/zh/api/xml' },
            { text: 'Raw 类', link: '/zh/api/raw' },
            { text: 'Compile API', link: '/zh/api/compile' }
          ]
        }
      ],

      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'What is PurePHP?', link: '/guide/' },
            { text: 'Quick Start', link: '/guide/getting-started' }
          ]
        },
        {
          text: 'Basics',
          items: [
            { text: 'Basic Usage', link: '/guide/basic-usage' },
            { text: 'Core Concepts', link: '/guide/concepts' },
            { text: 'Props and Slots', link: '/guide/props' }
          ]
        },
        {
          text: 'Core',
          items: [
            { text: 'Components', link: '/guide/components' },
            { text: 'Compiled Rendering', link: '/guide/compiled' },
            { text: 'Artifacts & Deployment', link: '/guide/artifacts' }
          ]
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Events', link: '/guide/events' },
            { text: 'SVG and XML Support', link: '/guide/svg-xml' },
            { text: 'Utility Functions', link: '/guide/utils' }
          ]
        },
        {
          text: 'Integration',
          items: [
            { text: 'HTMX', link: '/guide/htmx' },
            { text: 'TailwindCSS', link: '/guide/tailwindcss' }
          ]
        },
        {
          text: 'API Reference',
          items: [
            { text: 'Core Classes', link: '/api/' }
          ]
        }
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Overview', link: '/api/' },
            { text: 'Tag Class', link: '/api/tag' },
            { text: 'HTML Class', link: '/api/html' },
            { text: 'SVG Class', link: '/api/svg' },
            { text: 'XML Class', link: '/api/xml' },
            { text: 'Raw Class', link: '/api/raw' },
            { text: 'Compiled Rendering', link: '/api/compile' }
          ]
        }
      ],

    },
    footer: {
      message: 'Released under the MIT License',
      copyright: 'Copyright © 2024-present PurePHP'
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/YonLD/purephp' }
    ],
    search: {
      provider: 'local'
    },
    langMenuLabel: 'Change language',
    returnToTopLabel: 'Back to top'
  }
}
