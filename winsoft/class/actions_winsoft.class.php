<?php
/* Copyright (C) 2026 W Global Indo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/winsoft/class/actions_winsoft.class.php
 * \ingroup winsoft
 * \brief   Hooks of module Winsoft
 *
 * Third party "Consumption" tab (societe/consumption.php, context 'thirdpartyconsumption').
 * Replaces the direct core edit made on Dolibarr 13:
 *  - column + search filter "Ref. customer" / "Ref. supplier" for orders, invoices, supplier orders and supplier invoices
 *  - columns "Total HT (currency)" and "Unit price (currency)" when the third party uses a foreign currency
 * Columns are printed after the standard columns (hooks cannot insert them in the middle of the table).
 */
class ActionsWinsoft
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string[] */
	public $errors = array();

	/** @var array<string,mixed> */
	public $results = array();

	/** @var string */
	public $resprints = '';

	/**
	 * Document types where the customer/supplier reference is shown: type_element => SQL field
	 * @var array<string,string>
	 */
	private static $docRefFields = array(
		'invoice' => 'f.ref_client',
		'order' => 'c.ref_client',
		'supplier_invoice' => 'f.ref_supplier',
		'supplier_order' => 'c.ref_supplier',
	);

	/**
	 * Document types where line table "d" is the document line, so d.multicurrency_total_ht is the line amount
	 * @var string[]
	 */
	private static $currencyTypes = array('invoice', 'propal', 'order', 'supplier_invoice', 'supplier_proposal', 'supplier_order', 'contract');

	/** @var float */
	private $multicurrencyTotal = 0.0;

	/** @var float */
	private $qtyTotal = 0.0;

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
	 * Is the hook called from the consumption tab
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @return bool
	 */
	private function isConsumption($parameters)
	{
		return !empty($parameters['currentcontext']) && $parameters['currentcontext'] == 'thirdpartyconsumption';
	}

	/**
	 * Current document type of the consumption tab
	 *
	 * @return string
	 */
	private function typeElement()
	{
		return GETPOST('type_element', 'aZ09');
	}

	/**
	 * Search value of the customer/supplier reference filter
	 *
	 * @return string
	 */
	private function searchDocRef()
	{
		if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
			return '';
		}
		return GETPOST('sdoc_ref', 'alphanohtml');
	}

	/**
	 * Show the customer/supplier reference column for this document type
	 *
	 * @return bool
	 */
	private function showDocRef()
	{
		return array_key_exists($this->typeElement(), self::$docRefFields);
	}

	/**
	 * Show foreign currency columns: multicurrency enabled and third party currency differs from main currency
	 *
	 * @param CommonObject|null $object Third party
	 * @return bool
	 */
	private function showCurrency($object)
	{
		global $conf;

		if (!isModEnabled('multicurrency') || !in_array($this->typeElement(), self::$currencyTypes)) {
			return false;
		}
		$code = (is_object($object) && !empty($object->multicurrency_code)) ? $object->multicurrency_code : '';

		return ($code != '' && $code != $conf->currency);
	}

	/**
	 * Add fields into SELECT
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Third party
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
	{
		if (!$this->isConsumption($parameters)) {
			return 0;
		}
		$sql = '';
		if ($this->showDocRef()) {
			$sql .= ' '.self::$docRefFields[$this->typeElement()].' as doc_ref,';
		}
		if (in_array($this->typeElement(), self::$currencyTypes)) {
			$sql .= ' d.multicurrency_total_ht as multicurrency_total_ht,';
		}
		$this->resprints = $sql;
		return 0;
	}

	/**
	 * Add filter into WHERE
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Third party
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
	{
		if (!$this->isConsumption($parameters)) {
			return 0;
		}
		$search = $this->searchDocRef();
		if ($search !== '' && $this->showDocRef()) {
			$this->resprints = " AND ".self::$docRefFields[$this->typeElement()]." LIKE '%".$this->db->escape($search)."%'";
		}
		return 0;
	}

	/**
	 * Add cells into the search filter row
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Third party
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function printFieldListOption($parameters, &$object, &$action, $hookmanager)
	{
		global $param;

		if (!$this->isConsumption($parameters)) {
			return 0;
		}
		$search = $this->searchDocRef();
		// Keep the filter on sort links and on the bottom navigation bar ($param is built by the page before this hook)
		if ($search !== '' && isset($param)) {
			$param .= '&sdoc_ref='.urlencode($search);
		}

		if (!$this->showDocRef() && !$this->showCurrency($object)) {
			return 0;
		}
		// The page prints its search button cell after this hook and its filter row has one cell less than the
		// title row before the hooks: the first cell printed here sits under "Unit price". We also add a trailing
		// empty column (title, lines, total) so the search button gets its own column at the end.
		$out = '<th class="liste_titre"></th>';
		if ($this->showDocRef()) {
			$out .= '<th class="liste_titre left"><input class="flat maxwidth100" type="text" name="sdoc_ref" value="'.dol_escape_htmltag($search).'"></th>';
		}
		if ($this->showCurrency($object)) {
			$out .= '<th class="liste_titre"></th><th class="liste_titre"></th>';
		}
		$this->resprints = $out;
		return 0;
	}

	/**
	 * Add column titles
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Third party
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!$this->isConsumption($parameters)) {
			return 0;
		}
		$langs->loadLangs(array('multicurrency', 'winsoft@winsoft'));

		$param = isset($parameters['param']) ? $parameters['param'] : '';
		$search = $this->searchDocRef();
		if ($search !== '') {
			$param .= '&sdoc_ref='.urlencode($search);
		}
		$sortfield = isset($parameters['sortfield']) ? $parameters['sortfield'] : '';
		$sortorder = isset($parameters['sortorder']) ? $parameters['sortorder'] : '';

		ob_start();
		if ($this->showDocRef()) {
			$label = in_array($this->typeElement(), array('supplier_invoice', 'supplier_order')) ? 'RefSupplier' : 'RefCustomer';
			print_liste_field_titre($label, $_SERVER['PHP_SELF'], 'doc_ref', '', $param, '', $sortfield, $sortorder, 'left ');
		}
		if ($this->showCurrency($object)) {
			print_liste_field_titre('MulticurrencyAmountHT', $_SERVER['PHP_SELF'], 'multicurrency_total_ht', '', $param, '', $sortfield, $sortorder, 'right ');
			print_liste_field_titre('WinsoftUnitPriceCurrency', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'right ');
		}
		if ($this->showDocRef() || $this->showCurrency($object)) {
			print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'maxwidthsearch ');
		}
		$this->resprints = ob_get_clean();
		return 0;
	}

	/**
	 * Add cells for each line
	 *
	 * @param array<string,mixed> $parameters Hook parameters (obj = current SQL record)
	 * @param CommonObject $object Third party
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
	{
		if (!$this->isConsumption($parameters) || empty($parameters['obj'])) {
			return 0;
		}
		$obj = $parameters['obj'];
		$out = '';
		if ($this->showDocRef()) {
			$out .= '<td class="nowraponall tdoverflowmax150">'.dol_escape_htmltag((string) (isset($obj->doc_ref) ? $obj->doc_ref : '')).'</td>';
		}
		if ($this->showCurrency($object)) {
			$mctotal = isset($obj->multicurrency_total_ht) ? (float) $obj->multicurrency_total_ht : 0.0;
			$qty = isset($obj->prod_qty) ? (float) $obj->prod_qty : 0.0;
			$this->multicurrencyTotal += $mctotal;
			$this->qtyTotal += $qty;
			$out .= '<td class="right"><span class="amount">'.price($mctotal).'</span></td>';
			$out .= '<td class="right">'.price($mctotal / (empty($qty) ? 1 : $qty)).'</td>';
		}
		if ($out !== '') {
			$out .= '<td></td>';
		}
		$this->resprints = $out;
		return 0;
	}

	/**
	 * Add cells into the total line
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Third party
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function printFieldListTotal($parameters, &$object, &$action, $hookmanager)
	{
		if (!$this->isConsumption($parameters)) {
			return 0;
		}
		$out = '';
		if ($this->showDocRef()) {
			$out .= '<td></td>';
		}
		if ($this->showCurrency($object)) {
			$out .= '<td class="right">'.price($this->multicurrencyTotal).'</td>';
			$out .= '<td class="right">'.price(price2num($this->multicurrencyTotal / (empty($this->qtyTotal) ? 1 : $this->qtyTotal), 'MU')).'</td>';
		}
		if ($out !== '') {
			$out .= '<td></td>';
		}
		$this->resprints = $out;
		return 0;
	}
}
