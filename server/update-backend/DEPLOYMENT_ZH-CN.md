# 方块引擎在线更新后端部署说明

## 1. 服务器要求

- 域名：`san2.top`，并已配置 HTTPS。
- PHP 8.0 或更高版本，启用 Session、JSON、OpenSSL。
- `data/` 目录必须允许 PHP 进程写入。
- 更新接口固定为：`https://san2.top/blockengine/latest.php`。

建议目录：

```text
/www/wwwroot/san2.top/
├─ blockengine/                 更新后台
│  ├─ index.php                管理页面
│  ├─ latest.php               客户端读取的接口
│  ├─ config.local.php         本机配置，不公开
│  └─ data/version.txt         当前发布版本
└─ downloads/                  安装包和签名
```

## 2. 上传与设置权限

把整个 `update-backend` 目录上传为网站的 `/blockengine/` 目录。

Linux/宝塔终端示例：

```bash
cd /www/wwwroot/san2.top
chown -R www:www blockengine
find blockengine -type d -exec chmod 750 {} \;
find blockengine -type f -exec chmod 640 {} \;
chmod 770 blockengine/data
```

不同服务器的 PHP 用户可能叫 `www-data`、`nginx` 或 `apache`，需要相应替换。

## 3. 配置后台密码

在服务器终端生成密码哈希，命令中的密码只用于生成哈希：

```bash
php -r "echo password_hash('换成你的强密码', PASSWORD_DEFAULT), PHP_EOL;"
```

复制 `config.example.php` 为 `config.local.php`，只把 `password_hash` 的值替换为刚生成的完整哈希。不要填写明文密码。

也可以不创建该文件，改用 PHP-FPM 环境变量：

```text
BLOCK_ENGINE_UPDATE_PASSWORD_HASH=生成的完整哈希
```

环境变量优先于 `config.local.php`。

## 4. Nginx 保护规则

Apache 会读取随包附带的 `.htaccess`。如果使用 Nginx，请在 `san2.top` 的站点配置中加入：

```nginx
location ~ ^/blockengine/(?:data/|config\.(?:local|example)\.php$) {
    deny all;
    return 403;
}

location = /blockengine/latest.php {
    add_header Cache-Control "no-store, max-age=0" always;
}
```

保存后检查并重新加载 Nginx。使用 Cloudflare/CDN 时，也要为 `/blockengine/latest.php` 设置“绕过缓存”。

## 5. 首次验证

1. 打开 `https://san2.top/blockengine/`，确认可以使用管理密码登录。
2. 尚未发布版本时，打开 `https://san2.top/blockengine/latest.php` 应返回 404 JSON。
3. 确认浏览器无法直接读取 `config.local.php`。

## 6. 发布新版本

发布顺序不能颠倒：先上传安装包和签名，最后发布 TXT。

假设新版本为 `1.7.2`：

1. 构建并签名新版客户端。
2. 上传以下两个文件到同一个目录：

   ```text
   https://san2.top/downloads/BlockEngine_1.7.2_x64-setup.exe
   https://san2.top/downloads/BlockEngine_1.7.2_x64-setup.exe.sig
   ```

3. 分别访问两个地址，确认都能下载，且服务器没有把 `.sig` 当作网页返回。
4. 新建 UTF-8 编码的 `version.txt`，必须只有两行：

   ```text
   1.7.2
   https://san2.top/downloads/BlockEngine_1.7.2_x64-setup.exe
   ```

5. 打开 `https://san2.top/blockengine/`，登录后上传该 TXT。
6. 打开 `https://san2.top/blockengine/latest.php`，确认 JSON 中的版本、URL 和签名都正确。

客户端开启自动更新时会在启动后检查并下载，退出或确认重启时安装；关闭自动更新时只提示，由用户点击下载。

## 7. 回滚

客户端不会自动安装比当前版本更旧的版本。需要回滚代码时，应使用更高的修复版本号重新构建，例如把有问题的 `1.8.1` 代码回退后发布为 `1.8.2`。

紧急暂停更新时，可以暂时移走 `data/version.txt`，此时接口返回 404，客户端不会收到新版本。不要删除已经安装在用户电脑中的签名公钥。

## 8. 更新私钥安全

- 私钥文件 `block-engine.key` 只能保存在开发电脑和离线备份中。
- 不得上传服务器、网盘公开链接、GitHub 或客户端源码包。
- 安装包生成签名后不能再修改，否则客户端会拒绝安装。
- 私钥丢失后，已发布客户端无法验证使用新密钥签出的更新。
