<?php


/**
 * The same as the basic test, but using a mock cache to cover some of the caching code. In particular, covers
 * the concatenation of the key when the particular cache we are using requires key truncation.
 */

require_once ("SCTest.php");
require_once ("MockCache.php");

use QCubed\I18n\TranslationService as TService;

class SCMockCacheTest extends SCTest
{
	/** @var  \QCubed\I18n\SimpleCacheTranslator */
	protected $translator;

	public function setUp()
	{
		$this->translator = new \QCubed\I18n\SimpleCacheTranslator();
		$cache = new MockCache();

		$this->translator->bindDomain('dom1', __DIR__ . "/domain1")
			->bindDomain('dom2', __DIR__ . "/domain2")
			->setDefaultDomain('dom2')
			->setCache($cache);

		TService::instance()->setTranslator($this->translator); // reload from php files
		TService::instance()->setLanguage('es'); // reload from php files
	}

	// All other tests are inherited
}