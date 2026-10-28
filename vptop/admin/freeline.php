<?php
/* Copyright (C) 2026 W Global Indo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/vptop/admin/freeline.php
 * \ingroup vptop
 * \brief   Free text lines -> products: group similar names into one product, mapping list, apply to past lines.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/vptop/lib/vptop.lib.php');
dol_include_once('/vptop/class/freelineproduct.class.php');

$langs->loadLangs(array("admin", "products", "vptop@vptop"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$freeline = new VptopFreeLineProduct($db);
$form = new Form($db);


/*
 * Actions
 */

if ($action == 'setmap') {
	// Link the selected names to one product (existing one, or a new one)
	$keys = GETPOST('name_key', 'array:restricthtml');
	$fk_product = GETPOSTINT('fk_product');
	$newlabel = trim(GETPOST('newlabel', 'alphanohtml'));
	if (empty($keys)) {
		setEventMessages($langs->trans("VptopFreeLineNoNameSelected"), null, 'errors');
	} elseif ($fk_product <= 0 && $newlabel === '') {
		setEventMessages($langs->trans("VptopFreeLineChooseProduct"), null, 'errors');
	} else {
		$db->begin();
		$error = 0;
		if ($fk_product <= 0) {
			$fk_product = $freeline->createProduct($newlabel, 0, $user);
			if ($fk_product < 0) {
				$error++;
			}
		}
		if (!$error) {
			foreach ($keys as $key) {
				$key = VptopFreeLineProduct::normalize($key);	// keys are already normalized, this only cleans the posted value
				if ($freeline->setMap($key, $key, $fk_product, $user) < 0) {
					$error++;
					break;
				}
			}
		}
		if ($error) {
			$db->rollback();
			setEventMessages($freeline->error, $freeline->errors, 'errors');
		} else {
			$db->commit();
			setEventMessages($langs->trans("VptopFreeLineMapped", count($keys)), null, 'mesgs');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'changemap') {
	$key = GETPOST('name_key', 'restricthtml');
	$fk_product = GETPOSTINT('fk_product_'.GETPOSTINT('rowid'));
	if ($fk_product > 0 && $freeline->setMap(VptopFreeLineProduct::normalize($key), GETPOST('label', 'alphanohtml'), $fk_product, $user) > 0) {
		setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
	} else {
		setEventMessages($freeline->error ? $freeline->error : $langs->trans("VptopFreeLineChooseProduct"), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'deletemap') {
	if ($freeline->deleteMap(GETPOSTINT('rowid')) > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
	} else {
		setEventMessages($freeline->error, null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'apply') {
	$tables = array_intersect(GETPOST('tables', 'array'), array_keys(VptopFreeLineProduct::$lineTables));
	$db->begin();
	$result = $freeline->applyMapToExistingLines($tables);
	if (is_array($result)) {
		$db->commit();
		$msg = array();
		foreach ($result as $table => $nb) {
			$msg[] = $langs->trans(VptopFreeLineProduct::$lineTables[$table][1]).': '.$nb;
		}
		setEventMessages($langs->trans("VptopFreeLineApplied").' '.implode(', ', $msg), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($freeline->error, null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}


/*
 * View
 */

llxHeader('', $langs->trans("VptopFreeLineTitle"), '', '', 0, 0, '', '', '', 'mod-vptop page-admin-freeline');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("VPtoPSetup"), $linkback, 'title_setup');

$head = vptopAdminPrepareHead();
print dol_get_fiche_head($head, 'freeline', '', -1, 'generic');

print '<span class="opacitymedium">'.$langs->trans("VptopFreeLineHelp").'</span><br>';
print '<span class="opacitymedium">'.$langs->trans("VptopFreeLineStatus").': '.(getDolGlobalInt('VPTOP_FREELINE_PRODUCT', 1) ? $langs->trans("Enabled") : $langs->trans("Disabled"));
print ' &nbsp; ('.'VPTOP_FREELINE_PRODUCT, VPTOP_FREELINE_PRODUCT_PREFIX = '.dol_escape_htmltag(getDolGlobalString('VPTOP_FREELINE_PRODUCT_PREFIX', 'FL')).')</span><br><br>';

// 1. Names not linked yet
$names = $freeline->listNames(true);
if (!is_array($names)) {
	dol_print_error($db, $freeline->error);
	$names = array();
}
$groupsize = array();
foreach ($names as $n) {
	$groupsize[$n['loose']] = (isset($groupsize[$n['loose']]) ? $groupsize[$n['loose']] : 0) + 1;
}
// Mapped names by loose key, to suggest the product of a similar mapped name
$mappedloose = array();
$resql = $db->query("SELECT name_key, fk_product FROM ".MAIN_DB_PREFIX."vptop_freeline_map WHERE entity = ".((int) $conf->entity));
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$mappedloose[VptopFreeLineProduct::looseKey($obj->name_key)] = (int) $obj->fk_product;
	}
}
$suggestproduct = new Product($db);
print load_fiche_titre($langs->trans("VptopFreeLineUnmapped").' ('.count($names).')', '', '');
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" name="formsetmap">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setmap">';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th></th><th>'.$langs->trans("VptopFreeLineName").'</th><th>'.$langs->trans("VptopFreeLineSimilar").'</th>';
foreach (VptopFreeLineProduct::$lineTables as $table => $info) {
	print '<th class="right">'.$langs->trans($info[1]).'</th>';
}
print '<th class="right">'.$langs->trans("Total").'</th><th>'.$langs->trans("VptopFreeLineSuggested").'</th></tr>';
$prevloose = null;
$groupnum = 0;
foreach ($names as $n) {
	if ($n['loose'] !== $prevloose) {
		$groupnum++;
		$prevloose = $n['loose'];
	}
	$similar = ($groupsize[$n['loose']] > 1);
	print '<tr class="oddeven'.($similar ? ' highlight' : '').'">';
	print '<td class="nowrap"><input type="checkbox" class="flat checkforselect" name="name_key[]" value="'.dol_escape_htmltag($n['key']).'"></td>';
	print '<td class="tdoverflowmax500" title="'.dol_escape_htmltag($n['label']).'">'.dol_escape_htmltag($n['label']).'</td>';
	print '<td>'.($similar ? '<span class="badge badge-status1">'.$langs->trans("VptopFreeLineGroup").' '.$groupnum.' ('.$groupsize[$n['loose']].')</span>' : '').'</td>';
	foreach (VptopFreeLineProduct::$lineTables as $table => $info) {
		print '<td class="right">'.(empty($n['count'][$table]) ? '' : (int) $n['count'][$table]).'</td>';
	}
	print '<td class="right">'.(int) $n['total'].'</td>';
	print '<td>';
	if (!empty($mappedloose[$n['loose']]) && $suggestproduct->fetch($mappedloose[$n['loose']]) > 0) {
		print $suggestproduct->getNomUrl(1);
	}
	print '</td>';
	print '</tr>';
}
if (empty($names)) {
	print '<tr class="oddeven"><td colspan="'.(5 + count(VptopFreeLineProduct::$lineTables)).'"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table></div>';
print '<br>'.$langs->trans("VptopFreeLineLinkSelectedTo").' ';
print $form->select_produits(0, 'fk_product', 0, 0, 0, -1, 2, '', 0, array(), 0, 1, 0, 'minwidth300');
print ' '.$langs->trans("or").' '.$langs->trans("VptopFreeLineNewProduct").' <input type="text" class="flat minwidth300" name="newlabel" value="">';
print ' <input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("VptopFreeLineLink")).'">';
print '</form><br><br>';

// 2. Mappings
print load_fiche_titre($langs->trans("VptopFreeLineMappings"), '', '');
$sql = "SELECT m.rowid, m.name_key, m.label, m.fk_product, m.datec FROM ".MAIN_DB_PREFIX."vptop_freeline_map as m";
$sql .= " WHERE m.entity = ".((int) $conf->entity)." ORDER BY m.fk_product, m.name_key";
$resql = $db->query($sql);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans("VptopFreeLineName").'</th><th>'.$langs->trans("Product").'</th><th>'.$langs->trans("DateCreation").'</th><th></th></tr>';
$product = new Product($db);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td class="tdoverflowmax500" title="'.dol_escape_htmltag($obj->label).'">'.dol_escape_htmltag($obj->label).'</td>';
		print '<td>';
		if ($product->fetch($obj->fk_product) > 0) {
			print $product->getNomUrl(1).' - '.dol_escape_htmltag($product->label);
		} else {
			print '<span class="error">'.$langs->trans("ErrorRecordNotFound").' ('.(int) $obj->fk_product.')</span>';
		}
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="inline-block">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="changemap">';
		print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
		print '<input type="hidden" name="name_key" value="'.dol_escape_htmltag($obj->name_key).'">';
		print '<input type="hidden" name="label" value="'.dol_escape_htmltag($obj->label).'">';
		print ' &nbsp; '.$form->select_produits(0, 'fk_product_'.((int) $obj->rowid), 0, 0, 0, -1, 2, '', 0, array(), 0, 1, 0, 'maxwidth200');
		print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans("Modify")).'">';
		print '</form>';
		print '</td>';
		print '<td>'.dol_print_date($db->jdate($obj->datec), 'day').'</td>';
		print '<td class="right"><a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=deletemap&token='.newToken().'&rowid='.((int) $obj->rowid).'">'.img_delete().'</a></td>';
		print '</tr>';
	}
} else {
	dol_print_error($db);
}
print '</table></div><br><br>';

// 3. Apply to past lines
print load_fiche_titre($langs->trans("VptopFreeLineApplyTitle"), '', '');
print '<span class="opacitymedium">'.$langs->trans("VptopFreeLineApplyHelp").'</span><br>';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="apply">';
foreach (VptopFreeLineProduct::$lineTables as $table => $info) {
	$checked = in_array($table, array('commande_fournisseurdet', 'commandedet'));
	print '<label class="marginrightonly"><input type="checkbox" name="tables[]" value="'.$table.'"'.($checked ? ' checked' : '').'> '.$langs->trans($info[1]).'</label> ';
}
print ' <input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("VptopFreeLineApply")).'">';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
