<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => isset($_SERVER['HTTPS']),
]);

$localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}

$passwordHash = getenv('BLOCK_ENGINE_UPDATE_PASSWORD_HASH') ?: '';
if ($passwordHash === '') {
    $passwordHash = (string) ($localConfig['password_hash'] ?? '');
}
$dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$versionFile = $dataDirectory . DIRECTORY_SEPARATOR . 'version.txt';
$message = '';
$error = '';

if (!is_dir($dataDirectory)) {
    mkdir($dataDirectory, 0750, true);
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ./');
    exit;
}

if (isset($_POST['password']) && !isset($_SESSION['authenticated'])) {
    if ($passwordHash !== '' && password_verify((string) $_POST['password'], $passwordHash)) {
        $_SESSION['authenticated'] = true;
        session_regenerate_id(true);
    } else {
        $error = '密码错误，或服务器尚未配置管理密码。';
    }
}

if (isset($_SESSION['authenticated']) && isset($_FILES['version_file'])) {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = '页面已过期，请刷新后重试。';
    } elseif ($_FILES['version_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'TXT 上传失败。';
    } elseif ($_FILES['version_file']['size'] > 4096) {
        $error = 'TXT 文件不能超过 4 KB。';
    } else {
        $contents = file_get_contents($_FILES['version_file']['tmp_name']);
        $lines = preg_split('/\R/u', trim((string) $contents));
        $version = trim((string) ($lines[0] ?? ''));
        $url = trim((string) ($lines[1] ?? ''));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedHost = $host === 'san2.top' || str_ends_with($host, '.san2.top');

        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            $error = '第一行不是有效版本号，例如 1.8.1。';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            $error = '第二行必须是 HTTPS 下载地址。';
        } elseif (!$allowedHost) {
            $error = '下载地址必须位于 san2.top 或其子域名。';
        } else {
            $temporary = $versionFile . '.tmp';
            file_put_contents($temporary, $version . PHP_EOL . $url . PHP_EOL, LOCK_EX);
            rename($temporary, $versionFile);
            $message = '版本 ' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . ' 已发布。';
        }
    }
}

$current = is_file($versionFile) ? file($versionFile, FILE_IGNORE_NEW_LINES) : [];
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>方块引擎更新后台</title>
    <style>
        :root { color-scheme: dark; font-family: "Segoe UI", system-ui, sans-serif; background:#0e1012; color:#f5f3ef; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; display:grid; place-items:center; background:radial-gradient(circle at 82% 12%,#4a260f 0,transparent 34rem),#0e1012; }
        main { width:min(620px,calc(100vw - 32px)); padding:32px; border:1px solid #40362d; border-radius:14px; background:#17191ccc; box-shadow:0 24px 80px #0009; backdrop-filter:blur(18px); }
        header { display:flex; align-items:center; gap:14px; border-bottom:1px solid #34302b; padding-bottom:22px; margin-bottom:24px; }
        .mark { width:46px; height:46px; display:grid; place-items:center; border-radius:10px; background:#ff8500; color:#111; font-weight:900; font-size:23px; }
        h1 { margin:0; font-size:22px; } small,p { color:#aaa39a; } label { display:block; margin:16px 0 8px; font-weight:700; }
        input[type=password],input[type=file] { width:100%; padding:12px; border:1px solid #4a443d; border-radius:9px; background:#101214; color:#fff; }
        button { margin-top:18px; border:0; border-radius:9px; padding:11px 18px; background:#ff8500; color:#17100a; font-weight:800; cursor:pointer; }
        button.secondary { margin-left:8px; background:#2a2d31; color:#ddd; }
        .notice { margin:16px 0; padding:12px; border-left:3px solid #ff8500; background:#ff850014; }
        .error { border-color:#ef5555; background:#ef555514; color:#ffaaaa; }
        code { display:block; white-space:pre-wrap; padding:14px; border-radius:9px; background:#0d0f11; color:#d6d0c8; line-height:1.7; }
    </style>
</head>
<body>
<main>
    <header><div class="mark">B</div><div><h1>方块引擎更新后台</h1><small>BLOCK ENGINE RELEASE CHANNEL</small></div></header>
    <?php if ($error !== ''): ?><div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($message !== ''): ?><div class="notice"><?= $message ?></div><?php endif; ?>

    <?php if (empty($_SESSION['authenticated'])): ?>
        <form method="post"><label for="password">管理密码</label><input id="password" name="password" type="password" required autofocus><button type="submit">进入后台</button></form>
    <?php else: ?>
        <p>上传两行 TXT：第一行版本号，第二行安装包 HTTPS 地址。安装包旁边必须存在同名 <b>.sig</b> 签名文件。</p>
        <code><?= htmlspecialchars(($current[0] ?? '1.8.1') . "\n" . ($current[1] ?? 'https://san2.top/BlockEngine_1.8.1_x64-setup.exe'), ENT_QUOTES, 'UTF-8') ?></code>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <label for="version_file">version.txt</label><input id="version_file" name="version_file" type="file" accept=".txt,text/plain" required>
            <button type="submit">发布版本</button><button class="secondary" type="submit" name="logout" value="1">退出</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
