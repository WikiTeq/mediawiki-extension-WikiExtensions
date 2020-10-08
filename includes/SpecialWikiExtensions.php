<?php

namespace WikiExtensions;

use Cz\Git\GitRepository;
use Cz\Git\GitException;
use ExtensionRegistry;
use SpecialPage;
use TemplateParser;

class SpecialWikiExtensions extends SpecialPage {

	public function __construct( $name = 'WikiExtensions' ) {
		parent::__construct( $name );
	}

	public function execute( $param = null ) {

		if( !$this->getUser()->isAllowed( 'wikiextensions_view' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$out = $this->getOutput();
		$out->addModules( 'ext.wikiextensions.special' );
		$out->setPageTitle( wfMessage( 'wikiextensions-special-extensions-title' ) );

		$html = $this->renderHomepage();

		$out->addHTML( $html );

	}

	private function updateExtension( $extension ) {
		$r = ExtensionRegistry::getInstance()->getAllThings()[ $extension ];
		$path = dirname( $r['path'] );

		try {
			$repo = new GitRepository( $path );
		}
		catch ( GitException $e ) {
			// We should not land here, not a repo
			return false;
		}

		try {
			$repo->pull();
			return true;
		}
		catch ( GitException $e ) {
			// A conflict?
			$repo->execute([
				'merge',
				'--abort'
			]);
			$repo->execute([
				'reset',
				'--keep',
				'HEAD@{1}'
			]);
			return false;
		}

	}

	private function renderHomepage() {
		$tp = new TemplateParser( dirname( __FILE__ ) . '/../modules/templates', true );

		$things = ExtensionRegistry::getInstance()->getAllThings();

		/*$data = [];
		foreach ( $things as $key => $thing ) {
			$info = $this->fetchExtensionState( $thing );
			$data[] = [
				'title' => $key,
				'version' => array_key_exists( 'version', $thing ) ? $thing['version'] : '-',
				'can_update' => $info['is_git'] && $info['can_write'] && (!$info['local'] && $info['remote']),
				'data' => $info
			];
		}*/

		//$html = $tp->processTemplate( 'extensions', [] /*[ 'extensions' => $data ]*/ );
		$html = "<div class='wikiextensions-wrapper'></div>";
		return $html;
	}

	private function fetchExtensionState( $extension ) {

		$changesRemote = false;
		$changesLocal = false;
		$detached = false;
		$localBranch = null;
		$commit = null;
		$writable = false;
		$isGit = false;

		$path = dirname( $extension['path'] );
		// create repo object

		if( file_exists( $path . '/.git' ) ) {
			$isGit = true;

			if ( is_writable( $path . '/.git' ) ) {

				$writable = true;

				try {
					$repo = new GitRepository( $path );
					$repo->fetch();
					$localBranch = $repo->getCurrentBranchName();

					if ( strpos( $localBranch, 'HEAD detached' ) == false ) {
						$commit = $repo->getLastCommitId();
						$res = $repo->execute( [
							'rev-parse',
							$localBranch . '@{upstream}'
						] );
						$remoteCommit = $res[0];

						if ( $remoteCommit != $commit ) {
							$changesRemote = true;
						}
					} else {
						// HEAD is detached
						$detached = true;
					}

					$changesLocal = $repo->hasChanges();

				}
				catch ( GitException $e ) {
					// Could be not a git repo, so just skip
				}

			}

		}

		return [
			'local' => $changesLocal,
			'remote' => $changesRemote,
			'detached' => $detached,
			'branch' => $localBranch,
			'commit' => $commit,
			'can_write' => $writable,
			'is_git' => $isGit
		];

	}

}
