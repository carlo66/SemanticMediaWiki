<?php

namespace SMW\Tests\Integration\Maintenance;

use SMW\Tests\DatabaseTestCase;
use SMW\Tests\TestEnvironment;

/**
 * @group semantic-mediawiki
 * @group medium
 *
 * @license GNU GPL v2+
 * @since 3.2
 *
 * @author mwjames
 */
class RunImportTest extends DatabaseTestCase {

	protected $destroyDatabaseTablesAfterRun = true;
	private $runnerFactory;
	private $spyMessageReporter;

	protected function setUp() {
		parent::setUp();

		$this->testEnvironment->addConfiguration( 'smwgImportReqVersion', 1 );
		$this->testEnvironment->addConfiguration( 'smwgEnabledFulltextSearch', false );

		$this->runnerFactory  = $this->testEnvironment::getUtilityFactory()->newRunnerFactory();
		$this->spyMessageReporter = $this->testEnvironment::getUtilityFactory()->newSpyMessageReporter();
	}

	protected function tearDown() {
		parent::tearDown();
	}

	public function testRun() {

		$maintenanceRunner = $this->runnerFactory->newMaintenanceRunner(
			'SMW\Maintenance\RunImport'
		);

		$maintenanceRunner->setMessageReporter(
			$this->spyMessageReporter
		);

		$this->assertTrue(
			$maintenanceRunner->run()
		);

		$this->assertContains(
			'Importing from smw.vocab.json',
			$this->spyMessageReporter->getMessagesAsString()
		);
	}

}
