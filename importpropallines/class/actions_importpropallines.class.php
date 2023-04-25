<?php

/**
 * Copyright © 2015-2016 Marcos García de La Fuente <hola@marcosgdf.com>
 * Copyright © 2018 Julien Marchand <julien.marchand@iouston.com>
 *
 * This file is part of Importpropalelines, un module développé sur la base du module importorderline développé par Marcos Garcia
 *
 * This file is part of Importpropallines.
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


class ActionsImportpropallines
{
	/**
	 * @param   array         	$parameters     Hook metadatas (context, etc...)
	 * @param   Propal    		$object        	The object to process
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons(array $parameters, Propal $object, &$action, HookManager $hookmanager)
	{
		global $langs;

		$langs->load('importpropallines@importpropallines');

		if ($object->statut < 1) {
			print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=import">' . $langs->trans('ImportpropalLines') . '</a></div>';
		}

		return 0;
	}

	/**
	 * @param   array         $parameters     Hook metadatas (context, etc...)
	 * @param   Propale    $object        The object to process
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function formConfirm(array $parameters, Propal $object, &$action, HookManager $hookmanager)
	{
		global $langs, $db, $conf;
		
		$langs->load('importpropallines@importpropallines');

		if ($object->statut >= 1) {
			return 0;
		}

		require __DIR__.'/Utils.php';

		if ($action == 'import') {

			$this->resprints = Utils::uploadForm(
				$_SERVER["PHP_SELF"] . '?id=' . $object->id,
				$langs->trans('ImportpropalLines'),
				$langs->trans('ConfirmClonepropal', $object->ref),
				'confirm_import',
				$langs->trans('SelectFileToImport')
			);

		} elseif ($action == 'confirm_import') {
								
			try {

				if (!isset($_FILES['uploadfile'])) {
					throw new Exception($langs->trans('UploadFileError'));
				}

				$file = $_FILES['uploadfile'];

				if (!is_uploaded_file($file['tmp_name'])) {
					throw new Exception($langs->trans('UploadFileError'));
				}

				if ($file['error'] != UPLOAD_ERR_OK) {
					throw new Exception($langs->trans('UploadFileError'), $file['error']);
				}

				require __DIR__.'/../lib/phpoffice/phpexcel/Classes/PHPExcel.php';

				//Supported PHPExcel File readers to ensure we deal with a Spreadsheet.
				$supported_filereaders = array(
					'CSV',
					'Excel2007',
					'Excel5',
					'OOCalc',
					'Excel2003XML'
				);

				if (!in_array(PHPExcel_IOFactory::identify($file['tmp_name']), $supported_filereaders)) {
					throw new Exception($langs->trans('UploadFileErrorUnsupportedFormat'));
				}

				try {
					$excelfd = PHPExcel_IOFactory::load($file['tmp_name']);
				} catch (PHPExcel_Reader_Exception $e) {
					throw new Exception($e->getMessage());
				}

				$activesheet = $excelfd->getActiveSheet();

				//Check of the format
				$a1 = $activesheet->getCell('A1')->getValue() == $langs->transnoentities('Ref');
				$b1 = $activesheet->getCell('B1')->getValue() == $langs->transnoentities('Qty');
				$c1 = $activesheet->getCell('C1')->getValue() == $langs->transnoentities('Price');
				$d1 = $activesheet->getCell('D1')->getValue() == $langs->transnoentities('Cost');
				//$b1 = $activesheet->getCell('B1')->getValue() == $langs->transnoentities('Label');

				if (!$a1 || !$b1 || !$c1 ) {
					throw new Exception($langs->trans('UploadFileErrorFormat'));
				}

				$maxrow = $activesheet->getHighestRow();

				for ($i = 2; $i <= $maxrow; $i++) {

					$qty = (int) $activesheet->getCellByColumnAndRow(1, $i)->getValue();

					
					if (isset($qty)) {

						$ref = $activesheet->getCellByColumnAndRow(0, $i)->getValue();
						$prixuht =$activesheet->getCellByColumnAndRow(2, $i)->getValue();
						$cost =$activesheet->getCellByColumnAndRow(3, $i)->getValue();
						$prod = new Product($db);

						if (!$ref=='' && $prod->fetch('', $ref) <= 0) {
							//throw new Exception($langs->trans('ErrorProductNotFound', $ref));
							Utils::addpropalLine_manual($object, $ref, $qty, $prixuht, $cost);
						}
						else if($ref ==''){

						}
						else{

						Utils::addpropalLine($object, $prod, $qty, $prixuht);

						unset($prod);
						}
					}
				}

			} catch (Exception $e) {

				$message = $e->getMessage();

				setEventMessage($e->getMessage(), 'errors');

				if ($e->getCode()) {
					$message .= '. Error code: '.$e->getCode();
				}

				dol_syslog('[importpropallines] '.$message, LOG_DEBUG);

				return -1;
			}

			//Delete temporary file
			unlink($file['tmp_name']);

			//Reload the object with new lines
			$object->fetch($object->id);
			$object->fetch_thirdparty($object->socid);

			if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {

				// Define output language
				$outputlangs = $langs;
				$newlang = GETPOST('lang_id', 'alpha');
				if (! empty($conf->global->MAIN_MULTILANGS) && empty($newlang))
					$newlang = $object->thirdparty->default_lang;
				if (! empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}

				// PDF
				$hidedetails = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
				$hidedesc = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
				$hideref = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);

				//Propale_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
				//$object->generateDocument($object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);

			}

		}

		return 0;
	}

}