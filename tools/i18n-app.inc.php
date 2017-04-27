<?php
/**
 * This is a convenience file to include in your application project. It brings common shortcuts for translation into the global
 * space, similar to how the gettext PHP plugin works. You can either use it, or make up your own to do something similar,
 * like if you are using a service container for dependency injection.
 *
 * It just maps shortcut functions to the namespaced functions.
 *
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

/**
 * Shortcut for the basic translator, translating the given msg to a localized message.
 * @param string $strMsgId			The home language version of the string. If no translation file is found, it will return this string (or perhaps throw an exception. This is configurable).
 * @param string|null $strDomain	The domain. Libraries should specify their PACKAGIST package name. Tells the translator to look in a specific place.
 * @param string|null $strContext	Rarely used, but a way to handle the same msgId differently based on a context identifier. This context will correspond
 * 									to a context in the PO file.
 * @return string					The translated string, or the msgId if no translation is found.
 */
function t($strMsgId, $strDomain = null, $strContext = null)
{
	return \QCubed\I18n\TranslationService::instance()->translate($strMsgId, $strDomain, $strContext);
}

/**
 * Shortcut for the plural translator. In English, this will return the first string if
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
function tp($strMsgId, $strMsgId_plural, $intNum, $strDomain = null, $strContext = null)
{
	return \QCubed\I18n\TranslationService::instance()->translatePlural(
		$strMsgId,
		$strMsgId_plural,
		$intNum,
		$strDomain,
		$strContext);
}