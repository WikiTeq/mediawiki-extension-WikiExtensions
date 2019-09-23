<?php

namespace WikiExtensions;

use Cz\Git\GitRepository;
use Cz\Git\GitException;
use ExtensionRegistry;

class WikiExtensions {

	private static $instance = null;
	private $extensionRegistry;

	public static function getInstance() {
		if( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->extensionRegistry = ExtensionRegistry::getInstance();
	}

	public function getExtensions() {
		$things = $this->extensionRegistry->getAllThings();
		$data = [];
		foreach ( $things as $key => $thing ) {
			$data[ $key ] = $this->getExtension( $key );
		}
		return $data;
	}

	public function getExtension( $extensionName ) {
		$things = $things = $this->extensionRegistry->getAllThings();

		if( !array_key_exists( $extensionName, $things ) ) {
			return null;
		}

		$extension = $things[ $extensionName ];
		$path = dirname( $extension['path'] );

		$changesRemote = false;
		$changesLocal = false;
		$detached = false;
		$localBranch = null;
		$commit = null;
		$writable = false;
		$isGit = false;

		// TODO: rework!
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

	public function updateExtension( $extensionName ) {
		$things = $this->extensionRegistry->getAllThings();

		if( !array_key_exists( $extensionName, $things ) ) {
			return null;
		}

		$path = dirname( $things[ $extensionName ]['path'] );

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

}
