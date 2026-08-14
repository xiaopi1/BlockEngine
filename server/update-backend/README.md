# 方块引擎官方更新服务

把本目录部署到 `https://san2.top/blockengine/`，要求 PHP 8.0+。完整步骤见 `DEPLOYMENT_ZH-CN.md`。

1. 用 PHP 的 `password_hash()` 生成后台密码哈希。可以设置环境变量 `BLOCK_ENGINE_UPDATE_PASSWORD_HASH`，也可以复制 `config.example.php` 为 `config.local.php` 并填入哈希。
2. 每次正式构建会在安装包旁生成 `.sig` 文件。先把安装包和同名 `.sig` 上传到 `san2.top`。
3. 最后打开 `index.php`，登录后上传两行 `version.txt`：

   ```text
   1.7.2
   https://san2.top/downloads/BlockEngine_1.7.2_x64-setup.exe
   ```

4. 启动器读取 `latest.php`。自动更新开启时会自动下载签名安装包；关闭时只显示更新提示。

更新私钥位于本机 `E:\codex\private\BlockEngine-Update-Keys\block-engine.key`。不要上传、不要放进网站目录、不要提交到公开仓库；丢失后旧版本将无法验证新更新。
