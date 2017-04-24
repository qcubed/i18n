<?php
/**
 * 	  Part of the QCubed I18n framework. Designed to operate standalone without the framework as well.
 *
 *    MIT Licensed
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
 * Class GettextTranslator
 *
 * This is a Gettext translator adaptor. The Gettext translator uses the PHP gettext function, which relies
 * on the GNU gettext built in plugin. You need to enable it in PHP and your OS.
 *
 * @see http://php.net/manual/en/book.gettext.php
 *
 *
 * This has not yet been tested. If you are seeing this and want this, please test and correct.
 *
 * There is also a PHP only version of this that does not rely on the built-in gettext function, and that will read
 * .mo files directly.
 *
 * @package QCubed\I18n
 */
class GettextTranslator implements TranslatorInterface {
	const LC_CTYPE = 0;
	const LC_NUMERIC = 1;
	const LC_TIME = 2;
	const LC_COLLATE = 3;
	const LC_MONETARY = 4;
	const LC_MESSAGES = 5;
	const LC_ALL = 6;

	protected $charset = null;

	/**
	 * Translate the given message.
	 *
	 * @param string $strMsgId
	 * @param string|null $strDomain	Domain string (Optional)
	 * @param string|null $strContext	Context string (Optional)
	 * @return string
	 */
	public function translate($strMsgId, $strDomain, $strContext)
	{
		if ($strContext) {
			$strMsgId = "{$strContext}\004{$strMsgId}";
		}

		if ($strDomain) {
			return dcgettext($strDomain, $strMsgId, LC_MESSAGES);
		}
		else {
			return gettext($strMsgId);
		}
	}

	/**
	 * Translate the given plural message.
	 *
	 * @param string $strMsgId		Singular message id
	 * @param string $strMsgId_plural	Plural message id
	 * @param integer $intNum		Number to use to determine which string to return
	 * @param string $strDomain		Domain string (Optional)
	 * @param string $strContext	Context string (Optional)
	 * @return string
	 */
	public function translatePlural($strMsgId, $strMsgId_plural, $intNum, $strDomain, $strContext)
	{
		if ($strContext) {
			$strMsgId = "{$strContext}\004{$strMsgId}";
		}

		if ($strDomain) {
			return dngettext($strDomain, $strMsgId, $strMsgId_plural, $intNum);
		}
		else {
			return ngettext($strMsgId, $strMsgId_plural, $intNum);
		}
	}

	/**
	 * Binds the given domain to the directory, and optionally allows the charset to be defined.
	 *
	 * The charset is kind of finnicky, and the directory structure. See the comments for the PHP doc for gettext for details.
	 * You will likely need to copy all the .po and .mo files to a new directory structure, that looks like:
	 * /lang_COUNTRY.charset/LC_MESSAGES/domain.mo (or .po)
	 *
	 * Here, a charset should be hyphenated, like UTF-8. The hyphenated version is used sometimes,
	 * and hyphens are removed in others.
	 *
	 * @param string $strDomain
	 * @param string $strDirectory
	 * @param string|null $strCharset
	 * @return $this
	 */
	public function bindDomain ($strDomain, $strDirectory, $strCharset = 'UTF-8')
	{
		$strDomain = TranslationService::cleanDomain($strDomain);
		assert(file_exists($strDirectory), "i18n directory does not exist");
		bindtextdomain($strDomain, $strDirectory);

		if ($strCharset) {
			bind_textdomain_codeset($strDomain, $strCharset);
			$this->charset = $strCharset;
		}
		return $this;
	}

	/**
	 * Set the default domain.
	 *
	 * @param $strDomain
	 * @return $this
	 */
	public function setDefaultDomain($strDomain)
	{
		$strDomain = TranslationService::cleanDomain($strDomain);
		textdomain($strDomain);
		return $this;
	}


	/**
	 * Set the language. If doing this with a builder pattern, do this last.
	 *
	 * @param string $strLanguage
	 * @param string|null $strCountry
	 * @return void
	 */
	public function setLanguage ($strLanguage, $strCountry = null)
	{
		$locale = $strLanguage;

		if ($strCountry) {
			$locale .= '_' . $strCountry;
		}

		// There is some confusion on the net about whether the charset needs to be cleaned.
		// I suspect it has something to do with the directory structure, but I have not tested this.
		if ($this->charset) {
			$locale .= '.' . $this->charset;
		}
		putenv("LANG=" . $locale); //ight need LANGUAGE setting too.
		setlocale(LC_ALL, $locale);
	}
}