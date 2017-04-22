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
 * Implement this interface to create different translation objects
 */
interface TranslatorInterface {
	/**
	 * Translate the given message.
	 *
	 * @param string $strMsgId
	 * @param string|null $strDomain	Domain string (Optional)
	 * @param string|null $strContext	Context string (Optional)
	 * @return string
	 */
	public function translate($strMsgId, $strDomain, $strContext);

	/**
	 * Translate the given plural message.
	 *
	 * @param string $strMsgId		Singular message id
	 * @param string|null $strMsgId_plural	Plural message id
	 * @param integer $intNum		Number to use to determine which string to return
	 * @param string $strDomain		Domain string (Optional)
	 * @param string $strContext	Context string (Optional)
	 * @return string
	 */
	public function translatePlural($strMsgId, $strMsgId_plural, $intNum, $strDomain, $strContext);

	/**
	 * Set the language and country code.
	 * @param string $strLanguage Language code (e.g. en, fr)
	 * @param string|null $strCountry Country code (e.g. us) (Optional)
	 * @return mixed
	 */
	public function setLanguage ($strLanguage, $strCountry);
}
