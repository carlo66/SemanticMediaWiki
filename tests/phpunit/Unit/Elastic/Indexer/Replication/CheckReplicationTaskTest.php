<?php

namespace SMW\Tests\Elastic\Indexer\Replication;

use SMW\Elastic\Indexer\Replication\CheckReplicationTask;
use SMW\Elastic\Indexer\Replication\ReplicationError;
use SMW\Tests\PHPUnitCompat;
use SMW\DIWikiPage;
use SMW\DIProperty;
use SMWDITime as DITime;

/**
 * @covers \SMW\Elastic\Indexer\Replication\CheckReplicationTask
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class CheckReplicationTaskTest extends \PHPUnit_Framework_TestCase {

	use PHPUnitCompat;

	private $store;
	private $documentReplicationExaminer;
	private $entityCache;
	private $elasticClient;
	private $idTable;

	protected function setUp() {

		$this->idTable = $this->getMockBuilder( '\SMW\SQLStore\EntityStore\EntityIdManager' )
			->disableOriginalConstructor()
			->getMock();

		$this->store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->setMethods( [ 'getObjectIds', 'getConnection' ] )
			->getMockForAbstractClass();

		$this->store->expects( $this->any() )
			->method( 'getObjectIds' )
			->will( $this->returnValue( $this->idTable ) );

		$this->documentReplicationExaminer = $this->getMockBuilder( '\SMW\Elastic\Indexer\Replication\DocumentReplicationExaminer' )
			->disableOriginalConstructor()
			->getMock();

		$this->elasticClient = $this->getMockBuilder( '\SMW\Elastic\Connection\DummyClient' )
			->disableOriginalConstructor()
			->getMock();

		$this->elasticClient->expects( $this->any() )
			->method( 'ping' )
			->will( $this->returnValue( true ) );

		$this->entityCache = $this->getMockBuilder( '\SMW\EntityCache' )
			->disableOriginalConstructor()
			->getMock();
	}

	public function testCanConstruct() {

		$this->assertInstanceOf(
			CheckReplicationTask::class,
			new CheckReplicationTask( $this->store, $this->documentReplicationExaminer, $this->entityCache )
		);
	}

	public function testCheckReplication_NotExists() {

		$error = new ReplicationError(
			ReplicationError::TYPE_MODIFICATION_DATE_MISSING,
			[ 'id' => 42 ]
		);

		$this->elasticClient->expects( $this->any() )
			->method( 'hasMaintenanceLock' )
			->will( $this->returnValue( false ) );

		$this->documentReplicationExaminer->expects( $this->once() )
			->method( 'check' )
			->will( $this->returnValue( $error ) );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->documentReplicationExaminer,
			$this->entityCache
		);

		$html = $instance->checkReplication( DIWikiPage::newFromText( 'Foo' ), [] );

		$this->assertContains(
			'smw-highlighter',
			$html
		);
	}

	public function testCheckReplication_NoConnection() {

		$elasticClient = $this->getMockBuilder( '\SMW\Elastic\Connection\DummyClient' )
			->disableOriginalConstructor()
			->getMock();

		$elasticClient->expects( $this->any() )
			->method( 'ping' )
			->will( $this->returnValue( false ) );

		$elasticClient->expects( $this->never() )
			->method( 'hasMaintenanceLock' );

		$this->documentReplicationExaminer->expects( $this->never() )
			->method( 'check' );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->documentReplicationExaminer,
			$this->entityCache
		);

		$subject = DIWikiPage::newFromText( 'Foo' );

		$html = $instance->checkReplication( $subject );

		$this->assertContains(
			'smw-highlighter',
			$html
		);
	}

	public function testCheckReplication_ModificationDate() {

		$error = new ReplicationError(
			ReplicationError::TYPE_MODIFICATION_DATE_DIFF,
			[
				'id' => 42,
				'time_es' => DITime::newFromTimestamp( 1272508900 )->asDateTime()->format( 'Y-m-d H:i:s' ),
				'time_store' =>DITime::newFromTimestamp( 1272508903 )->asDateTime()->format( 'Y-m-d H:i:s' )
			]
		);

		$config = $this->getMockBuilder( '\SMW\Options' )
			->disableOriginalConstructor()
			->getMock();

		$config->expects( $this->any() )
			->method( 'dotGet' )
			->with(	$this->equalTo( 'indexer.experimental.file.ingest' ) )
			->will( $this->returnValue( true ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'hasMaintenanceLock' )
			->will( $this->returnValue( false ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'getConfig' )
			->will( $this->returnValue( $config ) );

		$this->documentReplicationExaminer->expects( $this->once() )
			->method( 'check' )
			->will( $this->returnValue( $error ) );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->documentReplicationExaminer,
			$this->entityCache
		);

		$subject = DIWikiPage::newFromText( 'Foo' );

		$html = $instance->checkReplication( $subject );

		$this->assertContains(
			'smw-highlighter',
			$html
		);

		$this->assertContains(
			'2010-04-29 02:41:43',
			$html
		);
	}

	public function testCheckReplication_AssociateRev() {

		$error = new ReplicationError(
			ReplicationError::TYPE_ASSOCIATED_REVISION_DIFF,
			[
				'id' => 42,
				'rev_es' => 42,
				'rev_store' => 99999
			]
		);

		$config = $this->getMockBuilder( '\SMW\Options' )
			->disableOriginalConstructor()
			->getMock();

		$config->expects( $this->any() )
			->method( 'dotGet' )
			->with(	$this->equalTo( 'indexer.experimental.file.ingest' ) )
			->will( $this->returnValue( true ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'hasMaintenanceLock' )
			->will( $this->returnValue( false ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'getConfig' )
			->will( $this->returnValue( $config ) );

		$this->documentReplicationExaminer->expects( $this->once() )
			->method( 'check' )
			->will( $this->returnValue( $error ) );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->documentReplicationExaminer,
			$this->entityCache
		);

		$subject = DIWikiPage::newFromText( 'Foo' );

		$html = $instance->checkReplication( $subject, [] );

		$this->assertContains(
			'smw-highlighter',
			$html
		);

		$this->assertContains(
			'99999',
			$html
		);
	}

	public function testMakeCacheKey() {

		$subject = DIWikiPage::newFromText( 'Foo', NS_MAIN );

		$this->assertSame(
			CheckReplicationTask::makeCacheKey( $subject->getHash() ),
			CheckReplicationTask::makeCacheKey( $subject )
		);
	}

	public function testGetReplicationFailures() {

		$this->entityCache->expects( $this->once() )
			->method( 'fetch' )
			->with( $this->stringContains( 'smw:entity:1ce32bc49b4f8bc82a53098238ded208' ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->documentReplicationExaminer,
			$this->entityCache
		);

		$instance->getReplicationFailures();
	}

	public function testDeleteReplicationTrail_OnTitle() {

		$subject = DIWikiPage::newFromText( 'Foo', NS_MAIN );

		$this->entityCache->expects( $this->once() )
			->method( 'deleteSub' )
			->with(
				$this->stringContains( 'smw:entity:1ce32bc49b4f8bc82a53098238ded208' ),
				$this->stringContains( 'smw:entity:b94628b92d22cd315ccf7abb5b1df3c0' ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->documentReplicationExaminer,
			$this->entityCache
		);

		$instance->deleteReplicationTrail( $subject->getTitle() );
	}

	public function testDeleteReplicationTrail_OnSubject() {

		$subject = DIWikiPage::newFromText( 'Foo', NS_MAIN );

		$this->entityCache->expects( $this->once() )
			->method( 'deleteSub' )
			->with(
				$this->stringContains( 'smw:entity:1ce32bc49b4f8bc82a53098238ded208' ),
				$this->stringContains( 'smw:entity:b94628b92d22cd315ccf7abb5b1df3c0' ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->documentReplicationExaminer,
			$this->entityCache
		);

		$instance->deleteReplicationTrail( $subject );
	}

}
