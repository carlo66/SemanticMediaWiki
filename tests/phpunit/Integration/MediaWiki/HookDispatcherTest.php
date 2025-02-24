<?php

namespace SMW\Tests\Integration;

use RuntimeException;
use SMW\MediaWiki\HookDispatcher;
use SMW\Tests\TestEnvironment;

/**
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 3.2
 *
 * @author mwjames
 */
class HookDispatcherTest extends \PHPUnit_Framework_TestCase {

	private $mwHooksHandler;

	protected function setUp() {
		parent::setUp();

		$this->testEnvironment = new TestEnvironment();

		$this->mwHooksHandler = $this->testEnvironment->getUtilityFactory()->newMwHooksHandler();
		$this->mwHooksHandler->deregisterListedHooks();
	}

	protected function tearDown() {
		$this->mwHooksHandler->restoreListedHooks();
		$this->testEnvironment->tearDown();
		parent::tearDown();
	}

	public function testOnGetPreferences() {

		$preferences = [];

		$hookDispatcher = new HookDispatcher();

		$user = $this->getMockBuilder( '\User' )
			->disableOriginalConstructor()
			->getMock();

		$this->mwHooksHandler->register( 'SMW::GetPreferences', function( $user, &$preferences ) {
			$preferences = [ 'Foo' ];
		} );

		$hookDispatcher->onGetPreferences( $user, $preferences );

		$this->assertEquals(
			[ 'Foo' ],
			$preferences
		);
	}

	public function testOnBeforeMagicWordsFinder() {

		$magicWords = [];

		$hookDispatcher = new HookDispatcher();

		$this->mwHooksHandler->register( 'SMW::Parser::BeforeMagicWordsFinder', function( &$magicWords ) {
			$magicWords = [ 'Foo' ];
		} );

		$hookDispatcher->onBeforeMagicWordsFinder( $magicWords );

		$this->assertEquals(
			[ 'Foo' ],
			$magicWords
		);
	}

	public function testOnAfterLinksProcessingComplete() {

		$text = '';

		$hookDispatcher = new HookDispatcher();

		$annotationProcessor = $this->getMockBuilder( '\SMW\Parser\AnnotationProcessor' )
			->disableOriginalConstructor()
			->getMock();

		$this->mwHooksHandler->register( 'SMW::Parser::AfterLinksProcessingComplete', function( &$text, $annotationProcessor ) {
			$text = 'Foo';
		} );

		$hookDispatcher->onAfterLinksProcessingComplete( $text, $annotationProcessor );

		$this->assertEquals(
			'Foo',
			$text
		);
	}

	public function testOnParserAfterTidyPropertyAnnotationComplete() {

		$hookDispatcher = new HookDispatcher();

		$propertyAnnotator = $this->getMockBuilder( '\SMW\Property\Annotator' )
			->disableOriginalConstructor()
			->getMock();

		$propertyAnnotator->expects( $this->once() )
			->method( 'addAnnotation' );

		$parserOutput = $this->getMockBuilder( '\ParserOutput' )
			->disableOriginalConstructor()
			->getMock();

		$this->mwHooksHandler->register( 'SMW::Parser::ParserAfterTidyPropertyAnnotationComplete', function( $propertyAnnotator, $parserOutput ) {
			$propertyAnnotator->addAnnotation();
		} );

		$hookDispatcher->onParserAfterTidyPropertyAnnotationComplete( $propertyAnnotator, $parserOutput );
	}

	public function testOnIsApprovedRevision() {

		$hookDispatcher = new HookDispatcher();

		$title = $this->getMockBuilder( '\Title' )
			->disableOriginalConstructor()
			->getMock();

		$this->mwHooksHandler->register( 'SMW::RevisionGuard::IsApprovedRevision', function( $title, $latestRevID ) {
			return $latestRevID == 9999 ? false : true ;
		} );

		$this->assertFalse(
			$hookDispatcher->onIsApprovedRevision( $title, 9999 )
		);
	}

	public function testOnChangeRevisionID() {

		$hookDispatcher = new HookDispatcher();

		$title = $this->getMockBuilder( '\Title' )
			->disableOriginalConstructor()
			->getMock();

		$latestRevID = 9999;

		$this->mwHooksHandler->register( 'SMW::RevisionGuard::ChangeRevisionID', function( $title, &$latestRevID ) {
			$latestRevID = 1001;
		} );

		$hookDispatcher->onChangeRevisionID( $title, $latestRevID );

		$this->assertEquals(
			1001,
			$latestRevID
		);
	}

	public function testOnChangeFile() {

		$hookDispatcher = new HookDispatcher();

		$title = $this->getMockBuilder( '\Title' )
			->disableOriginalConstructor()
			->getMock();

		$file = $this->getMockBuilder( '\File' )
			->disableOriginalConstructor()
			->getMock();

		$anotherFile = $this->getMockBuilder( '\File' )
			->disableOriginalConstructor()
			->getMock();

		$anotherFile->extraProperty = 'Foo';

		$this->assertNotEquals(
			$anotherFile,
			$file
		);

		$this->mwHooksHandler->register( 'SMW::RevisionGuard::ChangeFile', function( $title, &$file ) use ( $anotherFile ) {
			$file = $anotherFile;
		} );

		$hookDispatcher->onChangeFile( $title, $file );

		$this->assertEquals(
			$anotherFile,
			$file
		);
	}

	public function testConfirmAllOnMethodsWereCalled() {

		// Expected class methods to be tested
		$classMethods = get_class_methods( HookDispatcher::class );

		// Match all "testOn" to the expected set of methods
		$testMethods = preg_grep('/^testOn/', get_class_methods( $this ) );

		$testMethods = array_flip(
			str_replace( 'testOn', 'on', $testMethods )
		);

		foreach ( $classMethods as $name ) {
			$this->assertArrayHasKey( $name, $testMethods );
		}
	}

}
