<?php

/**
 * Copyright © 2015-2016 Marcos García de La Fuente <hola@marcosgdf.com>
 *
 * This file is part of Importorderlines.
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

if (file_exists('../../main.inc.php')) {
	require __DIR__.'/../../main.inc.php';
} else {
	require __DIR__.'/../../../main.inc.php';
}

$langs->load('admin');
$langs->load('exports');
$langs->load('other');
$langs->load('importsupplier_proposal@importsupplier_proposal');

llxHeader();

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('ImportSupplierproposalLinesInfo'), $linkback);

?>

<div class="titre"><?php echo $langs->trans('ImportSupplierproposalLinesTitle') ?></div>

<p><?php echo $langs->trans('ImportSupplierproposalLinesInfoFormat') ?></p><ul>
	<li><?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatA', $langs->transnoentities('Ref')) ?></li>
	<li><?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatB', $langs->transnoentities('Label')) ?></li>
	<li><?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatC', $langs->transnoentities('Qty')) ?></li>
</ul>
<p><?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatMore') ?></p>
<p><?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatCreate',
		$langs->transnoentities('Tools'),
		$langs->transnoentities('NewExport'),
		$langs->transnoentities('Products'),
		$langs->transnoentities('Ref')
	).$langs->trans('ImportSupplierproposalLinesInfoFormatCreate2',
			$langs->transnoentities('Label'),
			$langs->transnoentities('Qty')
		) ?></p>
<p><?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatExample') ?></p>
<img src="<?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatExampleImgSrc') ?>" alt="<?php echo $langs->trans('ImportSupplierproposalLinesInfoFormatExampleImgAlt') ?>">

<br><br>

<div class="titre"><?php echo $langs->trans('ImportSupplierproposalLinesInfoUsing') ?></div>

<p><?php echo $langs->trans('ImportSupplierproposalLinesInfoUsingSupplierproposal', $langs->transnoentities('ImportSupplierproposalLines')) ?></p>

<br>

<div class="titre"><?php echo $langs->trans('ImportSupplierproposalLinesAbout') ?></div>

<p><?php echo $langs->trans('ImportSupplierproposalLinesAuthor', '<a href="http://marcosgdf.com">http://marcosgdf.com</a>') ?></p>
<p><?php echo $langs->trans('ImportSupplierproposalLinesContact', '<a href="mailto:hola@marcosgdf.com">hola@marcosgdf.com</a>') ?></p>

<?php

llxFooter();