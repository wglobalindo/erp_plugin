<?php
/* Copyright (C) 2026 W Global Indo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/vptop/core/triggers/interface_99_modVPtoP_VPtoPTriggers.class.php
 * \ingroup vptop
 * \brief   Triggers of module VPtoP
 *
 * ORDER_SUPPLIER_VALIDATE: free text lines of the supplier order become stock products (class/freelineproduct.class.php),
 * so reception adds stock and shipment of the customer order removes it. Disable with VPTOP_FREELINE_PRODUCT = 0.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Triggers of module VPtoP
 */
class InterfaceVPtoPTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "other";
		$this->description = "VPtoP triggers: free text lines of validated supplier orders become stock products.";
		$this->version = '24.0.2-1';
		$this->picto = 'generic';
	}

	/**
	 * Run trigger
	 *
	 * @param string		$action		Event code
	 * @param CommonObject	$object		Object
	 * @param User			$user		User
	 * @param Translate		$langs		Langs
	 * @param Conf			$conf		Conf
	 * @return int						<0 if KO, 0 if nothing done, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('vptop')) {
			return 0;
		}
		if ($action == 'ORDER_SUPPLIER_VALIDATE' && getDolGlobalInt('VPTOP_FREELINE_PRODUCT', 1)) {
			dol_include_once('/vptop/class/freelineproduct.class.php');
			$freeline = new VptopFreeLineProduct($this->db);
			$result = $freeline->linkSupplierOrder($object, $user);
			if ($result < 0) {
				$this->setErrorsFromObject($freeline);
				dol_syslog(__METHOD__.' '.$freeline->error, LOG_ERR);
				return -1;
			}
			dol_syslog(__METHOD__.' supplier order '.$object->id.': '.$result.' free text line(s) linked to a product');
			return $result;
		}
		return 0;
	}
}
