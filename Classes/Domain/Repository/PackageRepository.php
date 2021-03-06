<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Daniel Corn <info@cundd.net>, cundd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 *
 * @package cundd_composer
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class Tx_CunddComposer_Domain_Repository_PackageRepository extends Tx_Extbase_Persistence_Repository {

	/**
	 * The composer.json contents
	 *
	 * @var array
	 */
	protected $composerJson;

	/**
	 * The property mapper
	 *
	 * @var Tx_Extbase_Property_PropertyMapper
	 * @inject
	 */
	protected $propertyMapper;

	/**
	 * The property mapping configuration builder
	 *
	 * @var Tx_Extbase_Property_PropertyMappingConfigurationBuilder
	 * @inject
	 */
	protected $propertyMappingConfigurationBuilder;

	/**
	 * Array of package objects
	 *
	 * @var SplObjectStorage
	 */
	protected $packages = NULL;

	/**
	 * Returns all objects of this repository.
	 *
	 * @return array
	 * @api
	 */
	public function findAll() {
		if (!$this->packages) {
			// Get the package domain object properties
			$properties = new Tx_CunddComposer_Domain_Model_Package();
			$properties = array_keys($properties->_getProperties());

			$this->packages = new \SplObjectStorage();
			$composerJson = $this->getComposerJson();
			foreach ($composerJson as $currentJsonData) {
				// Flatten the fields "require" and "authors"
				$this->convertPropertyForKey($currentJsonData, 'authors');
				$this->convertPropertyForKey($currentJsonData, 'require');
				$this->convertPropertyForKey($currentJsonData, 'require-dev', 'requireDev');

				// Prepare the property mapping configuration
				$propertyMappingConfiguration = $this->propertyMappingConfigurationBuilder->build();

				// Filter the properties
				$currentJsonData = array_intersect_key($currentJsonData, array_flip($properties));
				// Doesn't work in extbase: $propertyMappingConfiguration->allowProperties($properties);

				$package = $this->propertyMapper->convert($currentJsonData, 'Tx_CunddComposer_Domain_Model_Package');
				if ($package) {
					$this->packages->attach($package);
				}
			}
		}
		return $this->packages;
	}

	/**
	 * Converts an array property to a string
	 *
	 * @param array 	$source Reference to the input array
	 * @param string 	$key    The key which to convert
	 * @param string 	$newKey The new key under which to store the converted data
	 * @return void
	 */
	protected function convertPropertyForKey(&$source, $key, $newKey = '') {
		if (isset($source[$key])) {
			if (!$newKey) {
				$newKey = $key;
			}
			$originalData = $source[$key];

			array_walk($originalData, function(&$value, $key) {
				$value = $key . ' ' . $value;
			});
			$source[$newKey] = implode(PHP_EOL, $originalData);
		}
	}

	/**
	 * Returns the list of composer.json files
	 *
	 * @return array<string>
	 */
	public function getComposerFiles() {
		$composerFiles = array();
		$extensions = explode(',', t3lib_extMgm::getEnabledExtensionList());

		foreach ($extensions as $extension) {
			$composerFilePath = t3lib_extMgm::extPath($extension) . '/composer.json';
			if (file_exists($composerFilePath)) {
				$composerFiles[] = $composerFilePath;
			}
		}
		return $composerFiles;
	}

	/**
	 * Returns the composer.json contents as array
	 *
	 * @return array
	 */
	public function getComposerJson() {
		if (!$this->composerJson) {
			$jsonData = array();
			$composerFiles = $this->getComposerFiles();
			foreach ($composerFiles as $composerFilePath) {
				$currentJsonData = NULL;
				$jsonString = file_get_contents($composerFilePath);

				if ($jsonString) {
					$currentJsonData = json_decode($jsonString, TRUE);
				}
				if (!$currentJsonData) {
					throw new \DomainException('Exception while parsing composer file ' . $composerFilePath . ': ' . $this->getJsonErrorDescription(), 1356356009);
				}
				$jsonData[] = $currentJsonData;
			}
			$this->composerJson = $jsonData;
		}
		return $this->composerJson;
	}

	/**
	 * Returns an error description for the last JSON error
	 *
	 * @return string
	 */
	protected function getJsonErrorDescription() {
		$error = '';
		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				$error = 'No errors';
			break;
			case JSON_ERROR_DEPTH:
				$error = 'Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				$error = 'Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				$error = 'Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				$error = 'Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				$error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
			default:
				$error = 'Unknown error';
			break;
		}
		return $error;
	}

}
?>