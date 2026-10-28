-- Copyright (C) 2026 W Global Indo
-- GPL v3 or later

ALTER TABLE llx_vptop_freeline_map ADD UNIQUE INDEX uk_vptop_freeline_map(entity, name_key);
ALTER TABLE llx_vptop_freeline_map ADD INDEX idx_vptop_freeline_map_fk_product(fk_product);
