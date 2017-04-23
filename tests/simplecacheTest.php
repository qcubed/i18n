<?php


// TODO: Remove these once we can get them in composer's autoloader
require_once (__DIR__ . "/../src/TranslatorInterface.php");
require_once (__DIR__ . "/../src/TranslationService.php");
require_once (__DIR__ . "/../src/SimpleCacheTranslator.php");

require_once (__DIR__ . "/../tools/i18n.inc.php"); // keep this

use Sepia\PoParser\Parser;
use Sepia\PoParser\Handler\FileHandler;
use Sepia\PoParser\Handler\StringHandler;

use QCubed\I18n\TranslationService as TService;

class SimpleCacheTest extends \PHPUnit_Framework_TestCase
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
		$str = _t("Required");
		$this->assertEquals("Obligatorio", $str);
	}

	public function testPlural() {
		$str = _tp("<b>Results:</b> 1 %s found.", "<b>Results:</b> %s %s found.", 2);
		$this->assertEquals("<b>Resultados:</b> Hay %s %s.", $str);
	}

}
