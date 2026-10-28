-- Copyright (C) 2026 W Global Indo
-- GPL v3 or later
--
-- Free text line name -> product. Used to turn free text lines of supplier orders into stock products
-- (see class/freelineproduct.class.php). name_key is the normalized text (lower case, no HTML, single spaces).

CREATE TABLE llx_vptop_freeline_map(
	rowid		integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity		integer DEFAULT 1 NOT NULL,
	name_key	varchar(255) NOT NULL,
	label		varchar(255),
	fk_product	integer NOT NULL,
	datec		datetime,
	fk_user		integer,
	tms			timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
