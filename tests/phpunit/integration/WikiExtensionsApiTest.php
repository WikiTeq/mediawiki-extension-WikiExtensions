<?php

namespace MediaWiki\Extension\WikiExtensions\Tests\Integration;

use ApiTestCase;
use ExtensionRegistry;
use Status;
use User;
use WikiExtensions\WikiExtensions;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \WikiExtensions\WikiExtensionsApi
 * @group Database
 * @group medium
 */
class WikiExtensionsApiTest extends ApiTestCase {

	/** @var string */
	private string $extensionPath;

	private string $branch;

	protected function setUp(): void {
		parent::setUp();

		// Find the location of the examples extension
		$registry = ExtensionRegistry::getInstance();
		$all = $registry->getAllThings();
		$this->extensionPath = dirname( $all['examples']['path'] );

		$this->setGroupPermissions( [
			'*' => [
				'wikiextensions_view' => false,
				'wikiextensions_update' => false,
			],
			'view-only' => [
				'wikiextensions_view' => true,
				'wikiextensions_update' => false,
			],
			'sysop' => [
				'wikiextensions_view' => true,
				'wikiextensions_update' => true,
			],
		] );
		$this->setMwGlobals( [
			'wgWikiExtensionsAllowedIP' => [ '192.168.0.0' ],
		] );

		if ( version_compare( MW_VERSION, '1.43', '>=' ) ) {
			$this->branch = 'REL1_43';
		} elseif ( version_compare( MW_VERSION, '1.39', '>=' ) ) {
			$this->branch = 'REL1_39';
		} else {
			$this->branch = 'REL1_35';
		}
	}

	protected function tearDown(): void {
		TestingAccessWrapper::newFromClass( WikiExtensions::class )->instance = null;

		$path = $this->extensionPath;
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path config remote.origin.url https://github.com/wikimedia/mediawiki-extensions-examples" );
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path reset --hard origin/" . $this->branch );

		parent::tearDown();
	}

	protected function doExtensionsApiRequest( array $params, User $user ): array {
		$params['action'] = 'wikiextensions';
		return $this->doApiRequest( $params, null, false, $user )[0];
	}

	public static function provideFail() {
		yield 'Unknown action' => [ [ 'do' => 'unknown' ] ];
		yield 'List' => [ [ 'do' => 'list' ] ];
		yield 'Extension' => [ [ 'do' => 'extension', 'extension' => 'irrelevant' ] ];
		yield 'Update' => [ [ 'do' => 'update', 'extension' => 'irrelevant' ] ];
	}

	/**
	 * @dataProvider provideFail
	 */
	public function testFail( array $params ) {
		$result = $this->doExtensionsApiRequest(
			$params,
			User::newFromName( '1.2.3.4', false )
		);
		$this->assertSame(
			[
				'wikiextensions' => [ 'status' => 'FAIL' ],
			],
			$result
		);
	}

	public function testGetExamples() {
		$result = $this->doExtensionsApiRequest(
			[
				'do' => 'extension',
				'extension' => 'examples'
			],
			User::newFromName( '192.168.0.0', false )
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'wikiextensions', $result );
		$this->assertCount( 1, $result );
		$innerRes = $result['wikiextensions'];
		$this->assertIsArray( $innerRes );
		$this->assertArrayHasKey( 'status', $innerRes );
		$this->assertSame( 'OK', $innerRes['status'] );
		$this->assertArrayHasKey( 'extension', $innerRes );
		$this->assertCount( 2, $innerRes );
		$this->verifyExtensionData( $innerRes['extension'] );
	}

	public function testUpdateExample() {
		// Nothing to actually update, but API result is the same, tests of the
		// actual updating happen in WikiExtensionsTest
		$result = $this->doExtensionsApiRequest(
			[
				'do' => 'update',
				'extension' => 'examples'
			],
			User::newFromName( '192.168.0.0', false )
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'wikiextensions', $result );
		$this->assertCount( 1, $result );
		$innerRes = $result['wikiextensions'];
		$this->assertIsArray( $innerRes );
		$this->assertArrayHasKey( 'status', $innerRes );
		$this->assertSame( 'OK', $innerRes['status'] );
		$this->assertArrayHasKey( 'extension', $innerRes );
		$this->assertCount( 2, $innerRes );

		$this->verifyExtensionData( $innerRes['extension'] );
	}

	public function testUpdateFailure() {
		// Break the git config for the extension so that pulling will fail,
		// the teardown will restore the git config
		$path = $this->extensionPath;
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( "git -C $path config remote.origin.url borken" );
		$result = $this->doExtensionsApiRequest(
			[
				'do' => 'update',
				'extension' => 'examples'
			],
			User::newFromName( '192.168.0.0', false )
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'wikiextensions', $result );
		$this->assertCount( 1, $result );
		$innerRes = $result['wikiextensions'];
		$this->assertIsArray( $innerRes );
		$this->assertArrayHasKey( 'status', $innerRes );
		// Not sure where this key comes from, but it should be improved
		if ( version_compare( MW_VERSION, '1.43', '>=' ) ) {
			$this->assertSame( 'rawmessage', $innerRes['status'] );
		} else {
			$this->assertSame( "* \$1\n* \$2", $innerRes['status'] );
		}
		$this->assertArrayHasKey( 'extension', $innerRes );
		$this->assertCount( 2, $innerRes );

		// Not checking all of the details, that is done in WikiExtensionsTest
		$this->assertIsArray( $innerRes['extension'] );
		$this->assertSame( 'examples', $innerRes['extension']['name'] );
	}

	private function verifyExtensionData( $data ) {
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
		// Unlike in WikiExtensionsTest, when the data is passed to the API
		// ApiResult::validateValue() will convert the CzProject\GitPhp\CommitId
		// objects to strings (by calling __toString()) and so we can compare
		// the full commits too
		$this->assertSame( $remoteCommitShort, $localCommitShort );
		$this->assertSame( $remoteCommit, $localCommit );
	}

	private function checkListAllowed( User $user ) {
		$service = $this->createNoOpMock(
			WikiExtensions::class,
			[ 'getExtensions' ]
		);
		$service->expects( $this->once() )
			->method( 'getExtensions' )
			->willReturn( [ 'fake data' ] );
		TestingAccessWrapper::newFromClass( WikiExtensions::class )->instance = $service;
		$result = $this->doExtensionsApiRequest( [ 'do' => 'list' ], $user );
		$this->assertSame(
			[
				'wikiextensions' => [
					'status' => 'OK',
					'extensions' => [ 'fake data' ],
				],
			],
			$result
		);
	}

	public function testListIPAllowed() {
		$this->checkListAllowed( User::newFromName( '192.168.0.0', false ) );
	}

	public function testListPermission() {
		$this->checkListAllowed(
			$this->getTestUser( [ 'view-only' ] )->getUser()
		);
	}

	public function testViewNoUpdate() {
		$result = $this->doExtensionsApiRequest(
			[ 'do' => 'update', 'extension' => 'irrelevant' ],
			$this->getTestUser( [ 'view-only' ] )->getUser()
		);
		$this->assertSame(
			[
				'wikiextensions' => [ 'status' => 'FAIL' ],
			],
			$result
		);
	}

	private function runMockedUpdate( User $user, Status $status ) {
		$service = $this->createNoOpMock(
			WikiExtensions::class,
			[ 'updateExtension', 'getExtension' ]
		);
		$service->expects( $this->once() )
			->method( 'updateExtension' )
			->with( 'MyExtensionName' )
			->willReturn( $status );
		$service->expects( $this->once() )
			->method( 'getExtension' )
			->With( 'MyExtensionName' )
			->willReturn( [ 'fake data' ] );
		TestingAccessWrapper::newFromClass( WikiExtensions::class )->instance = $service;
		return $this->doExtensionsApiRequest(
			[ 'do' => 'update', 'extension' => 'MyExtensionName' ],
			$user
		);
	}

	public function checkUpdateAllowed( User $user ) {
		// Success
		$result = $this->runMockedUpdate(
			$user,
			Status::newGood()
		);
		$this->assertSame(
			[
				'wikiextensions' => [
					'status' => 'OK',
					'extension' => [ 'fake data' ]
				],
			],
			$result
		);
		// Failure
		$result = $this->runMockedUpdate(
			$user,
			Status::newFatal( 'failure-key' ),
		);
		$this->assertSame(
			[
				'wikiextensions' => [
					'status' => 'failure-key',
					'extension' => [ 'fake data' ]
				],
			],
			$result
		);
	}

	private function testUpdateIPAllowed() {
		$this->checkUpdateAllowed( User::newFromName( '192.168.0.0', false ) );
	}

	private function testUpdatePermission() {
		$this->checkUpdateAllowed( $this->getTestSysop()->getUser() );
	}

}
