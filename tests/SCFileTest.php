<?php


/**
 * The same as the basic test, but with file processing.
 */

require_once ("SCTest.php");

use QCubed\I18n\TranslationService as TService;

class SCFileTest extends SCTest
{
	/** @var  string */
	protected $strTestPath;
	/** @var  \QCubed\I18n\SimpleCacheTranslator */
	protected $translator;

	public function setUp()
	{
		$this->translator = new \QCubed\I18n\SimpleCacheTranslator();

		$this->strTestPath = realpath(__DIR__ . "/../build") . "/tests";
		mkdir($this->strTestPath);

		$this->translator->bindDomain('dom1', __DIR__ . "/domain1")
			->bindDomain('dom2', __DIR__ . "/domain2")
			->setDefaultDomain('dom2')
			->setTempDir($this->strTestPath);
		TService::instance()->setTranslator($this->translator);
		TService::instance()->setLanguage('es');
		TService::instance()->setLanguage('ru');
		$this->translator->clearCache();
		TService::instance()->setLanguage('es'); // reload from php files

		copy(__DIR__ . '/domain1/es.po', __DIR__ . '/domain1/es.saved.po');

	}


	public function tearDown()
	{
		parent::tearDown();

		if (file_exists($this->strTestPath)) {
			$this->recursiveDelete($this->strTestPath, true);
		}

		rename(__DIR__ . '/domain1/es.saved.po', __DIR__ . '/domain1/es.po');

	}

	protected function recursiveDelete($dirPath, $deleteParent = true){
		if ($dirPath && $dirPath != '/') { // safety
			foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
				$path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
			}
			if($deleteParent) rmdir($dirPath);
		}
	}

	public function testFileExistance() {
		$this->assertFileExists($this->strTestPath . '/ru/dom2.php');
		$this->assertFileExists($this->strTestPath . '/es/dom1.php');
		$this->assertFileExists($this->strTestPath . '/es/dom2.php');
	}

	/**
	 * This doesn't really test that we are using the files, but as long as some parts of the code are working, it
	 * will exercise the part of the code that reads the PHP file.
	 */
	public function testFileUse() {
		$str = _t("item");
		$this->assertEquals('artículo', $str);
	}

	/**
	 * Here we make sure we are using the .php version when it is newer than the .po file, but that
	 * we are recreating the php file when the .po file is newer
	 */
	public function testTouch() {
		copy(__DIR__ . '/touchTest/dom1.php', $this->strTestPath . '/es/dom1.php');
		clearstatcache(true);
		sleep(1); // prevent same mod date as other files
		touch($this->strTestPath . '/es/dom1.php');
		TService::instance()->setLanguage('ru');
		TService::instance()->setLanguage('es'); // reload from php files

		$str = _t("Yes", "dom1");
		$this->assertEquals('No', $str);	// using our badly mangled version of the php file

		clearstatcache(true);
		sleep(1); // prevent same mod date as other files
		touch(__DIR__ . '/domain1/es.po');
		TService::instance()->setLanguage('ru');
		TService::instance()->setLanguage('es'); // reload from po files

		$str = _t("Yes", "dom1");
		$this->assertEquals('Si', $str);	// using the touched po file, since its newer


		clearstatcache(true);
		copy(__DIR__ . '/touchTest/es.po', __DIR__ . '/domain1/es.po');
		sleep(1); // prevent same mod date as other files
		touch($this->strTestPath . '/es/dom1.php');
		TService::instance()->setLanguage('ru');
		TService::instance()->setLanguage('es'); // reload from php files

		$str = _t("Yes", "dom1");
		$this->assertEquals('Si', $str);	// using the php file instead of our sucky one, and the original po file was in fact processed and saved

	}
}