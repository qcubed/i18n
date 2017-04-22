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

use Psr\SimpleCache;
use Sepia\PoParser\Parser;

/**
 * Class SimpleCacheTranslator
 *
 * This is a translator adaptor based on the PSR-16 Simple Cache Interface.
 * You will need to provide a cache service to make this work.
 *
 * @see http://www.php-fig.org/psr/psr-16/
 *
 * There are two modes of operation: one where it assumes the cache is in volatile memory and will need to potentially
 * be loaded with information, and one where the cache is really a key/value disk store, and we never try to load
 * a cache, but rather we just look in the database and assume its always there.
 *
 *
 * This has not yet been tested. If you are seeing this and want this, please test and correct.
 *
 * @package QCubed\I18n
 */
class SimpleCacheTranslator implements TranslatorInterface {

	/** @var  SimpleCache */
	protected $cache;
	/** @var  string[] */
	protected $domains;
	/** @var  string */
	protected $strDefaultDomain;
	/** @var  boolean */
	protected $blnMsgIdRequiresCleaning;
	/** @var  string */
	protected $strLocale;
	/** @var  string[] */
	protected $translations;	// Local memory cache if a cache is not provided
	/** @var  We can put pre-built translations in the temp dir if you provide one */
	protected $strTempDir;

	/**
	 * SimpleCacheTranslator constructor.
	 * @param SimpleCache|null $cache		The cache to use. Can be specified later user setCache
	 * @param bool $blnRequiresCleaning		True if your cache cannot accept just any string as a key
	 */
	public function __construct(SimpleCache $cache = null, $blnRequiresCleaning = true)
	{
		if ($cache) {
			$this->setCache($cache, $blnRequiresCleaning);
		}
	}

	/**
	 * Set the cache.
	 *
	 * @param SimpleCache $cache			The cache to use
	 * @param bool $blnRequiresCleaning		True if your cache cannot accept just any string as a key. If true, the key
	 * 										will be cleaned to conform to the strict requirements of SimpleCache keys.
	 */
	public function setCache(SimpleCache $cache, $blnRequiresCleaning = true)
	{
		$this->cache = $cache;
		$this->blnMsgIdRequiresCleaning = $blnRequiresCleaning;
	}

	/**
	 * This function creates a single string that represents a key to use to search for the corresponding translation
	 * in the cache.
	 *
	 * At its basic form, it just concatenates all the pieces.
	 *
	 * However, SimpleCache only guarantees that certain characters can be used as a key, and that the key can only be
	 * 64-characters long. Some caches may support more, but its not guaranteed. Keys can only be
	 * A-Z, a-z, 0-9, _, and . However, many caches support any kind of key string with unlimited length.
	 *
	 * This is a public static to allow an external application to preprocess .po files and create corresponding php files,
	 * or pre-load a cache or database with values.
	 *
	 * Cleaning mangles the string so that it can be used as a key for any SimpleCache compliant cache.
	 *
	 * Strategy:
	 *    - Spaces are replaced with underscores
	 *  - periods are left alone
	 *  - all other characters are removed, but if this happens, the string is appended with an md5 hash to differentiate it from other strings
	 *  - If the string is longer than 64-characters, it will be truncated and appended with an md5 hash
	 *  - The string is appended with the domain or context if one is provided, before being mangled.
	 *
	 * @param string $strMsgId 				message id
	 * @param string|null $strDomain 		domain
	 * @param string|null $strContext
	 * @param string|null $strLocale
	 * @param integer|null $intNumOffset	if a plural message id, the offset. Offsets start at 1.
	 * @param bool $blnRequiresCleaning
	 * @return string
	 */
	public static function getKey($strMsgId, $strDomain = null, $strContext = null, $strLocale = null, $intNumOffset = null, $blnRequiresCleaning = false)
	{
		if ($strDomain) {
			$strMsgId .= '.' . $strDomain;
		}

		if ($strContext) {
			$strMsgId .= '.' . $strContext;
		}

		if ($strLocale) {
			$strLocale = str_replace('_', '.', $strLocale);
			$strMsgId .= '.' . $strLocale;
		}

		if ($intNumOffset && $intNumOffset > 1) {
			$strMsgId .= '.' . $intNumOffset;	// offsets of 1 are the default plural, and are not needed in the key, since its already a plural key
		}

		if ($blnRequiresCleaning) {
			$strNewMsgId = preg_replace(
				'/^[A-Z][a-z][0-9.][:space:]/u',
				'',
				$strMsgId,
				-1,
				$count
			);

			$strNewMsgId = preg_replace(
				'/[:space:]/u',
				'_',
				$strNewMsgId
			);
			assert ($strNewMsgId !== null);

			if ($count || strlen($strNewMsgId) > 64) {
				$strNewMsgId = substr($strNewMsgId, 0, 31);
				$strNewMsgId .= '.' . md5($strMsgId);
			}

			if ($strNewMsgId) {
				$strMsgId = $strNewMsgId;
			}
		}

		return $strMsgId;
	}

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
		$strNewMsgId = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null, $this->blnMsgIdRequiresCleaning);

		if ($this->cache) {
			return $this->cache->get($strNewMsgId, $strMsgId);
		}
		elseif ($this->translations) {
			return $this->translations[$strNewMsgId];
		}

		// default
		return $strMsgId;
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
		$offset = $this->pluralNumToOffset($intNum, $this->strLocale);

		if (!$offset) {
			$strNewMsgId = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null, $this->blnMsgIdRequiresCleaning);
		}
		else {
			$strNewMsgId = static::getKey($strMsgId_plural, $strDomain, $strContext, $this->strLocale, $offset, $this->blnMsgIdRequiresCleaning);
		}

		if ($this->cache) {
			return $this->cache->get($strNewMsgId, $strMsgId);
		}
		elseif ($this->translations) {
			return $this->translations[$strNewMsgId];
		}

		// Do the default if nothing is configured
		if ($intNum == 1) {
			return $strMsgId;
		}
		else {
			return $strMsgId_plural;
		}
	}

	/**
	 * This currently just does the default action, which works for languages that only have one plural form.
	 *
	 * If the given integer specifies a singular value, return 0. Otherwise, return the offset into the language file
	 * that the integer corresponds to. Offsets start at 1.
	 *
	 * TODO: multi-plurals
	 * To do this right is tricky. One way is to read the header from each po file to determine the right thing. A better
	 * way is to just know what to do for every locale. Buried in Symfony, someone did this. We should just copy their work, include
	 * the attribution, and then use it to do return the correct offset based on the give integer.
	 *
	 * @param $intNum
	 * @param $strLocale
	 * @return int
	 */
	protected function pluralNumToOffset($intNum, $strLocale) {
		if ($intNum == 1) {
			return 0;
		}
		else {
			return 1;
		}
	}

	/**
	 * Binds the given domain to the directory, and optionally allows the charset to be defined.
	 *
	 * In this version, we are going to assume that the domain is the same as a Packagist package name,
	 * and we are pointing to a directory full of .po files and/or .php array files. The standard Gettext directory
	 * structure is a little unwieldy (though it does make merging a little easier).
	 *
	 * Always bind your directories before setting the language, or it will not be able to find your language files.
	 *
	 * If you are using a cache that is pre-loaded, you don't need to do this.
	 *
	 * @param string $strDomain
	 * @param string $strDirectory
	 * @param string|null $strCharset
	 * @return void
	 */
	public function bindDomain ($strDomain, $strDirectory)
	{
		$strDomain = TranslationService::cleanDomain($strDomain);

		assert(file_exists($strDirectory), "i18n directory does not exist");
		$this->domains[$strDomain] = $strDirectory;

		// TODO: Should we support alternate charsets?
	}

	/**
	 * Set the default domain.
	 *
	 * @param $strDomain
	 * @return void
	 */
	public function setDefaultDomain($strDomain)
	{
		$this->strDefaultDomain = TranslationService::cleanDomain($strDomain);
	}

	/**
	 * Set language and option country code. Set to null to revert to the default language.
	 *
	 * @param string|null $strLanguage
	 * @param string|null $strCountry
	 */
	public function setLanguage ($strLanguage, $strCountry = null)
	{
		$locale = null;
		if ($strLanguage) {
			$locale = $strLanguage;
			if ($strCountry) {
				$locale .= '_' . $strCountry;
			}
		}
		if ($this->strLocale != $locale) {	// If user accidentally set same language twice, we do nothing
			$this->strLocale = $locale;
			$this->translations = null;
			$this->loadCaches();
		}
	}

	protected function loadCaches() {
		$strPoName = $this->strLocale . '.po';

		foreach ($this->domains as $strDomain=>$strDirectory) {
			$strPoFileName = $strDirectory . '/' . $strPoName;
			if (file_exists($strPoFileName)) {
				$poModTime = filemtime($strPoFileName);

				// Check if temp dir has more recent cached version of this file
				if ($this->strTempDir) {
					$strTempFileName = $this->getTempFileName($strDomain);
					$tempModTime = filemtime($strTempFileName);
					if ($tempModTime !== false && $tempModTime > $poModTime) {
						$this->loadTempFile($strTempFileName, $strDomain, $poModTime);
					}
					else {
						$this->loadPoFile($strPoFileName, $strDomain, $poModTime);
					}
				}

				// no temporary files, so we deal with po files directly
				else {
					if ($this->cache) {
						if (!$this->isCacheLoaded($strDomain, $poModTime)) {
							$this->loadPoFile($strPoFileName, $strDomain, $poModTime);
						}
					}
					else {
						$this->loadPoFile($strPoFileName, $strDomain, $poModTime);
					}
				}

			}
		}
	}

	protected function loadTempFile($strTempFileName, $strDomain, $poModTime) {
		if ($this->cache) {
			if (!$this->isCacheLoaded($strDomain, $poModTime)) {
				$a = $this->readTempFile($strDomain);
				$this->cache->setMultiple($a);
			}
		}
		else {
			$a = include($strTempFileName);

			if (!$this->translations) {
				$this->translations = $a;
			}
			else {
				$this->translations = array_merge_recursive($this->translations, $a);
			}
		}
	}

	protected function isCacheLoaded ($strDomain, $poModTime) {
		if ($this->cache) {
			$strKey = $this->strLocale . '.' . $strDomain;
			$oldModTime = $this->cache->get($strKey);
			if ($oldModTime && $oldModTime == $poModTime) {
				return true;
			}
		}
		return false;
	}

	protected function setEntry($strKey, $strMessage) {
		if ($this->cache) {
			$this->cache->set($strKey, $strMessage);
		}
		else {
			$this->translations[$strKey] = $strMessage;
		}
	}

	protected function loadPoFile($strPoFileName, $strDomain, $poModDate) {
		$entries = Parser::parseFile($strPoFileName)->getEntries();
		$data = [];

		foreach ($entries as $entry) {
			$strMsgId = $entry['msgid'];
			$strContext = null;
			if (isset($entry['msgctxt'])) {
				$strContext = $entry['msgctxt'];
			}
			if (isset($entry['msgid_plural'])) {
				assert(is_array($entry['msgstr']));
				$key = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null, $this->blnMsgIdRequiresCleaning);
				$data[$key] = $entry['msgstr'][0];

				for ($i = 1; $i <= count($entry['msgstr']); $i++) {
					$key = static::getKey($entry['msgid_plural'], $strDomain, $strContext, $this->strLocale, $i, $this->blnMsgIdRequiresCleaning);
					$data[$key] = $entry['msgstr'][$i];
				}
			}
			else {
				$key = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null, $this->blnMsgIdRequiresCleaning);
				$data[$key] = $entry['msgstr'];
			}
		}

		// Put the data in the cache
		if ($this->cache) {
			$this->cache->setMultiple($data);
			$strKey = $this->strLocale . '.' . $strDomain;
			$this->cache->set($strKey, $poModDate);	// notify that the data is up to date
		}
		else {
			if (!$this->translations) {
				$this->translations = $data;
			}
			else {
				$this->translations = array_merge($this->translations, $data);
			}
		}

		// Write the data to a PHP file so the next time we need to read it, we can get to it faster than parsing a PO file.
		if ($this->strTempDir) {
			$this->writeTempFile($data, $strDomain);
		}
	}

	protected function getTempFileName($strDomain) {
		return $this->strTempDir . '/' . $this->strLocale . '/' . $strDomain . '.php';
	}

	protected function writeTempFile($data, $strDomain) {
		// Stackexhchange lore suggests that json encode is the fastest way to do this using standard PHP calls,
		// though igbinary may be even faster, but we don't want to make that a dependency at this point.
		$strEncodedData = json_encode($data);
		file_put_contents($this->getTempFileName($strDomain), $strEncodedData, LOCK_EX);
	}

	protected function readTempFile($strDomain) {
		$data = file_get_contents($this->getTempFileName($strDomain));
		return json_decode($data, true);
	}

}