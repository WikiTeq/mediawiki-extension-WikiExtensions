<?php

namespace WikiExtensions;

use ApiBase;
use ApiUsageException;
use User;

class WikiExtensionsApi extends ApiBase {

	private $formattedData;

	/**
	 * @throws ApiUsageException
	 */
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
		if( $this->getUser()->isAllowed( 'wikiextensions_view' ) || $this->isWhitelisted() ) {
			$this->formattedData['extensions'] = WikiExtensions::getInstance()->getExtensions();
			$this->formattedData['status'] = 'OK';
		}else{
			$this->formattedData['status'] = 'FAIL';
		}
	}

	private function doExtension( $extension ) {
		if( $this->getUser()->isAllowed( 'wikiextensions_view' ) || $this->isWhitelisted() ) {
			$this->formattedData['extension'] = WikiExtensions::getInstance()->getExtension( $extension );
			$this->formattedData['status'] = 'OK';
		}else{
			$this->formattedData['status'] = 'FAIL';
		}
	}

	private function updateExtension( $extension ) {
		if( $this->getUser()->isAllowed('wikiextensions_update') || $this->isWhitelisted() ) {
			$result = WikiExtensions::getInstance()->updateExtension( $extension );
			$this->formattedData['status'] = $result ? 'OK' : 'FAIL';
			$this->formattedData['extension'] = WikiExtensions::getInstance()->getExtension( $extension );
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
