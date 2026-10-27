<?php
/* Copyright (C) 2026 W Global Indo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \defgroup   winsoft     Module Winsoft
 * \brief      Company customizations moved out of the Dolibarr core (Dolibarr 24)
 *
 * \file       htdocs/custom/winsoft/core/modules/modWinsoft.class.php
 * \ingroup    winsoft
 * \brief      Description and activation file for module Winsoft
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module Winsoft
 *
 * Contents:
 * - 14 PDF document models (invoice, proposal, order, shipment, supplier documents)
 * - Hooks replacing former direct core edits (third party "consumption" tab)
 * - Per-company settings (signature on proposal PDFs)
 */
class modWinsoft extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->numero = 500100;
		$this->rights_class = 'winsoft';
		$this->family = "other";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "WinsoftDescription";
		$this->descriptionlong = "WinsoftDescription";
		$this->editor_name = 'W Global Indo';
		$this->editor_url = '';
		$this->version = '24.0.2-2';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'generic';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			// PDF models are stored into core/modules/<element>/doc/ of this module
			'models' => 1,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(
				'data' => array(
					'thirdpartyconsumption',
					// product statistics pages (unit price column), see class/actions_winsoft.class.php
					'productstatsorder',
					'productstatsinvoice',
					'productstatssupplierinvoice',
					'productstatspropal',
					'productstatssupplierpropal',
				),
				'entity' => '0',
			),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array("setup.php@winsoft");

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("winsoft@winsoft");
		$this->phpmin = array(8, 1);
		$this->need_dolibarr_version = array(24, 0);
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants are only inserted when missing, so a company value already set (ex: 0) is kept on re-activation.
		// Array(name, type, value, desc, visible, entity, deleteonunactive)
		$this->const = array(
			0 => array('WINSOFT_PDF_PRONEW_SIGNATURE', 'chaine', '1', 'Print signature image on proposal PDF "pronew"', 0, 'current', 0),
			1 => array('WINSOFT_PDF_PRONEW_NO_TAX_SIGNATURE', 'chaine', '1', 'Print signature image on proposal PDF "pronew_no_tax"', 0, 'current', 0),
			2 => array('WINSOFT_PRODUCT_STATS_UNIT_PRICE', 'chaine', '1', 'Product statistics pages with unit price column (0 = standard pages)', 0, 'current', 0),
		);

		if (!isModEnabled("winsoft")) {
			$conf = $GLOBALS['conf'];
			$conf->winsoft = new stdClass();
			$conf->winsoft->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$this->menu = array();
	}

	/**
	 *  Function called when module is enabled.
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/winsoft/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
