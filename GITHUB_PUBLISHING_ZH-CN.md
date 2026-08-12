# 方块引擎 GitHub 开源发布说明

## 创建仓库时怎么选

- Repository name：`BlockEngine`
- Description：`面向 Minecraft Java 版的开源桌面启动器与游戏工作台`
- Visibility：`Public`
- Add a README file：不要勾选（源码里已有 README）
- Add .gitignore：不要重复添加（源码里已有）
- Choose a license：上传现有源码时选择 `None`，因为根目录已经包含标准 GPLv3 `LICENSE`；如果你准备由 GitHub 先初始化仓库，则选择 `GNU General Public License v3.0`

项目 SPDX 标识：`GPL-3.0-only`。

## 建议 Topics

`minecraft` `minecraft-launcher` `java` `tauri` `vue` `rust` `modrinth` `curseforge` `open-source`

## 必须保留

- 根目录 `LICENSE`
- 根目录 `COPYING.md`
- 根目录 `SOURCE_AND_LICENSE.txt`
- 各子包 `COPYING.md` / `LICENSE`
- `third-party/licenses/`
- README 中 Axolotl Launcher 与 Modrinth App 的上游链接

## 发布二进制版本

在 GitHub Releases 上传安装包时，同时附上该版本对应源码，或确保公开仓库中存在与二进制完全对应的源码版本。建议为版本创建标签，例如 `v1.7.1`。

注意：第三方素材、数据和依赖适用各自许可证，不能统一改写为 GPL。若要宣称仓库所有内容均属于 OSI/FSF 自由许可证，需要先移除 COPYING 中提到的有限许可内容。