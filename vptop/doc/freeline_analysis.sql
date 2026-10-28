-- Free text lines analysis (READ ONLY). Run on the production database (Dolibarr 13 or 24, MariaDB 10.0.5+ / MySQL 8+).
-- Lists the free text names (lines of type product without product) with their count by document type, sorted so
-- that similar names are next to each other ("loose_key" = only letters and digits, lower case).
-- After the upgrade, the same list is on page VPtoP setup > "Free text lines -> products", where names are linked to products.
-- Note: HTML tags are only removed approximately here (the setup page removes them exactly).

SELECT loose_key, name_key, SUM(supplier_order) AS supplier_order, SUM(customer_order) AS customer_order,
	SUM(supplier_proposal) AS supplier_proposal, SUM(proposal) AS proposal, SUM(n) AS total
FROM (
	SELECT n, supplier_order, customer_order, supplier_proposal, proposal,
		LOWER(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(description, '<[^>]*>', ' '), '[[:space:]]+', ' '))) AS name_key,
		REGEXP_REPLACE(LOWER(REGEXP_REPLACE(description, '<[^>]*>', ' ')), '[^[:alnum:]]+', '') AS loose_key
	FROM (
		SELECT description, COUNT(*) AS n, COUNT(*) AS supplier_order, 0 AS customer_order, 0 AS supplier_proposal, 0 AS proposal
			FROM llx_commande_fournisseurdet WHERE fk_product IS NULL AND product_type = 0 AND special_code = 0 AND qty > 0 GROUP BY description
		UNION ALL
		SELECT description, COUNT(*), 0, COUNT(*), 0, 0
			FROM llx_commandedet WHERE fk_product IS NULL AND product_type = 0 AND special_code = 0 AND qty > 0 GROUP BY description
		UNION ALL
		SELECT description, COUNT(*), 0, 0, COUNT(*), 0
			FROM llx_supplier_proposaldet WHERE fk_product IS NULL AND product_type = 0 AND special_code = 0 AND qty > 0 GROUP BY description
		UNION ALL
		SELECT description, COUNT(*), 0, 0, 0, COUNT(*)
			FROM llx_propaldet WHERE fk_product IS NULL AND product_type = 0 AND special_code = 0 AND qty > 0 GROUP BY description
	) AS lines_by_text
) AS lines_by_key
WHERE name_key <> ''
GROUP BY loose_key, name_key
ORDER BY loose_key, name_key;

-- Summary: number of distinct names, and number of "similar groups" (same loose_key, several spellings)
-- SELECT COUNT(DISTINCT name_key), COUNT(DISTINCT loose_key) FROM ( ...same subquery as above... ) t;
