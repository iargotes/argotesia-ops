SET @db_name = DATABASE();

SET @duplicate_intakes = (
  SELECT COUNT(*)
  FROM (
    SELECT intake_id
    FROM tickets
    WHERE intake_id IS NOT NULL
    GROUP BY intake_id
    HAVING COUNT(*) > 1
  ) duplicate_groups
);

SET @index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'tickets'
    AND INDEX_NAME = 'uk_tickets_intake_id'
);

SET @sql = IF(
  @index_exists > 0,
  'SELECT ''uk_tickets_intake_id already exists'' AS result',
  IF(
    @duplicate_intakes = 0,
    'CREATE UNIQUE INDEX uk_tickets_intake_id ON tickets (intake_id)',
    'SELECT ''unique index deferred: existing duplicate intake tickets require review'' AS result'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
