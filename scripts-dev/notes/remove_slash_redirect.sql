#remove leading slash from redirect table (13 entries)

UPDATE `redirect`
SET source = TRIM(LEADING '/' FROM `source`);