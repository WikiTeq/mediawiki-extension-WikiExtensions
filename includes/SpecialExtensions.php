<?php

namespace WikiExtensions;

use PermissionsError;
use SpecialPage;

class SpecialExtensions extends SpecialPage {

	/** @inheritDoc */
	public function __construct( $name = 'Extensions' ) {
		parent::__construct( $name );
	}

	/**
	 * @param string|null $subPage
	 * @return void
	 * @throws PermissionsError
	 */
	public function execute( $subPage = null ) {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'wikiextensions-special-extensions-title' ) );

		if ( !$this->getUser()->isAllowed( 'wikiextensions_view' ) ) {
			// it throws the PermissionsError exception
			$this->displayRestrictionError();
		}

		$GitRepositoryClassName = 'CzProject\\GitPhp\\GitRepository';
		if ( !class_exists( $GitRepositoryClassName ) ) {
			$out->addWikiMsg( 'wikiextensions-special-not-installed', $GitRepositoryClassName );
			return;
		}

		$out->addModules( 'ext.wikiextensions.special' );

		$html = $this->renderHomepage();

		$out->addHTML( $html );
	}

	private function renderHomepage() {
		return "<div class='wikiextensions-wrapper'></div>";
	}
}
