<?php

// We run Phan with both MW 1.39 and MW 1.43, the suppression from 1.39 isn't
// needed on 1.43 and phan would complain about that
// @phan-file-suppress UnusedPluginSuppression,UnusedPluginFileSuppression

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
		if ( version_compare( MW_VERSION, '1.41', '>=' ) ) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$out->setPageTitleMsg( $this->msg( 'wikiextensions-special-extensions-title' ) );
		} else {
			$out->setPageTitle( $this->msg( 'wikiextensions-special-extensions-title' ) );
		}

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

	/**
	 * Get the HTML that will be targetted by the JavaScript
	 *
	 * @return string
	 */
	private function renderHomepage() {
		return "<div class='wikiextensions-wrapper'></div>";
	}
}
