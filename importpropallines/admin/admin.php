<?php

/**
 * Copyright © 2015-2016 Marcos García de La Fuente <hola@marcosgdf.com>
 * Copyright © 2018 Julien Marchand <julien.marchand@iouston.com>
 *
 * This file is part of Importpropalelines, un module développé sur la base du module importorderline développé par Marcos Garcia
 *
 * This file is part of Importorpropal lines.
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
$langs->load('importpropallines@importpropallines');

llxHeader();

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('ImportpropalLinesInfo'), $linkback);

?>

<div class="titre"><?php echo $langs->trans('ImportpropalLinesTitle') ?></div>

<p><?php echo $langs->trans('ImportpropalLinesInfoFormat') ?></p><ul>
	<li><?php echo $langs->trans('ImportpropalLinesInfoFormatA', $langs->transnoentities('Ref')) ?></li>
	<li><?php echo $langs->trans('ImportpropalLinesInfoFormatB', $langs->transnoentities('Label')) ?></li>
	<li><?php echo $langs->trans('ImportpropalLinesInfoFormatC', $langs->transnoentities('Qty')) ?></li>
	<li><?php echo $langs->trans('ImportpropalLinesInfoFormatD', $langs->transnoentities('PU ht')) ?></li>
</ul>
<p><?php echo $langs->trans('ImportpropalLinesInfoFormatMore') ?></p>
<p><?php echo $langs->trans('ImportpropalLinesInfoFormatCreate',
		$langs->transnoentities('Tools'),
		$langs->transnoentities('NewExport'),
		$langs->transnoentities('Products'),
		$langs->transnoentities('Ref')
	).$langs->trans('ImportpropalLinesInfoFormatCreate2',
			$langs->transnoentities('Label'),
			$langs->transnoentities('Qty')
		) ?></p>
<p><?php echo $langs->trans('ImportpropalLinesInfoFormatExample') ?></p>
<img src="<?php echo $langs->trans('ImportpropalLinesInfoFormatExampleImgSrc') ?>" alt="<?php echo $langs->trans('ImportpropalLinesInfoFormatExampleImgAlt') ?>">

<br><br>
<p><?php echo $langs->trans('ImportpropalLinesInfoLibelleCol') ?></p>

<div class="titre"><?php echo $langs->trans('ImportpropalLinesInfoUsing') ?></div>

<p><?php echo $langs->trans('ImportpropalLinesInfoUsingpropal', $langs->transnoentities('ImportpropalLines')) ?></p>
<p><b><?php echo $langs->trans('ImportpropalLinesInfoParticularites') ?></b></p>

<br>

<div class="titre"><?php echo $langs->trans('ImportpropalLinesAbout') ?></div>

<p><?php echo $langs->trans('ImportpropalLinesAuthor', '<a href="http://www.iouston.com">www.iouston.com</a>', '<a href="https://www.dolistore.com/fr/crm-gestion-relation-client/470-Import-order-lines.html">importorderline</a>', '<a href="http://marcosgdf.com">http://marcosgdf.com</a>') ?></p>
<p><?php echo $langs->trans('ImportpropalLinesContact', '<a href="mailto:julien.marchand@ioustonµ.com">julien.marchand@iouston.com</a>') ?></p>

<?php

llxFooter();