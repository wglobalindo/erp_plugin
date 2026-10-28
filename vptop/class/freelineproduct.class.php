<?php
/* Copyright (C) 2026 W Global Indo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/vptop/class/freelineproduct.class.php
 * \ingroup vptop
 * \brief   Turn free text lines (no product) into stock products so ordered goods are tracked in stock.
 *
 * Quotes keep free text lines (goods not bought yet). When a supplier order is validated, each free text line of
 * type "product" gets a product: the product mapped to its name in llx_vptop_freeline_map, or a new product with an
 * automatic ref (VPTOP_FREELINE_PRODUCT_PREFIX + yymm-nnnn) that is then mapped. The same product is set on the
 * matching line of the customer order (extrafield cp of the supplier order line), so reception adds stock and
 * shipment removes it. Names are compared normalized (no HTML, lower case, single spaces). Similar names are
 * grouped by hand on page admin/freeline.php (map several names to one product).
 * Service lines, special lines and lines with a quantity <= 0 are left as free text lines.
 */

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * Free text line -> product
 */
class VptopFreeLineProduct
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string[] */
	public $errors = array();

	/** @var array<string,int> cache name_key => fk_product */
	private $mapCache = array();

	/**
	 * Lines tables scanned for free text lines: table => array(document id field, label)
	 * @var array<string,array{0:string,1:string}>
	 */
	public static $lineTables = array(
		'commande_fournisseurdet' => array('fk_commande', 'SupplierOrder'),
		'commandedet' => array('fk_commande', 'CustomerOrder'),
		'supplier_proposaldet' => array('fk_supplier_proposal', 'SupplierProposal'),
		'propaldet' => array('fk_propal', 'Proposal'),
	);

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Plain text of a line description: no HTML, single spaces, trimmed (case kept)
	 *
	 * @param string $text Description
	 * @return string
	 */
	public static function plainText($text)
	{
		$s = preg_replace('/<br\s*\/?>/i', ' ', (string) $text);
		$s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$s = preg_replace('/\s+/u', ' ', $s);
		return trim($s);
	}

	/**
	 * Normalized name used as map key: plain text, lower case, max 255 chars
	 *
	 * @param string $text Description
	 * @return string
	 */
	public static function normalize($text)
	{
		return mb_substr(mb_strtolower(self::plainText($text), 'UTF-8'), 0, 255, 'UTF-8');
	}

	/**
	 * Loose key to suggest similar names: only letters and digits
	 *
	 * @param string $key Normalized name
	 * @return string
	 */
	public static function looseKey($key)
	{
		return preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $key);
	}

	/**
	 * Product mapped to a normalized name
	 *
	 * @param string $key Normalized name
	 * @return int Product id, 0 if none
	 */
	public function getMappedProduct($key)
	{
		global $conf;

		if (isset($this->mapCache[$key])) {
			return $this->mapCache[$key];
		}
		$sql = "SELECT m.fk_product FROM ".MAIN_DB_PREFIX."vptop_freeline_map as m";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = m.fk_product";
		$sql .= " WHERE m.entity = ".((int) $conf->entity)." AND m.name_key = '".$this->db->escape($key)."'";
		$resql = $this->db->query($sql);
		$id = 0;
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$id = (int) $obj->fk_product;
		}
		$this->mapCache[$key] = $id;
		return $id;
	}

	/**
	 * Map a normalized name to a product (insert or replace)
	 *
	 * @param string	$key		Normalized name
	 * @param string	$label		Name as typed (for display)
	 * @param int		$fk_product	Product id
	 * @param User		$user		User
	 * @return int					>0 if OK, <0 if KO
	 */
	public function setMap($key, $label, $fk_product, $user)
	{
		global $conf;

		if ($key === '' || $fk_product <= 0) {
			return -1;
		}
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."vptop_freeline_map WHERE entity = ".((int) $conf->entity)." AND name_key = '".$this->db->escape($key)."'";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."vptop_freeline_map (entity, name_key, label, fk_product, datec, fk_user)";
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($key)."', '".$this->db->escape(dol_trunc($label, 255, 'right', 'UTF-8', 1))."',";
		$sql .= " ".((int) $fk_product).", '".$this->db->idate(dol_now())."', ".((int) $user->id).")";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->mapCache[$key] = (int) $fk_product;
		return 1;
	}

	/**
	 * Remove a mapping
	 *
	 * @param int $rowid Id of mapping
	 * @return int >0 if OK, <0 if KO
	 */
	public function deleteMap($rowid)
	{
		global $conf;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."vptop_freeline_map WHERE entity = ".((int) $conf->entity)." AND rowid = ".((int) $rowid);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->mapCache = array();
		return 1;
	}

	/**
	 * Next automatic product ref: prefix + yymm + '-' + 4 digits
	 *
	 * @return string
	 */
	public function nextRef()
	{
		$prefix = getDolGlobalString('VPTOP_FREELINE_PRODUCT_PREFIX', 'FL').dol_print_date(dol_now(), '%y%m').'-';
		$sql = "SELECT MAX(CAST(SUBSTRING(ref, ".(dol_strlen($prefix) + 1).") AS UNSIGNED)) as maxnum FROM ".MAIN_DB_PREFIX."product";
		$sql .= " WHERE ref LIKE '".$this->db->escape($this->db->escapeforlike($prefix))."%'";
		$num = 0;
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$num = (int) $obj->maxnum;
		}
		return $prefix.sprintf('%04d', $num + 1);
	}

	/**
	 * Create a stock product for a free text line
	 *
	 * @param string	$desc		Line description
	 * @param int		$fk_unit	Unit of the line
	 * @param User		$user		User
	 * @return int					Product id, <0 if KO
	 */
	public function createProduct($desc, $fk_unit, $user)
	{
		$product = new Product($this->db);
		$product->ref = $this->nextRef();
		$product->label = dol_trunc(self::plainText($desc), 255, 'right', 'UTF-8', 1);
		$product->description = $desc;
		$product->type = Product::TYPE_PRODUCT;
		$product->status = 1;		// on sale
		$product->status_buy = 1;	// on purchase
		$product->price_base_type = 'HT';
		$product->fk_unit = ($fk_unit > 0 ? (int) $fk_unit : null);
		$product->note_private = 'Created by module VPtoP from a free text line';
		$id = $product->create($user);
		if ($id <= 0) {
			$this->error = $product->error;
			$this->errors = array_merge($this->errors, $product->errors);
			return -1;
		}
		return $id;
	}

	/**
	 * Product for a free text line: mapped one, or a new one that is mapped
	 *
	 * @param string	$desc		Line description
	 * @param int		$fk_unit	Unit of the line
	 * @param User		$user		User
	 * @return int					Product id, 0 if the line has no text, <0 if KO
	 */
	public function productForText($desc, $fk_unit, $user)
	{
		$key = self::normalize($desc);
		if ($key === '') {
			return 0;
		}
		$id = $this->getMappedProduct($key);
		if ($id > 0) {
			return $id;
		}
		$id = $this->createProduct($desc, $fk_unit, $user);
		if ($id < 0) {
			return -1;
		}
		if ($this->setMap($key, self::plainText($desc), $id, $user) < 0) {
			return -1;
		}
		return $id;
	}

	/**
	 * Is a line a free text line to convert
	 *
	 * @param object $line Line (object with fk_product, product_type, special_code, qty)
	 * @return bool
	 */
	public static function isConvertible($line)
	{
		return empty($line->fk_product) && (int) $line->product_type == 0 && empty($line->special_code) && (float) $line->qty > 0;
	}

	/**
	 * Set a product on the free text lines of a validated supplier order, and on the matching lines of the customer order
	 *
	 * @param CommandeFournisseur	$order	Supplier order
	 * @param User					$user	User
	 * @return int						Number of lines changed, <0 if KO
	 */
	public function linkSupplierOrder($order, $user)
	{
		if (empty($order->lines) && method_exists($order, 'fetch_lines')) {
			$order->fetch_lines();
		}
		$nb = 0;
		foreach ($order->lines as $line) {
			if (!self::isConvertible($line)) {
				continue;
			}
			$desc = (!empty($line->desc) ? $line->desc : (string) $line->description);
			$fk_product = $this->productForText($desc, (int) $line->fk_unit, $user);
			if ($fk_product < 0) {
				return -1;
			}
			if ($fk_product == 0) {
				continue;
			}
			$sql = "UPDATE ".MAIN_DB_PREFIX."commande_fournisseurdet SET fk_product = ".((int) $fk_product);
			$sql .= " WHERE rowid = ".((int) $line->id)." AND fk_product IS NULL";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$line->fk_product = $fk_product;
			$nb++;

			// Same product on the customer order line this supplier line was made from (extrafield cp = customer order)
			if (!isset($line->array_options['options_cp']) && method_exists($line, 'fetch_optionals')) {
				$line->fetch_optionals();
			}
			$fk_customer_order = (int) (empty($line->array_options['options_cp']) ? 0 : $line->array_options['options_cp']);
			if ($fk_customer_order > 0) {
				$res = $this->linkLinesOfDocument('commandedet', $fk_customer_order, self::normalize($desc), $fk_product);
				if ($res < 0) {
					return -1;
				}
			}
		}
		return $nb;
	}

	/**
	 * Set a product on the free text lines of one document whose normalized text is $key
	 *
	 * @param string	$table			Lines table (without prefix)
	 * @param int		$fk_document	Document id
	 * @param string	$key			Normalized name
	 * @param int		$fk_product		Product id
	 * @return int						Number of lines changed, <0 if KO
	 */
	public function linkLinesOfDocument($table, $fk_document, $key, $fk_product)
	{
		$docfield = self::$lineTables[$table][0];
		$sql = "SELECT rowid, description FROM ".MAIN_DB_PREFIX.$table;
		$sql .= " WHERE ".$docfield." = ".((int) $fk_document)." AND fk_product IS NULL AND product_type = 0 AND special_code = 0 AND qty > 0";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$ids = array();
		while ($obj = $this->db->fetch_object($resql)) {
			if (self::normalize($obj->description) === $key) {
				$ids[] = (int) $obj->rowid;
			}
		}
		if ($ids) {
			$sql = "UPDATE ".MAIN_DB_PREFIX.$table." SET fk_product = ".((int) $fk_product)." WHERE rowid IN (".implode(',', $ids).") AND fk_product IS NULL";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}
		return count($ids);
	}

	/**
	 * Free text names found in the document lines, grouped by normalized name.
	 *
	 * @param bool $onlyUnmapped	Only names without mapping
	 * @return array<string,array{key:string,label:string,loose:string,count:array<string,int>,total:int,fk_product:int}>|int	<0 if KO
	 */
	public function listNames($onlyUnmapped = true)
	{
		global $conf;

		$names = array();
		foreach (self::$lineTables as $table => $info) {
			$sql = "SELECT d.description, COUNT(*) as nb FROM ".MAIN_DB_PREFIX.$table." as d";
			$sql .= " WHERE d.fk_product IS NULL AND d.product_type = 0 AND d.special_code = 0 AND d.qty > 0";
			$sql .= " GROUP BY d.description";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			while ($obj = $this->db->fetch_object($resql)) {
				$key = self::normalize($obj->description);
				if ($key === '') {
					continue;
				}
				if (!isset($names[$key])) {
					$names[$key] = array('key' => $key, 'label' => self::plainText($obj->description), 'loose' => self::looseKey($key), 'count' => array(), 'total' => 0, 'fk_product' => 0);
				}
				$names[$key]['count'][$table] = (isset($names[$key]['count'][$table]) ? $names[$key]['count'][$table] : 0) + (int) $obj->nb;
				$names[$key]['total'] += (int) $obj->nb;
			}
		}
		foreach ($names as $key => $val) {
			$names[$key]['fk_product'] = $this->getMappedProduct($key);
			if ($onlyUnmapped && $names[$key]['fk_product'] > 0) {
				unset($names[$key]);
			}
		}
		uasort($names, function ($a, $b) {
			return strcmp($a['loose'].' '.$a['key'], $b['loose'].' '.$b['key']);
		});
		return $names;
	}

	/**
	 * Apply the mapping to existing free text lines of the given tables (past documents).
	 * Stock of the past is not changed (no stock movement is made).
	 *
	 * @param string[] $tables Lines tables (keys of self::$lineTables)
	 * @return array<string,int>|int	Number of lines changed by table, <0 if KO
	 */
	public function applyMapToExistingLines($tables)
	{
		$result = array();
		foreach ($tables as $table) {
			if (empty(self::$lineTables[$table])) {
				continue;
			}
			$sql = "SELECT rowid, description FROM ".MAIN_DB_PREFIX.$table;
			$sql .= " WHERE fk_product IS NULL AND product_type = 0 AND special_code = 0 AND qty > 0";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$byproduct = array();
			while ($obj = $this->db->fetch_object($resql)) {
				$fk_product = $this->getMappedProduct(self::normalize($obj->description));
				if ($fk_product > 0) {
					$byproduct[$fk_product][] = (int) $obj->rowid;
				}
			}
			$nb = 0;
			foreach ($byproduct as $fk_product => $ids) {
				foreach (array_chunk($ids, 500) as $chunk) {
					$sql = "UPDATE ".MAIN_DB_PREFIX.$table." SET fk_product = ".((int) $fk_product)." WHERE rowid IN (".implode(',', $chunk).") AND fk_product IS NULL";
					$res = $this->db->query($sql);
					if (!$res) {
						$this->error = $this->db->lasterror();
						return -1;
					}
					$nb += $this->db->affected_rows($res);
				}
			}
			$result[$table] = $nb;
		}
		return $result;
	}
}
