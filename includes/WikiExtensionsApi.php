<?php

namespace WikiExtensions;

use ApiBase;
use User;

class WikiExtensionsApi extends ApiBase {

	private $formattedData;

	public function execute() {
		$params = $this->extractRequestParams();
		$action = $params['do'];
		$this->formattedData = [
			'status' => 'FAIL'
		];
		switch ($action) {
			case 'list':
				$this->doList();
				break;
			case 'extension':
				$this->doExtension( $params['extension'] );
				break;
			case 'update':
				$this->updateExtension( $params['extension'] );
				break;
		}
		$this->getResult()->addValue(
			null, $this->getModulePath(), $this->formattedData
		);
	}

	private function doList() {
		$this->formattedData['extensions'] = WikiExtensions::getInstance()->getExtensions();
		$this->formattedData['status'] = 'OK';
	}

	private function doExtension( $extension ) {
		$this->formattedData['extension'] = WikiExtensions::getInstance()->getExtension( $extension );
		$this->formattedData['status'] = 'OK';
	}

	private function updateExtension( $extension ) {
		if( $this->getUser()->isAllowed('wikiextensions_update') || $this->isWhitelisted() ) {
			$result = WikiExtensions::getInstance()->updateExtension( $extension );
			$this->formattedData['status'] = $result ? 'OK' : 'FAIL';
		}
	}

	private function isWhitelisted() {
		global $wgWikiExtensionsAllowedIP;
		if( $this->getUser()->isAnon() && User::isIP( $this->getUser()->getName() ) ) {
			if( in_array( $this->getUser()->getName(), $wgWikiExtensionsAllowedIP ) ) {
				return true;
			}
		}
		return false;
	}

	public function getAllowedParams() {
		$params = [
			'do' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'extension' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
		return $params + parent::getAllowedParams();
	}

}
