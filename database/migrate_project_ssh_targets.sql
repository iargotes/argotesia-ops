SET @db_name = DATABASE();

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE projects ADD COLUMN server_ssh_ivan VARCHAR(255) NULL AFTER server_ssh',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'projects'
    AND COLUMN_NAME = 'server_ssh_ivan'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE projects ADD COLUMN server_ssh_oscar VARCHAR(255) NULL AFTER server_ssh_ivan',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'projects'
    AND COLUMN_NAME = 'server_ssh_oscar'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The legacy ArgoDrive target was confirmed as Oscar's account. Other legacy values are
-- intentionally left unassigned because ownership cannot be inferred safely.
UPDATE projects
SET server_ssh_oscar = server_ssh
WHERE project_key = 'argodrive'
  AND server_ssh_oscar IS NULL
  AND server_ssh IS NOT NULL
  AND server_ssh <> '';
