<?php

/**
 * The most basic of tests, with no caching at all, so all translations are memory resident.
 */

// TODO: Remove these once we can get them in composer's autoloader
require_once (__DIR__ . "/../src/TranslatorInterface.php");
require_once (__DIR__ . "/../src/TranslationService.php");
require_once (__DIR__ . "/../src/SimpleCacheTranslator.php");

require_once (__DIR__ . "/../tools/i18n-app.inc.php"); // keep this

use QCubed\I18n\TranslationService as TService;

class SCTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$translator = new \QCubed\I18n\SimpleCacheTranslator();

		$translator->bindDomain('dom1', __DIR__ . "/domain1");
		$translator->bindDomain('dom2', __DIR__ . "/domain2");
		$translator->setDefaultDomain('dom2');
		TService::instance()->setTranslator($translator);
		TService::instance()->setLanguage('es');
	}

    /**
     * Tests multiline msgid
     */

    public function testSetLanguage()
    {
        try {
			TService::instance()->setLanguage('ru');
			TService::instance()->setLanguage('es');
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testBasicTranslation() {
		$str = \_t("Required");
		$this->assertEquals("Obligatorio", $str);
	}

	public function testPlural() {
		$str = \_tp("<b>Results:</b> 1 %s found.", "<b>Results:</b> %s %s found.", 1);
		$this->assertEquals("<b>Resultados:</b> Hay 1 %s.", $str);

		$str =\ _tp("<b>Results:</b> 1 %s found.", "<b>Results:</b> %s %s found.", 2);
		$this->assertEquals("<b>Resultados:</b> Hay %s %s.", $str);
	}

	/**
	 * The programmer should be able to embed newlines in translated text, and the translator
	 * should see these as escaped \n in the translation
	 */
	public function testMultiline() {
		$str = \_t("Line 1\nLine 2");
		$this->assertEquals("Línea 1\nLínea 2", $str);
	}

	public function testDomainAndContext() {
		$str = \_t("Welcome", "dom1", "Welcome panel");
		$this->assertEquals("Bienvenidos", $str);

		$str = \_t("Welcome", "dom1", "Howdy");
		$this->assertEquals("Hola", $str);
	}
}
