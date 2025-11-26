-- 更新sys_news_article表的name字段长度从10改为50
ALTER TABLE sys_news_article MODIFY COLUMN name VARCHAR(50) NOT NULL;
