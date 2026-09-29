---
title: 升级
description: PurePHP 的环境要求、升级步骤与回滚说明。
---

# 升级

## 环境要求

| 范围 | 要求 | 说明 |
| --- | --- | --- |
| 运行时 | PHP 8.1+ | 请在生产使用的 PHP 版本上验证。 |
| 依赖 | Composer | 脚本和 front controller 都应加载 `vendor/autoload.php`。 |
| 生产 | OPcache（可选） | 加载生成的 `*.pure.php` 与 `*.plain.php` 时有帮助。 |

## 升级清单

对于已有 tag 的 release，在应用中调整 Composer 约束并执行：

```bash
composer update yonld/purephp
```

如果跟随默认分支，请把已验证的 commit 固定在应用中，不要自行编造版本号。部署
该 commit 前：

1. 阅读 [CHANGELOG](https://github.com/YonLD/purephp/blob/main/CHANGELOG.md)，
   了解本版改了什么、需要做什么。
2. 对组件与 Shape 单元运行 `vendor/bin/pure check <paths>`。
3. 重新生成严格产物与 plain 产物：

   ```bash
   vendor/bin/pure compile <paths>
   vendor/bin/pure compile --plain <paths>
   vendor/bin/pure compile --check --plain <paths>
   ```

4. 如果应用使用 `Compile::cachePath()`，只有在 release 要求重新生成时才删除由
   PurePHP 管理的缓存文件；显式 API 是 `Compile::clearCache()`。
5. 验证渲染结果与文档声明，然后让源文件和生成产物一起部署。

不要手改 `*.pure.php` 或 `*.plain.php`。它们包含缓存版本或指纹契约，必须用匹配的
编译器重新生成。

## 回滚

部署失败时，请把应用源文件与生成产物作为一个整体回滚。随后清理 PurePHP 渲染器
缓存，重新运行 `vendor/bin/pure compile --check`，并检查第一个失败的路由。保留与
源文件匹配的已知可用产物，比只恢复其中一半更安全。

需要按现象定位时，请继续阅读[故障排查](/zh/guide/troubleshooting)。
