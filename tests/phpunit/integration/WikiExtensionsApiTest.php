<?php

namespace MediaWiki\Extension\WikiExtensions\Tests\Integration;

use ApiTestCase;
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

	public function setUp(): void {
		parent::setUp();

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
