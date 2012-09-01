<?php
/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *
 * $Id$
 *                                                                        */

/**
 * A logger to store submission information in TYPO3 database
 *
 * @author	Reinhard Führicht <rf@typoheads.at>
 * @package	Tx_Formhandler
 * @subpackage	Logger
 */
class Tx_Formhandler_Logger_DB extends Tx_Formhandler_AbstractLogger {

	/**
	 * Logs the given values.
	 *
	 * @return void
	 */
	public function process() {

		//set params
		$table = 'tx_formhandler_log';

		$doDisableIPlog = $this->utilityFuncs->getSingle($this->settings, 'disableIPlog');
		$fields['ip'] = t3lib_div::getIndpEnv('REMOTE_ADDR');
		if(intval($doDisableIPlog) === 1) {
			unset($fields['ip']);
		}
		$fields['tstamp'] = time();
		$fields['crdate'] = time();
		$fields['pid'] = $this->utilityFuncs->getSingle($this->settings, 'pid');
		if (!$fields['pid']) {
			$fields['pid'] = $GLOBALS['TSFE']->id;
		}
		ksort($this->gp);
		$keys = array_keys($this->gp);

		$logParams = $this->gp;

		if($this->settings['fields.']) {
			foreach($this->settings['fields.'] as $field => $fieldConf) {
				$field = str_replace('.', '', $field);
				if($fieldConf['ifIsEmpty'] && (empty($logParams[$field]) || !isset($logParams[$field]))) {
					$value = $this->utilityFuncs->getSingle($fieldConf, 'ifIsEmpty');
					$logParams[$field] = $value;
				}
				if(intval($this->utilityFuncs->getSingle($fieldConf, 'nullIfEmpty')) === 1 && (empty($logParams[$field]) || !isset($logParams[$field]))) {
					unset($logParams[$field]);
				}
			}
		}
		if($this->settings['excludeFields']) {
			$excludeFields = $this->utilityFuncs->getSingle($this->settings, 'excludeFields');
			$excludeFields = t3lib_div::trimExplode(',', $excludeFields);
			foreach($excludeFields as $excludeField) {
				unset($logParams[$excludeField]);
			}
		}
		$serialized = serialize($logParams);
		$hash = md5(serialize($keys));
		$uniqueHash = sha1(sha1($serialized) . $TYPO3_CONF_VARS['SYS']['encryptionKey'] . time() . $this->globals->getRandomID());
		$fields['params'] = $serialized;
		$fields['key_hash'] = $hash;
		$fields['unique_hash'] = $uniqueHash;

		if (intval($this->settings['markAsSpam']) === 1) {
			$fields['is_spam'] = 1;
		}

		//query the database
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $fields);
		$insertedUID = $GLOBALS['TYPO3_DB']->sql_insert_id();
		$sessionValues = array (
			'inserted_uid' => $insertedUID,
			'inserted_tstamp' => $fields['tstamp'],
			'key_hash' => $hash,
			'unique_hash' => $uniqueHash
		);
		$this->globals->getSession()->setMultiple($sessionValues);

		if (intval($this->utilityFuncs->getSingle($this->settings, 'nodebug')) !== 1) {
			$this->utilityFuncs->debugMessage('logging', array($table, implode(',', $fields)));
			if (strlen($GLOBALS['TYPO3_DB']->sql_error()) > 0) {
				$this->utilityFuncs->debugMessage('error', array($GLOBALS['TYPO3_DB']->sql_error()), 3);
			}
		}

		return $this->gp;
	}

}
?>
