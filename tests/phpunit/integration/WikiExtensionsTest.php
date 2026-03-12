<?php

namespace MediaWiki\Extension\WikiExtensions\Tests\Integration;

use ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use WikiExtensions\WikiExtensions;

/**
 * @covers \WikiExtensions\WikiExtensions
 * @group medium
 */
class WikiExtensionsTest extends MediaWikiIntegrationTestCase {

	/** @var string */
	private string $extensionPath;

	private string $branch;

	protected function setUp(): void {
		parent::setUp();

		// Find the location of the examples extension
		$registry = ExtensionRegistry::getInstance();
		$all = $registry->getAllThings();
		$this->extensionPath = dirname( $all['examples']['path'] );

		if ( version_compare( MW_VERSION, '1.43', '>=' ) ) {
			$this->branch = 'REL1_43';
		} elseif ( version_compare( MW_VERSION, '1.39', '>=' ) ) {
			$this->branch = 'REL1_39';
		} else {
			$this->branch = 'REL1_35';
		}
	}

	protected function tearDown(): void {
		$path = $this->extensionPath;
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path config remote.origin.url https://github.com/wikimedia/mediawiki-extensions-examples" );
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path reset --hard origin/" . $this->branch );

		parent::tearDown();
	}

	public function testUnknownExtension() {
		$extensions = WikiExtensions::getInstance();

		$this->assertNull( $extensions->getExtension( '???' ) );

		$status = $extensions->updateExtension( '???' );
		$this->assertTrue( $status->isGood() );
	}

	public function testAllExtensions() {
		// Break the git config for the extension so that pulling will fail,
		// the teardown will restore the git config
		$path = $this->extensionPath;
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path config remote.origin.url borken" );

		$extensions = WikiExtensions::getInstance();

		$all = $extensions->getExtensions();
		$this->assertIsArray( $all );

		$exampleExtension = false;
		$wikiExtensions = false;
		foreach ( $all as $ext ) {
			$this->assertIsArray( $ext );
			$this->assertArrayHasKey( 'name', $ext );
			if ( $ext['name'] === 'examples' ) {
				$exampleExtension = $ext;
			} elseif ( $ext['name'] === 'WikiExtensions' ) {
				$wikiExtensions = $ext;
			}
		}
		$this->assertIsArray( $wikiExtensions );
		$this->assertIsArray( $exampleExtension, 'examples extension found' );
		// Should have failed to fetch due to broken remote
		$this->assertNull( $exampleExtension['git']['remote_commit'] );
		$this->assertNull( $exampleExtension['git']['remote_commit_short'] );
		$this->assertSame(
			"Command 'git -c 'safe.directory=*' fetch -q' failed (exit-code 128).",
			$exampleExtension['error']
		);
	}

	public function testFailedPull() {
		// Break the git config for the extension so that pulling will fail,
		// the teardown will restore the git config
		$path = $this->extensionPath;
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path config remote.origin.url borken" );

		$extensions = WikiExtensions::getInstance();
		$result = $extensions->updateExtension( 'examples' );
		$this->assertFalse( $result->isOK() );
		$this->assertSame(
			[
				[
					'type' => 'error',
					'message' => 'Command \'git merge --abort\' failed (exit-code 128).',
					'params' => [],
				],
				[
					'type' => 'error',
					'message' => 'Command \'git pull\' failed (exit-code 1).',
					'params' => [],
				],
			],
			$result->getErrors()
		);
	}

	public function testOnLatest() {
		$extensions = WikiExtensions::getInstance();

		$data = $extensions->getExtension( 'examples' );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'git', $data );
		$this->assertIsArray( $data['git'] );
		$this->assertArrayHasKey( 'remote_commit', $data['git'] );
		$this->assertArrayHasKey( 'local_commit', $data['git'] );
		$this->assertArrayHasKey( 'remote_commit_short', $data['git'] );
		$this->assertArrayHasKey( 'local_commit_short', $data['git'] );

		// Not pinning to specific commits, just checking correctness
		$remoteCommit = $data['git']['remote_commit'];
		$localCommit = $data['git']['local_commit'];
		$remoteCommitShort = $data['git']['remote_commit_short'];
		$localCommitShort = $data['git']['local_commit_short'];
		unset( $data['git']['remote_commit'] );
		unset( $data['git']['local_commit'] );
		unset( $data['git']['remote_commit_short'] );
		unset( $data['git']['local_commit_short'] );

		$branch = $this->branch;
		$this->assertSame(
			[
				'name' => 'examples',
				'version' => null,
				'is_writable' => 1,
				'is_git' => 1,
				'has_updates' => 0,
				'has_uncommitted_changes' => 0,
				'can_pull' => 0,
				'has_submodules' => 0,
				'git' => [
					'branch' => $branch,
					'status' => [
						'On branch ' . $branch,
						"Your branch is up to date with 'origin/$branch'.",
						'',
						'nothing to commit, working tree clean',
					],
				],
				'error' => null,
			],
			$data
		);
		$this->assertSame( $remoteCommitShort, $localCommitShort );
	}

	public function testNotLatest() {
		// Check out the previous commit but clean up afterwards so it is just
		// behind a bit
		$path = $this->extensionPath;
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path reset HEAD~1" );
		// `git restore` not available in the docker image that comes with mediawiki
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path checkout -- ." );

		$extensions = WikiExtensions::getInstance();

		$data = $extensions->getExtension( 'examples' );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'git', $data );
		$this->assertIsArray( $data['git'] );
		$this->assertArrayHasKey( 'remote_commit', $data['git'] );
		$this->assertArrayHasKey( 'local_commit', $data['git'] );
		$this->assertArrayHasKey( 'remote_commit_short', $data['git'] );
		$this->assertArrayHasKey( 'local_commit_short', $data['git'] );

		// Not pinning to specific commits, just checking correctness
		$remoteCommit = $data['git']['remote_commit'];
		$localCommit = $data['git']['local_commit'];
		$remoteCommitShort = $data['git']['remote_commit_short'];
		$localCommitShort = $data['git']['local_commit_short'];
		unset( $data['git']['remote_commit'] );
		unset( $data['git']['local_commit'] );
		unset( $data['git']['remote_commit_short'] );
		unset( $data['git']['local_commit_short'] );

		$branch = $this->branch;
		$this->assertSame(
			[
				'name' => 'examples',
				'version' => null,
				'is_writable' => 1,
				'is_git' => 1,
				'has_updates' => 1,
				'has_uncommitted_changes' => 0,
				'can_pull' => 1,
				'has_submodules' => 0,
				'git' => [
					'branch' => $branch,
					'status' => [
						'On branch ' . $branch,
						"Your branch is behind 'origin/$branch' by 1 commit, and can be fast-forwarded.",
						'  (use "git pull" to update your local branch)',
						'',
						'nothing to commit, working tree clean',
					],
				],
				'error' => null,
			],
			$data
		);
		$this->assertNotSame( $remoteCommitShort, $localCommitShort );
	}

	public function testUpdate() {
		// Bring us to the prior commit and make sure things worked
		$this->testNotLatest();

		$extensions = WikiExtensions::getInstance();
		$status = $extensions->updateExtension( 'examples' );
		$this->assertTrue( $status->isGood() );

		// And now should be on the latest commit
		$this->testOnLatest();
	}

}
