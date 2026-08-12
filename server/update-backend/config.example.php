<?php
declare(strict_types=1);

// 复制为 config.local.php，然后把下面内容替换为 password_hash() 生成的哈希。
// 不要在这里填写明文密码，也不要把 config.local.php 上传到公开仓库。
return [
    'password_hash' => '$2y$12$replace_this_with_your_password_hash',
];
