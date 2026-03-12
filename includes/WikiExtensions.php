<?php

namespace WikiExtensions;

use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\Runners\CliRunner;
use ExtensionRegistry;
use Status;

class WikiExtensions {

	/** @var WikiExtensions */
	private static $instance = null;
	/** @var ExtensionRegistry */
	private $extensionRegistry;
	/** @var array[] */
	private $things;

	/**
	 * @return WikiExtensions
	 */
	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->extensionRegistry = ExtensionRegistry::getInstance();
		$this->things = $this->extensionRegistry->getAllThings();
	}

	/**
	 * @return array
	 */
	public function getExtensions() {
		$data = [];
		foreach ( $this->things as $key => $thing ) {
			$data[] = $this->getExtension( $key );
		}
		return $data;
	}

	/**
	 * @param string $extensionName
	 *
	 * @return array|null
	 */
	public function getExtension( $extensionName ) {
		// Bow out quickly in case of a wrong extension name
		if ( !array_key_exists( $extensionName, $this->things ) ) {
			return null;
		}

		// Get the extension directory
		$extension = $this->things[$extensionName];
		$path = dirname( $extension['path'] );

		// Form default answer
		$result = [
			'name' => $extensionName,
			'version' => $extension['version'] ?? null,
			'is_writable' => (int)false,
			'is_git' => (int)false,
			'has_updates' => (int)false,
			'has_uncommitted_changes' => (int)false,
			'can_pull' => (int)false,
			'has_submodules' => (int)false,
			'git' => [
				'branch' => null,
				'remote_commit' => null,
				'local_commit' => null,
				'remote_commit_short' => null,
				'local_commit_short' => null
			],
			'error' => null
		];

		// Check if .git dir exists and bow out if it's not
		if ( !file_exists( $path . '/.git' ) ) {
			return $result;
		}

		$result['is_git'] = (int)true;

		// Check if .git dir is writable and bow out if it's not
		if ( !is_writable( $path . '/.git' ) ) {
			return $result;
		}

		$result['is_writable'] = (int)true;

		if ( file_exists( $path . '/.gitmodules' ) ) {
			$result['has_submodules'] = (int)true;
		}

		// Try to get some GIT information
		try {
			// Init the repository
			$repo = new GitRepository( $path, new AlwaysSafeRunner( new CliRunner() ) );
			// Get local branch name
			$localBranch = $repo->getCurrentBranchName();
			$result['git']['branch'] = $localBranch;
			// Get local commit SHA
			$commit = $repo->getLastCommitId();
			$result['git']['local_commit'] = $commit;
			$result['git']['local_commit_short'] = substr( $commit, 0, 7 );
			$result['git']['status'] = $repo->execute( 'status' );
			// Check if we're in detached HEAD state, in such case
			// we can't do pull because we're not tracking any remotes
			if ( strpos( $localBranch, 'detached' ) !== false ) {
				// HEAD is detached, bow out
				return $result;
			} else {
				// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.passthru
				passthru( "GIT_SSH_COMMAND=\"ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no\"" );
				// Fetch remote
				// Work around https://github.com/czproject/git-php/issues/77
				$repo->run( 'fetch', [ '-q' ] );
				// Get remote commit SHA
				$res = $repo->execute( [
					'rev-parse',
					$localBranch . '@{upstream}'
				] );
				$remoteCommit = $res[0];
				$result['git']['remote_commit'] = $remoteCommit;
				$result['git']['remote_commit_short'] = substr( $remoteCommit, 0, 7 );

				if ( $remoteCommit != $commit ) {
					$result['has_updates'] = (int)true;
					$result['can_pull'] = (int)true;
				}
			}

			$result['has_uncommitted_changes'] = (int)$repo->hasChanges();
			if ( $result['has_uncommitted_changes'] ) {
				$result['can_pull'] = (int)false;
			}

		} catch ( GitException $e ) {
			// Something gone wrong, do nothing
			$result['error'] = $e->getMessage();
			$result['can_pull'] = (int)false;
			return $result;
		}

		return $result;
	}

	/**
	 * @param string $extensionName
	 *
	 * @return Status
	 * @throws GitException
	 */
	public function updateExtension( $extensionName ) {
		$status = Status::newGood();

		if ( !array_key_exists( $extensionName, $this->things ) ) {
			return $status;
		}

		$path = dirname( $this->things[$extensionName]['path'] );

		try {
			$repo = new GitRepository( $path );
		} catch ( GitException $e ) {
			// We should not land here, not a repo
			return Status::newFatal( $e->getMessage() );
		}

		// Pull
		try {
			// Work around https://github.com/czproject/git-php/issues/77
			$repo->run( 'pull' );
		} catch ( GitException $e ) {
			// A merge conflict?
			if ( $e->getCode() === 1 ) {
				try {
					$repo->execute( [
						'merge',
						'--abort'
					] );
				} catch ( GitException $e2 ) {
					$status = Status::newFatal( $e2->getMessage() );
					$status->fatal( $e->getMessage() );
					return $status;
				}
				$repo->execute( [
					'reset',
					'--keep',
					'HEAD@{1}'
				] );
			}
			if ( $e->getCode() === 128 ) {
				// Perms?
			}
			return Status::newFatal( $e->getMessage() );
		}

		// Pull submodules
		if ( file_exists( $path . '/.gitmodules' ) ) {
			try {
				$repo->execute( [
					'submodule',
					'update'
				] );
			} catch ( GitException $e ) {
				return Status::newFatal( $e->getMessage() );
			}
		}

		return $status;
	}

}
