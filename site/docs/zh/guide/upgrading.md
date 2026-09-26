---
title: 升级与发布
description: PurePHP 的更新步骤、兼容性检查与部署说明。
---

# 升级与发布

## Tested With（测试环境）

| 范围 | 已声明或已测试环境 | 说明 |
| --- | --- | --- |
| 运行时 | PHP 8.1+ | 项目要求为 `php: >=8.1.0`；请在生产使用的 PHP 版本上验证。 |
| 依赖 | Composer | 脚本和示例 front controller 都应加载 `vendor/autoload.php`。 |
| 文档 | VitePress 1.6.x | 站点使用 VitePress 1 配置格式；本指南不需要升级依赖。 |
| 生产 | OPcache（可选） | 加载生成的 `*.pure.php` 与 `*.plain.php` 时有帮助。 |

这些是兼容性与文档目标，不代表未来每个 PHP 或扩展组合都已测试。

## 升级清单

对于已有 tag 的 release，在应用中调整 Composer 约束并执行：

```bash
composer update yonld/purephp
composer test
```

如果跟随默认分支，请把已验证的 commit 固定在应用中，不要自行编造版本号。部署
该 commit 前：

1. 阅读 [CHANGELOG](https://github.com/YonLD/purephp/blob/main/CHANGELOG.md)
   与下方的[迁移说明](#迁移说明)。
2. 在库 checkout 中运行 `composer quality`。
3. 对组件与 Shape 单元运行 `vendor/bin/pure check <paths>`。
4. 重新生成严格产物与 plain 产物：

   ```bash
   vendor/bin/pure compile <paths>
   vendor/bin/pure compile --plain <paths>
   vendor/bin/pure compile --check --plain <paths>
   ```

5. 验证[示例](/zh/guide/examples)，至少访问严格路由、plain 路由、静态 `/cover`
   路由，并运行 XML CLI writer。
6. 如果应用使用 `Compile::cachePath()`，只有在 release 要求重新生成时才删除由
   PurePHP 管理的缓存文件；显式 API 是 `Compile::clearCache()`。
7. 验证渲染结果与文档声明，然后让源文件和生成产物一起部署。

不要手改 `*.pure.php` 或 `*.plain.php`。它们包含缓存版本或指纹契约，必须用匹配的
编译器重新生成。

## 迁移说明

从早期版本升级到 1.0.0 时，最需要注意的变化如下。

### 组件单元从调用函数派生名称

现在推荐的注册形式是：

```php
register(Card(...), factory: static fn () => div(...));
```

调用函数返回 `component(__FUNCTION__)`；只有模板读取 `children` Slot 时才返回
`component(__FUNCTION__, ...$children)`。这样组件名与单元文件只在一个地方定义。
若旧示例重复写了名称字面量，请迁移；只有明确替换已有注册时才使用
`override: true`。

### 模板构建器需要显式标记

`#[Template]` 函数标记单元旁边的 Shape 构建器。`pure compile --list` 会把它列为
`(template)`，`pure check` 会校验声明的返回类型。它不是另一种页面类型。

### 产物带有兼容性守卫

生成的严格产物包含缓存版本守卫。如果部署的产物由另一代缓存写出，请运行
`pure compile`；不要绕过异常，也不要在不兼容的部署之间复制产物。Plain view
也应使用 `--check --plain` 检查。

### 契约属性属于工具声明

`#[Prop]`、`#[Trusted]` 与 `#[Binds]` 由 `pure check` 读取，其中 `#[Prop]` 与
`#[Trusted]` 还会被开发守卫使用；它们不会清理值，也不会改变渲染。升级组件时要特别
复核 raw Slot 的信任边界，详见
[组件 API](/zh/api/component)。

## 发布流程

一次 release 应从已审查的 commit 准备，并包含：

- `CHANGELOG.md` 中真实 SemVer 标题下的日期条目；
- 与标题及 Composer 包版本一致的 Git tag；
- 通过语法、代码风格、PHPStan 与 PHPUnit 检查；
- 新的 `*.pure.php`，以及使用时的 `*.plain.php`；
- 示例 smoke test，以及缓存版本变化时的缓存重建。

站点目前还不发布 `/vX/` 版本副本，因此从搜索结果进入的页面，可能描述的不是你
实际安装的版本。请把 `CHANGELOG.md` 里的 `## [x.y.z]` 标题与
`composer show yonld/purephp` 输出的版本对照之后再照着操作。

## 回滚

部署失败时，请把应用源文件与生成产物作为一个整体回滚。随后清理 PurePHP 渲染器
缓存，重新运行 `vendor/bin/pure compile --check`，并检查第一个失败的路由。保留与
源文件匹配的已知可用产物，比只恢复其中一半更安全。

需要按现象定位时，请继续阅读[故障排查](/zh/guide/troubleshooting)。项目采用
[MIT 许可证](https://github.com/YonLD/purephp/blob/main/LICENSE)。
