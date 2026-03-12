<?php

namespace WikiExtensions;

use ApiBase;
use ApiUsageException;
use Wikimedia\IPUtils;
use Wikimedia\ParamValidator\ParamValidator;

class WikiExtensionsApi extends ApiBase {

	/** @var array */
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
		switch ( $action ) {
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
		$this->getResult()->addValue( null, $this->getModulePath(), $this->formattedData );
	}

	private function doList() {
		if ( $this->getUser()->isAllowed( 'wikiextensions_view' ) || $this->isWhitelisted() ) {
			$this->formattedData['extensions'] = WikiExtensions::getInstance()->getExtensions();
			$this->formattedData['status'] = 'OK';
		} else {
			$this->formattedData['status'] = 'FAIL';
		}
	}

	/**
	 * @param string $extension
	 */
	private function doExtension( $extension ) {
		if ( $this->getUser()->isAllowed( 'wikiextensions_view' ) || $this->isWhitelisted() ) {
			$this->formattedData['extension'] = WikiExtensions::getInstance()
				->getExtension( $extension );
			$this->formattedData['status'] = 'OK';
		} else {
			$this->formattedData['status'] = 'FAIL';
		}
	}

	/**
	 * @param string $extension
	 */
	private function updateExtension( $extension ) {
		if ( $this->getUser()->isAllowed( 'wikiextensions_update' ) || $this->isWhitelisted() ) {
			$result = WikiExtensions::getInstance()->updateExtension( $extension );
			$this->formattedData['status'] = $result->isGood() ? 'OK' : $result->getMessage()
				->getKey();
			$this->formattedData['extension'] = WikiExtensions::getInstance()
				->getExtension( $extension );
		}
	}

	/**
	 * Can the user modify extension status based on being an anonymous user
	 * with an allowed IP address?
	 *
	 * @return bool
	 */
	private function isWhitelisted() {
		$allowed = $this->getConfig()->get( 'WikiExtensionsAllowedIP' );
		if ( $this->getUser()->isAnon() && IPUtils::isIPAddress( $this->getUser()->getName() ) ) {
			if ( in_array( $this->getUser()->getName(), $allowed ) ) {
				return true;
			}
		}
		return false;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		$params = [
			'do' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'extension' => [
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
		return $params + parent::getAllowedParams();
	}

}
