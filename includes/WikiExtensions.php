<?php

namespace WikiExtensions;

use Cz\Git\GitRepository;
use Cz\Git\GitException;
use ExtensionRegistry;

class WikiExtensions {

	private static $instance = null;
	private $extensionRegistry;
	private $things;

	public static function getInstance() {
		if( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->extensionRegistry = ExtensionRegistry::getInstance();
		$this->things = $this->extensionRegistry->getAllThings();
	}

	public function getExtensions() {
		$data = [];
		foreach ( $this->things as $key => $thing ) {
			$data[] = $this->getExtension( $key );
		}
		return $data;
	}

	public function getExtension( $extensionName ) {

		// Bow out quickly in case of a wrong extension name
		if( !array_key_exists( $extensionName, $this->things ) ) {
			return null;
		}

		// Get the extension directory
		$extension = $this->things[ $extensionName ];
		$path = dirname( $extension['path'] );

		// Form default answer
		$result = [
			'name' => $extensionName,
			'version' => isset( $extension['version'] ) ? $extension['version'] : null,
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
		if( !file_exists( $path . '/.git' ) ) {
			return $result;
		}

		$result['is_git'] = (int)true;

		// Check if .git dir is writable and bow out if it's not
		if ( !is_writable( $path . '/.git' ) ) {
			return $result;
		}

		$result['is_writable'] = (int)true;

		if( file_exists( $path . '/.gitmodules' ) ) {
			$result['has_submodules'] = (int)true;
		}

		// Try to get some GIT information
		try {
			// Init the repository
			$repo = new GitRepository( $path );
			// Get local branch name
			$localBranch = $repo->getCurrentBranchName();
			$result['git']['branch'] = $localBranch;
			// Get local commit SHA
			$commit = $repo->getLastCommitId();
			$result['git']['local_commit'] = $commit;
			$result['git']['local_commit_short'] = substr( $commit, 0, 7 );
			// Check if we're in detached HEAD state, in such case
			// we can't do pull because we're not tracking any remotes
			if( strpos( $localBranch, 'detached' ) !== false ) {
				// HEAD is detached, bow out
				return $result;
			}else{
				passthru( "GIT_SSH_COMMAND=\"ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no\"" );
				// Fetch remote
				$repo->fetch(null, [ '-q' ]);
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
			if( $result['has_uncommitted_changes'] ) {
				$result['can_pull'] = (int)false;
			}

		}
		catch ( GitException $e ) {
			// Something gone wrong, do nothing
			$result['error'] = $e->getMessage();
			$result['can_pull'] = (int)false;
			return $result;
		}

		return $result;
	}

	/**
	 * @param $extensionName
	 *
	 * @return bool|null
	 * @throws GitException
	 */
	public function updateExtension( $extensionName ) {

		if( !array_key_exists( $extensionName, $this->things ) ) {
			return null;
		}

		$path = dirname( $this->things[ $extensionName ]['path'] );

		try {
			$repo = new GitRepository( $path );
		}
		catch ( GitException $e ) {
			// We should not land here, not a repo
			return false;
		}

		try {
			// Pull changes
			$repo->pull();
			// Pull submodules
			if( file_exists( $path . '/.gitmodules' ) ) {
				$repo->execute([
					'submodule',
					'update'
				]);
			}
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
