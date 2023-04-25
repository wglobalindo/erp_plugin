<?php

/**
 * Copyright © 2015-2016 Marcos García de La Fuente <hola@marcosgdf.com>
 * Copyright © 2018 Julien Marchand <julien.marchand@iouston.com>
 *
 * This file is part of Importpropalelines, un module développé sur la base du module importorderline développé par Marcos Garcia
 *
 * This file is part of Importpropalelines.
 *
 * Multismtp is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Multismtp is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Multismtp.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Dolibarr license:
 *
 * You can find a copy of the code at http://github.com/dolibarr/dolibarr
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class Utils
{

	/**
	 * Piece of code extracted from Form::formconfirm to show a confirm dialog with a upload form
	 * File input has name 'uploadfile'
	 *
	 * @param string $page Url of page to call if confirmation is OK
	 * @param string $title Title
	 * @param string $question Question
	 * @param string $action Action
	 * @param string $label Label of the input
	 * @return string HTML code
	 */
	public static function uploadForm($page, $title, $question, $action, $label)
	{
		global $langs;

		$formconfirm = "\n<!-- begin form_confirm page=".$page." -->\n";

		$formconfirm.= '<form method="POST" action="'.$page.'" class="notoptoleftroright" enctype="multipart/form-data">'."\n";
		$formconfirm.= '<input type="hidden" name="action" value="'.$action.'">';
		$formconfirm.= '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">'."\n";

		$formconfirm.= '<table width="100%" class="valid">'."\n";

		// Line title
		$formconfirm.= '<tr class="validtitre"><td class="validtitre" colspan="3">'.img_picto('','recent').' '.$title.'</td></tr>'."\n";

		// Line form fields
		$formconfirm.='<tr class="valid"><td class="valid" colspan="3">'."\n";
		$formconfirm.=$label.'</td><td valign="top" colspan="2" align="left">';
		$formconfirm.= '<input type="file" name="uploadfile">';
		$formconfirm.='</td></tr>'."\n";
		$formconfirm.='</td></tr>'."\n";

		// Line with question
		$formconfirm.= '<tr class="valid">';
		$formconfirm.= '<td class="valid" colspan="3"></td>';
		$formconfirm.= '<td class="valid" colspan="2"><input class="button" type="submit" value="'.$langs->trans("Upload").'"></td>';
		$formconfirm.= '</tr>'."\n";

		$formconfirm.= '</table>'."\n";

		$formconfirm.= "</form>\n";
		$formconfirm.= '<br>';

		$formconfirm.= "<!-- end form_confirm -->\n";

		return $formconfirm;
	}

	/**
	 * Adds a product to the propale
	 *
	 * @param Propale $object propale object
	 * @param Product $prod Product to add
	 * @param int $qty Quantity of the product
	 * @throws Exception
	 */
	public static function addpropalLine(Propal $object, Product $prod, $qty, $prixuht)
	{
		global $db, $conf, $mysoc, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

		$tva_tx = get_default_tva($mysoc, $object->thirdparty, $prod->id);
		$tva_npr = get_default_npr($mysoc, $object->thirdparty, $prod->id);

		if (! empty($conf->global->PRODUIT_MULTIPRICES) && ! empty($object->thirdparty->price_level))
		{
			$pu_ht = $prod->multiprices [$object->thirdparty->price_level];
			$pu_ttc = $prod->multiprices_ttc [$object->thirdparty->price_level];
			$price_base_type = $prod->multiprices_base_type [$object->thirdparty->price_level];

			if (isset($prod->multiprices_tva_tx[$object->thirdparty->price_level])) {
				$tva_tx=$prod->multiprices_tva_tx[$object->thirdparty->price_level];
			}
			if (isset($prod->multiprices_recuperableonly[$object->thirdparty->price_level])) {
				$tva_npr=$prod->multiprices_recuperableonly[$object->thirdparty->price_level];
			}
		}
		elseif (! empty($conf->global->PRODUIT_CUSTOMER_PRICES))
		{
			require_once DOL_DOCUMENT_ROOT . '/product/class/productcustomerprice.class.php';

			$prodcustprice = new Productcustomerprice($db);

			$filter = array('t.fk_product' => $prod->id,'t.fk_soc' => $object->thirdparty->id);

			$result = $prodcustprice->fetch_all('', '', 0, 0, $filter);
			if ($result >= 0) {
				if (count($prodcustprice->lines) > 0) {
					$pu_ht = price($prodcustprice->lines[0]->price);
					$pu_ttc = price($prodcustprice->lines[0]->price_ttc);
					$price_base_type = $prodcustprice->lines[0]->price_base_type;
					$prod->tva_tx = $prodcustprice->lines[0]->tva_tx;
				} else {
					$pu_ht = $prod->price;
					$pu_ttc = $prod->price_ttc;
					$price_base_type = $prod->price_base_type;
				}
			} else {
				throw new Exception($prodcustprice->error);
			}
		}
		else
		{
			$pu_ht = $prod->price;
			$pu_ttc = $prod->price_ttc;
			$price_base_type = $prod->price_base_type;
		}


		//Si le prix est importé et forcé depuis le tableau d'import
		if(isset($prixuht)){
		$pu_ht = $prixuht;
		}

		// if price ht is forced (ie: calculated by margin rate and cost price)
		if (! empty($price_ht)) {
			$pu_ht = price2num($price_ht, 'MU');
			$pu_ttc = price2num($pu_ht * (1 + ($tva_tx / 100)), 'MU');
		}

		// On reevalue prix selon taux tva car taux tva transaction peut etre different
		// de ceux du produit par defaut (par exemple si pays different entre vendeur et acheteur).
		elseif ($tva_tx != $prod->tva_tx) {
			if ($price_base_type != 'HT') {
				$pu_ht = price2num($pu_ttc / (1 + ($tva_tx / 100)), 'MU');
			} else {
				$pu_ttc = price2num($pu_ht * (1 + ($tva_tx / 100)), 'MU');
			}
		}

		// Define output language
		if (! empty($conf->global->MAIN_MULTILANGS) && ! empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE)) {
			$outputlangs = $langs;
			$newlang = '';
			if (empty($newlang) && GETPOST('lang_id', 'alpha'))
				$newlang = GETPOST('lang_id', 'alpha');
			if (empty($newlang))
				$newlang = $object->thirdparty->default_lang;
			if (! empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
			}

			$desc = (! empty($prod->multilangs [$outputlangs->defaultlang] ["description"])) ? $prod->multilangs [$outputlangs->defaultlang] ["description"] : $prod->description;
		} else {
			$desc = $prod->description;
		}

		// Add custom code and origin country into description
		if (empty($conf->global->MAIN_PRODUCT_DISABLE_CUSTOMCOUNTRYCODE) && (! empty($prod->customcode) || ! empty($prod->country_code))) {
			$tmptxt = '(';
			if (! empty($prod->customcode))
				$tmptxt .= $langs->transnoentitiesnoconv("CustomCode") . ': ' . $prod->customcode;
			if (! empty($prod->customcode) && ! empty($prod->country_code))
				$tmptxt .= ' - ';
			if (! empty($prod->country_code))
				$tmptxt .= $langs->transnoentitiesnoconv("CountryOrigin") . ': ' . getCountry($prod->country_code, 0, $db, $langs, 0);
			$tmptxt .= ')';
			$desc = dol_concatdesc($desc, $tmptxt);
		}

		//3.9.0 version added support for price units
		if (versioncompare(versiondolibarrarray(), array(3,9,0)) >= 0) {
			$fk_unit = $prod->fk_unit;
		} else {
			$fk_unit = null;
		}

		// Local Taxes
		$localtax1_tx = get_localtax($tva_tx, 1, $object->thirdparty);
		$localtax2_tx = get_localtax($tva_tx, 2, $object->thirdparty);

		$info_bits = 0;
		if ($tva_npr)
			$info_bits |= 0x01;

		//Percent remise
		if (! empty($object->thirdparty->remise_percent)) {
			$percent_remise = $object->thirdparty->remise_percent;
		} else {
			$percent_remise=0;
		}

		// Insert line
		
		$result = $object->addline(
			$desc,
			$pu_ht,
			$qty,
			$tva_tx,
			$localtax1_tx,
			$localtax2_tx,
			$prod->id,
			$percent_remise,
			$price_base_type,
			$pu_ttc,
			$info_bits,
			$prod->type,
			-1,//rang
			0,//special code
			0, //fk_parent_line
			0, //fk_fournprice
			$prod->cost_price,//pa ht
			'',//label
			'', //$date_start 
		  	'',	//$date_end
		  	0, //$array_options
		  	$fk_unit,
		  	'', //$origin 
		  	0, //$origin_id 
		  	0, //$pu_ht_devise 
		  	0 //$fk_remise_except
		);

		if ($result < 0) {
			throw new Exception($langs->trans('ErrorAddpropalLine', $prod->ref));
			}
	}
	public static function addpropalLine_manual(Propal $object, $desc, $qty=1, $pu_ht=0, $cost=0)
	{

		// Insert line
		$result = $object->addline(
			$desc,
			$pu_ht,
			$qty,
			0, //$tva_tx,
			$localtax1_tx,
			$localtax2_tx,
			'', //$prod->id,
			$percent_remise,
			$price_base_type,
			$pu_ht,//$pu_ttc,
			$info_bits,
			0,	//$prod->type,
			-1,//rang
			0,//special code
			0, //fk_parent_line
			0, //fk_fournprice
			$cost,//pa ht
			'',//label
			'', //$date_start 
		  	'',	//$date_end
		  	0, //$array_options
		  	$fk_unit,
		  	'', //$origin 
		  	0, //$origin_id 
		  	0, //$pu_ht_devise 
		  	0 //$fk_remise_except
		);		


		if ($result < 0) {
			throw new Exception($langs->trans('ErrorAddpropalLine', $prod->ref));
		}
	}

}