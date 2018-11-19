<?php
/**
 *      Part of the QCubed I18n framework. Designed to operate standalone without the framework as well.
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
 *
 * @see http://www.php-fig.org/psr/psr-16/
 *
 * This has a couple of layers of functionality. You actually don't need to provide a cache, but its better if you do.
 * If you do not provide a cache, it will directly read .PO language files. If you do provide a cache, it will store
 * translations in the cache and look them up as needed. Without a cache, you could get some file thrashing as every
 * time PHP starts up, the language files will be read.
 *
 * Will detect if an entry is removed from the cache, and will load the associated language file if that happens. So, if
 * your cache is running low on memory and starts swapping out entries, that could cause some thrashing too.
 *
 * If you provide a temporary directory, it will read and pre-process your .po files, saving them into your directory
 * in a format it can get to easily (JSON currently). That will speed up subsequent reads and help a bit with file thrashing.
 * Will detect if a .po file gets updated and process the file without you needing to reboot the server.
 *
 * Currently only supports UTF-8 .PO files.
 *
 * TODO: Implement a version using https://github.com/sevenval/SHMT This will be faster, as all accesses are memory mapped,
 * and no entries will be removed, so no file trashing problems!
 *
 * @package QCubed\I18n
 */
class SimpleCacheTranslator implements TranslatorInterface
{

    /** @var  SimpleCache\CacheInterface */
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
    protected $translations;    // Local memory cache if a cache is not provided
    /** @var  string  We can put pre-built translations in the temp dir if you provide one */
    protected $strTempDir;

    const INVALID_ENTRY = "***ui27^0u73"; // Random value that nobody would ever want to use, indicates we could not find this entry in a PO file.

    /**
     * SimpleCacheTranslator constructor.
     * @param SimpleCache\CacheInterface|null $cache The cache to use. Can be specified later user setCache
     * @param bool $blnRequiresCleaning True if your cache cannot accept just any string as a key
     */
    public function __construct(SimpleCache\CacheInterface $cache = null, $blnRequiresCleaning = true)
    {
        if ($cache) {
            $this->setCache($cache, $blnRequiresCleaning);
        }
    }

    /**
     * Set the cache.
     *
     * @param SimpleCache\CacheInterface $cache The cache to use
     * @param bool $blnRequiresCleaning True if your cache cannot accept just any string as a key. If true, the key
     *                                                    will be cleaned to conform to the strict requirements of SimpleCache keys.
     * @return $this
     */
    public function setCache(SimpleCache\CacheInterface $cache, $blnRequiresCleaning = true)
    {
        $this->cache = $cache;
        $this->blnMsgIdRequiresCleaning = $blnRequiresCleaning;
        return $this;
    }

    /**
     * Clears the cache, forcing a reload of the cache from temp files, or po files if temp files don't exist
     */
    public function clearCache()
    {
        if ($this->cache) {
            $this->cache->clear();
        } else {
            $this->translations = null;
        }
    }

    /**
     * Set the temporary directory.
     *
     * @param $strTempDir
     * @return $this
     */
    public function setTempDir($strTempDir)
    {
        assert(file_exists($strTempDir), "Temporary directory does not exist.");
        // TODO: Check for write permission to the directory
        $this->strTempDir = $strTempDir;
        return $this;
    }

    /**
     * Binds the given domain to the directory.
     *
     * In this version, we are going to assume that the domain is the same as a Packagist package name,
     * and we are pointing to a directory full of .po files, with each file named by language code and optionally country
     * code. (en.po, es.po, de_ch.po, etc.)
     *
     * @param string $strDomain
     * @param string $strDirectory
     * @param string $strCharset Currently ignored
     * @return $this
     */
    public function bindDomain($strDomain, $strDirectory, $strCharset = 'UTF-8')
    {
        $strDomain = TranslationService::cleanDomain($strDomain);

        assert(file_exists($strDirectory), "i18n directory " . $strDirectory . " does not exist");
        $this->domains[$strDomain] = $strDirectory;

        // TODO: Should we support alternate charsets, or just tell everyone to encode into UTF-8?

        if ($this->strLocale) {    // The language has already been set, so we need to import the PO file
            $this->loadDomain($strDomain);
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
        $this->strDefaultDomain = TranslationService::cleanDomain($strDomain);
        return $this;
    }

    /**
     * Set language and option country code. Set to null to revert to the default language.
     * If using this in a builder pattern during the initial conifuration, this should be
     * the last step.
     *
     * @param string|null $strLanguage
     * @param string|null $strCountry
     * @return void
     */
    public function setLanguage($strLanguage, $strCountry = null)
    {
        $locale = null;
        if ($strLanguage) {
            $locale = $strLanguage;
            if ($strCountry) {
                $locale .= '_' . $strCountry;
            }
        }
        if ($this->strLocale != $locale) {    // If user accidentally set same language twice, we do nothing
            $this->strLocale = $locale;
            $this->translations = null;
            $this->loadCache();
        }
    }

    /**
     * Returns the current locale as a string.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->strLocale;
    }

    /**
     * Translate the given message.
     *
     * @param string $strMsgId
     * @param string|null $strDomain Domain string (Optional)
     * @param string|null $strContext Context string (Optional)
     * @return string
     */
    public function translate($strMsgId, $strDomain, $strContext)
    {
        if ($strDomain === null) {
            $strDomain = $this->strDefaultDomain;
        }

        $key = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null,
            $this->blnMsgIdRequiresCleaning);

        return $this->getEntry($key, $strMsgId, $strDomain);
    }

    /**
     * Translate the given plural message.
     *
     * @param string $strMsgId Singular message id
     * @param string $strMsgId_plural Plural message id
     * @param integer $intNum Number to use to determine which string to return
     * @param string $strDomain Domain string (Optional)
     * @param string $strContext Context string (Optional)
     * @return string
     */
    public function translatePlural($strMsgId, $strMsgId_plural, $intNum, $strDomain, $strContext)
    {
        if ($strDomain === null) {
            $strDomain = $this->strDefaultDomain;
        }

        $offset = $this->pluralNumToOffset($intNum);

        if (!$offset) {
            $key = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null,
                $this->blnMsgIdRequiresCleaning);
        } else {
            $key = static::getKey($strMsgId_plural, $strDomain, $strContext, $this->strLocale, $offset,
                $this->blnMsgIdRequiresCleaning);
        }

        $strTranslation = $this->getEntry($key, null, $strDomain);

        if ($strTranslation !== null) {
            return $strTranslation;
        }

        // Do the default if nothing is working
        if ($intNum == 1) {
            return $strMsgId;
        } else {
            return $strMsgId_plural;
        }
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
     * @param string $strMsgId message id
     * @param string|null $strDomain domain
     * @param string|null $strContext
     * @param string|null $strLocale
     * @param integer|null $intNumOffset if a plural message id, the offset. Offsets start at 1.
     * @param bool $blnRequiresCleaning
     * @return string
     */
    public static function getKey(
        $strMsgId,
        $strDomain = null,
        $strContext = null,
        $strLocale = null,
        $intNumOffset = null,
        $blnRequiresCleaning = false
    ) {
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
            $strMsgId .= '.' . $intNumOffset;    // offsets of 1 are the default plural, and are not needed in the key, since its already a plural key
        }

        if ($blnRequiresCleaning) {
            $strNewMsgId = preg_replace(
                '/^[A-Z][a-z][0-9.][\s]/u',
                '',
                $strMsgId,
                -1,
                $count
            );

            $strNewMsgId = preg_replace(
                '/[\s]/u',
                '_',
                $strNewMsgId
            );
            assert($strNewMsgId !== null);

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
     * @return int
     */
    protected function pluralNumToOffset($intNum)
    {
        if ($intNum == 1) {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * Load the cache with all the files related to the current locale in all domains.
     */
    protected function loadCache()
    {
        foreach ($this->domains as $strDomain => $strDirectory) {
            $this->loadDomain($strDomain);
        }
    }

    /**
     * Load a file for a particular domain in the current locale.
     *
     * TODO: implement fallbacks when the current locale does not exist, but there is a related language. In particular, if the country code is specified, but not available.
     *
     * @param string $strDomain
     */
    protected function loadDomain($strDomain)
    {
        $strPoName = $this->strLocale . '.po';
        $strDirectory = $this->domains[$strDomain];

        $strPoFileName = $strDirectory . '/' . $strPoName;
        if (file_exists($strPoFileName)) {
            $poModTime = filemtime($strPoFileName);

            // Check if temp dir has more recent cached version of this file
            if ($this->strTempDir) {
                $strTempFileName = $this->getTempFileName($strDomain);
                if (file_exists($strTempFileName) &&
                    ($tempModTime = filemtime($strTempFileName)) &&
                    $tempModTime > $poModTime
                ) {
                    $this->loadTempFile($strDomain, $poModTime);
                } else {
                    $this->loadPoFile($strPoFileName, $strDomain, $poModTime);
                }
            } // no temporary files, so we deal with po files directly
            else {
                if ($this->cache) {
                    if (!$this->isCacheLoaded($strDomain, $poModTime)) {
                        $this->loadPoFile($strPoFileName, $strDomain, $poModTime);
                    }
                } else {
                    $this->loadPoFile($strPoFileName, $strDomain, $poModTime);
                }
            }
        }
    }

    /**
     * Load a language file from the temp directory. This is a .po file that we processed earlier into our own format
     * that we can read in order to speed up the loading process.
     *
     * @param string $strDomain The domain to search in
     * @param integer $poModTime The modification time of the corresponding po file.
     */
    protected function loadTempFile($strDomain, $poModTime)
    {
        if ($this->cache) {
            if (!$this->isCacheLoaded($strDomain, $poModTime)) {
                $a = $this->readTempFile($strDomain);
                $this->cache->setMultiple($a);
            }
        } else {
            $a = $this->readTempFile($strDomain);

            if (!$this->translations) {
                $this->translations = $a;
            } else {
                $this->translations = array_merge($this->translations, $a);
            }
        }
    }

    /**
     * Return true if the cache is loaded with current information for the given domain
     *
     * @param string $strDomain The domain to search in
     * @param integer $poModTime The modification time of the corresponding po file.
     * @return bool
     */
    protected function isCacheLoaded($strDomain, $poModTime)
    {
        if ($this->cache) {
            $strKey = $this->strLocale . '.' . $strDomain;
            $oldModTime = $this->cache->get($strKey);
            if ($oldModTime && $oldModTime == $poModTime) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set the key to the given message. Key should come from getKey. Will put it in the cache if we are using one,
     * or our own temporary translation array if not.
     *
     * @param string $strKey
     * @param string $strMessage
     */
    protected function setEntry($strKey, $strMessage)
    {
        if ($this->cache) {
            $this->cache->set($strKey, $strMessage);
        } else {
            $this->translations[$strKey] = $strMessage;
        }
    }

    /**
     * Gets an entry from the cache. If the entry does not exist, will make sure it was not removed from the cache due to an
     * LRU policy or something similar.
     *
     * @param string $strKey Key from getKey
     * @param string $strMsgId This is the corresponding msgid being asked for. It also serves as the default value if the key is not found.
     * @param string $strDomain The domain to search if the key is not in the cache. We need to make sure the key was not removed by the cache.
     * @return string
     */
    protected function getEntry($strKey, $strMsgId, $strDomain)
    {
        if ($this->cache) {
            $strFound = $this->cache->get($strKey);
            if ($strFound !== null) {
                return $strFound;
            }
            if ($strFound === static::INVALID_ENTRY) {
                return $strMsgId;    // The value does not exist in the PO file. This is an error that must be fixed.
            }

            // If we get here, we have a hard problem to solve. Either the value has been removed by the cache because memory is low, or
            // the value did not ever exist in the PO file. To solve this, we try to reload the PO file, and if we still cannot find
            // the value, we will mark the value in the cache as being invalid. We also assert so that a debug environment will alert
            // the developer of the missing PO entry.

            $this->loadDomain($strDomain);
            $strFound = $this->cache->get($strKey);
            if ($strFound !== null) {
                return $strFound;    // successfully reloaded cache
            } else {
                assert(false, "Trying to translate a msgid that does not exist in the PO file. Msgid: " . $strMsgId);
                $this->setEntry($strKey, static::INVALID_ENTRY);
                return $strMsgId;    // The value does not exist in the PO file. This is an error that must be fixed.
            }
        } elseif ($this->translations) {
            if (isset($this->translations[$strKey])) {
                return $this->translations[$strKey];
            } else {
                assert(false, "Trying to translate a msgid that does not exist in the PO file. Msgid: " . $strMsgId);
                return $strMsgId;
            }
        }
        return $strMsgId;    // Nothing loaded
    }

    /**
     * Loads a PO file. After processing the PO file, it will put its entries into the cache, and also try to save the
     * resulting array into a temporary file so that the next time we try to load this language file, it will go faster.
     * Makes sure we update the modification date of any cached data so we know the cache is valid.
     *
     * @param string $strPoFileName
     * @param string $strDomain
     * @param integer $poModDate
     */
    protected function loadPoFile($strPoFileName, $strDomain, $poModDate)
    {
        $entries = Parser::parseFile($strPoFileName)->getEntries();
        $data = [];

        foreach ($entries as $entry) {
            $strMsgId = implode('', $entry['msgid']);
            $strContext = null;
            if (isset($entry['msgctxt'])) {
                $strContext = implode('', $entry['msgctxt']);
            }
            if (isset($entry['msgid_plural'])) {
                $strPlural = implode('', $entry['msgid_plural']);
                $i = 0;
                while (isset($entry['msgstr[' . $i . ']'])) {
                    $strMsg = implode('', $entry['msgstr[' . $i . ']']);
                    if ($i == 0) { // singular
                        $key = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null,
                            $this->blnMsgIdRequiresCleaning);
                    } else {
                        $key = static::getKey($strPlural, $strDomain, $strContext, $this->strLocale, $i,
                            $this->blnMsgIdRequiresCleaning);
                    }
                    $data[$key] = $strMsg;
                    $i++;
                }
            } else {
                if (isset($entry['msgstr'])) {
                    $key = static::getKey($strMsgId, $strDomain, $strContext, $this->strLocale, null,
                        $this->blnMsgIdRequiresCleaning);
                    $data[$key] = implode($entry['msgstr']);
                } else {
                    // PO file error?
                }
            }
        }

        // Put the data in the cache
        if ($this->cache) {
            $this->cache->setMultiple($data);
            $strKey = $this->strLocale . '.' . $strDomain;
            $this->cache->set($strKey, $poModDate);    // notify that the data is up to date
        } else {
            if (!$this->translations) {
                $this->translations = $data;
            } else {
                $this->translations = array_merge($this->translations, $data);
            }
        }

        // Write the data to a PHP file so the next time we need to read it, we can get to it faster than parsing a PO file.
        if ($this->strTempDir) {
            $this->writeTempFile($data, $strDomain);
        }
    }

    /**
     * Returns the name of the temporary file that will hold processed po file data.
     *
     * @param $strDomain
     * @return string
     */
    protected function getTempFileName($strDomain)
    {
        return $this->strTempDir . '/' . $this->strLocale . '/' . $strDomain . '.php';
    }

    /**
     * Write data to the temporary file.
     *
     * @param string[] $data
     * @param string $strDomain
     */
    protected function writeTempFile($data, $strDomain)
    {
        if (!file_exists($this->strTempDir . '/' . $this->strLocale)) {
            mkdir($this->strTempDir . '/' . $this->strLocale);
        }
        // Stackexhchange lore suggests that json encode is the fastest way to do this using standard PHP calls,
        // though igbinary may be even faster, but we don't want to make that a dependency at this point.
        $strEncodedData = json_encode($data);
        if($strEncodedData) {
			file_put_contents($this->getTempFileName($strDomain), $strEncodedData, LOCK_EX);
		} else {
			assert(false, 'Translation cannot be json_encoded, check the source file for domain ' . $strDomain . '. Original json error: ' . json_last_error_msg());
		}
    }

    /**
     * Read data from the temporary file.
     *
     * @param string $strDomain
     * @return array
     */
    protected function readTempFile($strDomain)
    {
        $data = file_get_contents($this->getTempFileName($strDomain));
        return json_decode($data, true);
    }

}
