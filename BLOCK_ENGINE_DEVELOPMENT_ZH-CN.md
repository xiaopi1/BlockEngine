# 方块引擎客户端源码交接说明

## 当前状态

- 产品名称：方块引擎 / Block Engine
- 当前版本：`1.7.1`
- Windows 主程序：Tauri 2 + Rust
- 前端：Vue 3 + TypeScript
- 安装器：Rust WebView 窗口 + 自定义 NSIS
- 更新：Tauri Updater，官方地址 `https://san2.top/blockengine/latest.php`
- 许可证：GPL-3.0-only 衍生作品，必须保留 `COPYING.md`、`SOURCE_AND_LICENSE.txt` 及上游署名。



## 主要目录

```text
apps/app-frontend/        Vue 客户端界面
apps/app/                 Tauri Windows 应用、图标、NSIS 和发布脚本
apps/installer-ui/        940×620 窗口式安装器
packages/app-lib/         启动、账户、实例、下载等 Rust 核心功能
packages/                 其余共享模块
server/update-backend/    san2.top 在线更新后台
scripts/block-engine/     方块引擎版本工具
```

主页入口是 `apps/app-frontend/src/pages/Index.vue`。Minecraft Glass 世界工作台位于 `apps/app-frontend/src/components/home/HomeMinimal.vue`，日历、最近项目、固定世界、固定服务器和固定实例由 `HomeDashboard.vue` 及同目录组件提供。

## 安装依赖

新电脑需要：

- Node.js 24.15 或更高版本
- pnpm 10.33.2
- Rust 1.90 MSVC 工具链
- Visual Studio 2022 Build Tools（C++ 桌面生成工具）
- Windows 10/11 SDK
- Java 运行环境及 Gradle（Rust 核心中的 Java 辅助库需要）

在源码根目录安装前端依赖：

```powershell
corepack enable
pnpm install --frozen-lockfile
```

## 修改版本号

发布新版本前执行：

```powershell
powershell -ExecutionPolicy Bypass -File scripts\block-engine\set-version.ps1 -Version 1.8.1
```

脚本会同步修改：

- `apps/app-frontend/package.json`
- `apps/app/Cargo.toml`
- `packages/app-lib/Cargo.toml`

不要修改 `protocol_version.rs` 中的 Minecraft 版本号映射。

## 检查与测试

```powershell
cd apps\app-frontend
node node_modules\vue-tsc\bin\vue-tsc.js --noEmit
node --experimental-strip-types --test src\components\home\home-dashboard.test.ts
```

Rust 检查：

```powershell
cargo check --package theseus_gui --features updater
cargo test --package axolotl-installer-ui
```

## 正式 Windows 构建

更新私钥必须保存在源码目录之外。默认构建脚本会在当前工作区的 `outputs/BlockEngine-Update-Keys/block-engine.key` 寻找，也可自行指定：

```powershell
$env:BLOCK_ENGINE_SIGNING_KEY_PATH = 'D:\private\block-engine.key'
apps\app\build-windows.cmd
```

构建结果位于：

```text
target-original-ui/release/BlockEngine.exe
target-original-ui/release/bundle/nsis/方块引擎_<版本>_x64-setup.exe
target-original-ui/release/bundle/nsis/方块引擎_<版本>_x64-setup.exe.sig
target-original-ui/release/bundle/nsis/方块引擎_<版本>_x64-setup.nsis.zip
target-original-ui/release/bundle/nsis/方块引擎_<版本>_x64-setup.nsis.zip.sig
```

构建脚本带 `--ci`，无密码私钥不会再出现等待输入导致的假卡死。

## 发布在线更新

完整服务器步骤见 `server/update-backend/DEPLOYMENT_ZH-CN.md`。简要顺序：

1. 先上传新版安装包。
2. 再上传与安装包同名的 `.sig`。
3. 验证两个 HTTPS 地址都能下载。
4. 最后在更新后台上传两行 `version.txt`。

严禁把更新私钥放入客户端、服务器或公开源码包。私钥遗失后，现有客户端无法验证新密钥签出的更新。
