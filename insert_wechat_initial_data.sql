-- 插入微信公众账号初始数据
INSERT IGNORE INTO `wechat_public_account` (
    `id`,
    `name`,
    `description`,
    `avatar_url`,
    `app_id`,
    `app_secret`,
    `created_at`,
    `updated_at`,
    `is_active`,
    `token`,
    `encoding_aeskey`
) VALUES (
    'wx_primary_account_001',
    '官方主账号',
    '官方网站主要微信公众号，用于内容同步和用户互动',
    NULL,
    'wx9248416064fab130',
    '60401298c80bcd3cfd8745f117e01b14',
    NOW(),
    NOW(),
    1,
    'official_token_2024',
    NULL
);

-- 备用测试账号（如果需要）
INSERT IGNORE INTO `wechat_public_account` (
    `id`,
    `name`,
    `description`,
    `avatar_url`,
    `app_id`,
    `app_secret`,
    `created_at`,
    `updated_at`,
    `is_active`,
    `token`,
    `encoding_aeskey`
) VALUES (
    'test_account_001',
    '测试公众号',
    '这是一个用于测试的微信公众号',
    NULL,
    'test_app_id_001',
    'test_app_secret_001',
    '2025-12-04 06:18:29',
    '2025-12-04 06:18:29',
    1,
    NULL,
    NULL
);
