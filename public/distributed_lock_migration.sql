-- 分布式锁字段迁移SQL脚本
-- 将驼峰命名改为下划线命名
-- lockKey -> lock_key
-- lockId -> lock_id

-- 1. 创建备份表
CREATE TABLE distributed_locks_backup_20241208_031500 LIKE distributed_locks;

-- 2. 备份数据
INSERT INTO distributed_locks_backup_20241208_031500 SELECT * FROM distributed_locks;

-- 3. 检查并重命名字段
-- 如果目标字段已存在，先删除
-- ALTER TABLE distributed_locks DROP COLUMN IF EXISTS lock_key;
-- ALTER TABLE distributed_locks DROP COLUMN IF EXISTS lock_id;

-- 重命名 lockKey -> lock_key
-- ALTER TABLE distributed_locks CHANGE COLUMN lockKey lock_key VARCHAR(255) NOT NULL;

-- 重命名 lockId -> lock_id
-- ALTER TABLE distributed_locks CHANGE COLUMN lockId lock_id VARCHAR(255) NOT NULL;

-- 验证脚本
SELECT
    'Migration Verification' as status,
    COUNT(*) as total_records,
    (SELECT COUNT(*) FROM distributed_locks_backup_20241208_031500) as backup_records
FROM distributed_locks;

-- 显示表结构
SHOW COLUMNS FROM distributed_locks;
