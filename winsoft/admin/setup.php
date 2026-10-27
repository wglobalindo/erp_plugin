<?php
/* Copyright (C) 2026 W Global Indo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/winsoft/admin/setup.php
 * \ingroup winsoft
 * \brief   Winsoft module setup page (per-company options)
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
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

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";

$langs->loadLangs(array("admin", "propal", "winsoft@winsoft"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

$options = array(
	'WINSOFT_PDF_PRONEW_SIGNATURE' => 'WinsoftSignatureOnPronew',
	'WINSOFT_PDF_PRONEW_NO_TAX_SIGNATURE' => 'WinsoftSignatureOnPronewNoTax',
);

// Fallback when javascript is disabled (ajax_constantonoff is used otherwise)
if (preg_match('/^set_(.+)$/', $action, $reg) && array_key_exists($reg[1], $options)) {
	dolibarr_set_const($db, $reg[1], GETPOSTINT('value') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

llxHeader('', $langs->trans("WinsoftSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("WinsoftSetup"), $linkback, 'title_setup');

print '<span class="opacitymedium">'.$langs->trans("WinsoftSetupDesc").'</span><br><br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td class="center">'.$langs->trans("Value").'</td></tr>';
foreach ($options as $constname => $label) {
	print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td class="center">';
	if (!empty($conf->use_javascript_ajax)) {
		print ajax_constantonoff($constname);
	} else {
		$value = getDolGlobalInt($constname);
		print '<a href="'.$_SERVER["PHP_SELF"].'?action=set_'.$constname.'&value='.($value ? 0 : 1).'&token='.newToken().'">'.img_picto('', $value ? 'switch_on' : 'switch_off').'</a>';
	}
	print '</td></tr>';
}
print '</table>';
print '</div>';

print '<br><span class="opacitymedium">'.$langs->trans("WinsoftSignatureFile", '<b>'.dol_escape_htmltag($conf->mycompany->dir_output.'/signature.jpg').'</b>').'</span>';

llxFooter();
$db->close();
