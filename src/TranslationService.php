<?php
/**
 * 	Part of the QCubed I18n framework. Designed to operate standalone without the framework as well.
 *
 * MIT Licensed
 *
 *    Copyright (c) 2017 Shannon Pekary
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions
 *    are met:
 *    1. Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *    3. Neither the name of copyright holders nor the names of its
 *       contributors may be used to endorse or promote products derived
 *       from this software without specific prior written permission.
 *
 *    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 *    ''AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
 *    TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *    PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL COPYRIGHT HOLDERS OR CONTRIBUTORS
 *    BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 *    CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace QCubed\I18n;

/**
 * Class Translator
 *
 * Works together with a translator interface to implement I18N translation files.
 *
 * This is its own separate singleton file because translation is core to the entire framework, but this allows
 * the most independence from the rest of the framework. Implementing a translator is optional.
 *
 * See the iq8n.inc.php file for shortcuts that encapsulate the whole process of using the service object singleton.
 *
 * Usage:
 * In your startup code:
 * TranslationService::instance()->setTranslator(new MyTranslator());
 * TranslationService::instance()->setLanguage ('en', 'us);
 *
 * To translate (See i18n-app.inc.php)
 * $str = _t("A message to translate");
 *
 * @package QCubed\I18n
 *
 */
class TranslationService {
	/**
	 * @var TranslationService
	 * The singleton instance of the active QI18n object (which contains translation strings), if any.
	 * Must be defined during application startup if needed and implement the TranslatorInterface.
	 */
	protected static $instance;
	/** @var  TranslatorInterface */
	protected $translator;

	/**
	 * @return TranslationService
	 */
	public static function instance() {
		if (!static::$instance) {
			static::$instance = new TranslationService();
		}
		return static::$instance;
	}

	/**
	 * Basic translate, translating the given msg to a localized message. Uses the internal translator if one is defined.
	 * Otherwise, just returns the given msgid verbatim.
	 *
	 * @param string $strMsgId			The home language version of the string. If no translation file is found, it will return this string (or perhaps throw an exception. This is configurable).
	 * @param string|null $strDomain	The domain. Libraries should specify their PACKAGIST package name. Tells the translator to look in a specific place.
	 * @param string|null $strContext	Rarely used, but a way to handle the same msgId differently based on a context identifier. This context will correspond
	 * 									to a context in the PO file.
	 * @return string					The translated string, or the msgId if no translation is found.
	 */
	public function translate($strMsgId, $strDomain = null, $strContext = null) {
		if ($this->translator) {
			return $this->translator->translate($strMsgId, static::cleanDomain($strDomain), $strContext);
		} else {
			return $strMsgId;
		}
	}

	/**
	 * Plural translator. In English, this will return the first string if
	 * $strMsgId is 1, and the plural in all other cases. For multi-language support, different languages
	 * can provide one, or more than one translation string base in the $intNum given. That is all handled in the translation
	 * file, and by the .PO plural translation format.
	 *
	 * Be aware that for English this means that 0 or -1 will return the plural form, which is usually what is wanted. If you
	 * want something different, you will need to programmatically change $intNum before sending it here. However, realize that
	 * your translation file will need to be aware of this too. You can use the $strContext variable to help manage special
	 * situations.
	 *
	 * @param string $strMsgId				The home language version of the singular string.
	 * @param string $strMsgId_plural		The home language version of the plural string. Only two strings allowed for home language.
	 * @param integer $intNum				The number to use to figure out which form to use.
	 * @param string|null $strDomain	The domain. Libraries should specify their PACKAGIST package name. Tells the translator to look in a specific place.
	 * @param string|null $strContext	Rarely used, but a way to handle the same msgId differently based on a context identifier. This context will correspond
	 * 									to a context in the PO file.
	 * @return string					The translated string, or the msgId if no translation is found.
	 */
	public function translatePlural($strMsgId, $strMsgId_plural, $intNum, $strDomain = null, $strContext = null)
	{
		if ($this->translator) {
			return $this->translator->translatePlural(
				$strMsgId,
				$strMsgId_plural,
				$intNum,
				static::cleanDomain($strDomain),
				$strContext);
		} else {
			if ($intNum == 1) {
				return $strMsgId;
			} else {
				// TODO: Have some way to implement a home language that is not just two items.
				if (is_array($strMsgId_plural)) {
					return $strMsgId_plural[0];	// default, because we don't know what to do
				} else {
					return $strMsgId_plural;
				}
			}
		}
	}

	/**
	 * Set the translator for the service to the given object.
	 * @param TranslatorInterface $translator
	 */
	public function setTranslator (TranslatorInterface $translator)
	{
		$this->translator = $translator;
	}

	/**
	 * @return TranslatorInterface
	 */
	public function translator ()
	{
		return $this->translator;
	}

	/**
	 * Set the language and country codes for future translations to use. Strings themselves are specific to the translator.
	 *
	 * @param string $strLanguage
	 * @param string|null $strCountry
	 * @return $this
	 */
	public function setLanguage ($strLanguage, $strCountry = null)
	{
		$this->translator->setLanguage($strLanguage, $strCountry);
		return $this;
	}

	/**
	 * So that you can use a PACKAGIST package name as a domain, we clean it up so that this string doesn't look like
	 * a potential windows directory.
	 *
	 * @param string|null $strDomain
	 * @return string|null
	 */
	public static function cleanDomain ($strDomain) {
		if ($strDomain) {
			return str_replace(['\\', '/'], '.', $strDomain);
		}
		else {
			return $strDomain;
		}
	}

	/**
	 * @param string $strDomain
	 * @param string $strDirectory
	 * @param string $strCharset
	 * @return $this
	 */
	public function bindDomain ($strDomain, $strDirectory, $strCharset = 'UTF-8')
	{
		$this->translator->bindDomain($strDomain, $strDirectory, $strCharset);
		return $this;
	}

	/**
	 * @param string $strDomain
	 * @param string $strDirectory
	 * @param string $strCharset
	 * @return $this
	 */
	public function setDefaultDomain ($strDomain)
	{
		$this->translator->setDefaultDomain($strDomain);
		return $this;
	}

}