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

	protected function setUp(): void {
		parent::setUp();

		// Find the location of the examples extension
		$registry = ExtensionRegistry::getInstance();
		$all = $registry->getAllThings();
		$this->extensionPath = dirname( $all['examples']['path'] );
	}

	protected function tearDown(): void {
		$path = $this->extensionPath;
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path reset --hard origin/REL1_35" );

		parent::tearDown();
	}

	public function testUnknownExtension() {
		$extensions = WikiExtensions::getInstance();

		$this->assertNull( $extensions->getExtension( '???' ) );

		$status = $extensions->updateExtension( '???' );
		$this->assertTrue( $status->isGood() );
	}

	public function testAllExtensions() {
		$extensions = WikiExtensions::getInstance();

		$all = $extensions->getExtensions();
		$this->assertIsArray( $all );

		$found = false;
		$wikiExtensions = false;
		foreach ( $all as $ext ) {
			$this->assertIsArray( $ext );
			$this->assertArrayHasKey( 'name', $ext );
			if ( $ext['name'] === 'examples' ) {
				$found = true;
			} elseif ( $ext['name'] === 'WikiExtensions' ) {
				$wikiExtensions = $ext;
			}
		}
		$this->assertTrue( $found, 'examples extension found' );
		$this->assertIsArray( $wikiExtensions );
		// Should have failed to fetch due to private repo
		$this->assertNull( $wikiExtensions['git']['remote_commit'] );
		$this->assertNull( $wikiExtensions['git']['remote_commit_short'] );
		// In GitHub actions, able to fetch for some reason
		if ( $wikiExtensions['error'] !== null ) {
			$this->assertSame(
				"Command 'git fetch -q' failed (exit-code 128).",
				$wikiExtensions['error']
			);
		}
	}

	public function testFailedPull() {
		$extensions = WikiExtensions::getInstance();
		$result = $extensions->updateExtension( 'WikiExtensions' );
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
					'branch' => 'REL1_35',
					'status' => [
						'On branch REL1_35',
						'Your branch is up to date with \'origin/REL1_35\'.',
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
					'branch' => 'REL1_35',
					'status' => [
						'On branch REL1_35',
						'Your branch is behind \'origin/REL1_35\' by 1 commit, and can be fast-forwarded.',
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
