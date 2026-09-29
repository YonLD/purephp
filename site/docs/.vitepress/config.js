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
          { text: 'API', link: '/api/' },
          { text: 'Examples', link: '/guide/examples' },
          {
            text: 'Project',
            items: [
              { text: 'Upgrading', link: '/guide/upgrading' },
              { text: 'Troubleshooting', link: '/guide/troubleshooting' },
              { text: 'Changelog', link: 'https://github.com/YonLD/purephp/blob/main/CHANGELOG.md' },
              { text: 'License', link: 'https://github.com/YonLD/purephp/blob/main/LICENSE' }
            ]
          }
        ]
      }
    },
    zh: {
      label: '简体中文',
      lang: 'zh-CN',
      themeConfig: {
        // The shared themeConfig below carries the English chrome, so every
        // label a reader sees on a zh page is overridden here. The outline
        // title is `outlineTitle`, not `outlineLabel`.
        outlineTitle: '本页目录',
        darkModeSwitchLabel: '外观',
        lightModeSwitchTitle: '切换到浅色模式',
        darkModeSwitchTitle: '切换到深色模式',
        sidebarMenuLabel: '菜单',
        langMenuLabel: '切换语言',
        returnToTopLabel: '回到顶部',
        docFooter: { prev: '上一页', next: '下一页' },
        footer: {
          message: '基于 MIT 许可证发布',
          copyright: '版权所有 © 2024 至今 PurePHP'
        },
        nav: [
          { text: '首页', link: '/zh/' },
          { text: '指南', link: '/zh/guide/' },
          { text: 'API', link: '/zh/api/' },
          { text: '示例', link: '/zh/guide/examples' },
          {
            text: '项目',
            items: [
              { text: '升级', link: '/zh/guide/upgrading' },
              { text: '故障排查', link: '/zh/guide/troubleshooting' },
              { text: '变更日志', link: 'https://github.com/YonLD/purephp/blob/main/CHANGELOG.md' },
              { text: '许可证', link: 'https://github.com/YonLD/purephp/blob/main/LICENSE' }
            ]
          }
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
            { text: '核心概念', link: '/zh/guide/concepts' },
            { text: 'Props 与 Slot', link: '/zh/guide/props' }
          ]
        },
        {
          text: '构建界面',
          items: [
            { text: '组件', link: '/zh/guide/components' },
            { text: '编译渲染', link: '/zh/guide/compiled' },
            { text: '产物与部署', link: '/zh/guide/artifacts' }
          ]
        },
        {
          text: '探索',
          items: [
            { text: '示例', link: '/zh/guide/examples' },
            { text: '事件', link: '/zh/guide/events' },
            { text: 'SVG 与 XML 支持', link: '/zh/guide/svg-xml' },
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
          text: '支持与维护',
          items: [
            { text: '故障排查', link: '/zh/guide/troubleshooting' },
            { text: '升级', link: '/zh/guide/upgrading' }
          ]
        },
        {
          text: 'API 参考',
          items: [
            { text: '概览', link: '/zh/api/' },
            { text: '组件 API', link: '/zh/api/component' },
            { text: '编译 API', link: '/zh/api/compile' },
            { text: 'Tag 类', link: '/zh/api/tag' },
            { text: 'HTML 类', link: '/zh/api/html' },
            { text: 'SVG 类', link: '/zh/api/svg' },
            { text: 'XML 类', link: '/zh/api/xml' },
            { text: 'Raw 类', link: '/zh/api/raw' }
          ]
        }
      ],
      '/zh/api/': [
        {
          text: 'API 参考',
          items: [
            { text: '概览', link: '/zh/api/' },
            { text: '组件 API', link: '/zh/api/component' },
            { text: '编译 API', link: '/zh/api/compile' },
            { text: 'Tag 类', link: '/zh/api/tag' },
            { text: 'HTML 类', link: '/zh/api/html' },
            { text: 'SVG 类', link: '/zh/api/svg' },
            { text: 'XML 类', link: '/zh/api/xml' },
            { text: 'Raw 类', link: '/zh/api/raw' }
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
          text: 'Build with PurePHP',
          items: [
            { text: 'Components', link: '/guide/components' },
            { text: 'Compiled Rendering', link: '/guide/compiled' },
            { text: 'Artifacts & Deployment', link: '/guide/artifacts' }
          ]
        },
        {
          text: 'Explore',
          items: [
            { text: 'Examples', link: '/guide/examples' },
            { text: 'Events', link: '/guide/events' },
            { text: 'SVG and XML Support', link: '/guide/svg-xml' },
            { text: 'Utility Functions', link: '/guide/utils' }
          ]
        },
        {
          text: 'Integrations',
          items: [
            { text: 'HTMX', link: '/guide/htmx' },
            { text: 'TailwindCSS', link: '/guide/tailwindcss' }
          ]
        },
        {
          text: 'Support & Maintenance',
          items: [
            { text: 'Troubleshooting', link: '/guide/troubleshooting' },
            { text: 'Upgrading', link: '/guide/upgrading' }
          ]
        },
        {
          text: 'API Reference',
          items: [
            { text: 'Overview', link: '/api/' },
            { text: 'Component API', link: '/api/component' },
            { text: 'Compile API', link: '/api/compile' },
            { text: 'Tag Class', link: '/api/tag' },
            { text: 'HTML Class', link: '/api/html' },
            { text: 'SVG Class', link: '/api/svg' },
            { text: 'XML Class', link: '/api/xml' },
            { text: 'Raw Class', link: '/api/raw' }
          ]
        }
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Overview', link: '/api/' },
            { text: 'Component API', link: '/api/component' },
            { text: 'Compile API', link: '/api/compile' },
            { text: 'Tag Class', link: '/api/tag' },
            { text: 'HTML Class', link: '/api/html' },
            { text: 'SVG Class', link: '/api/svg' },
            { text: 'XML Class', link: '/api/xml' },
            { text: 'Raw Class', link: '/api/raw' }
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
