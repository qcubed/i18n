<?php
/**
 * If you are building a library to be included by other PHP applications, include this file
 * to do your translations, and use the _t and _tp functions below to do the translations. Also, be sure to change the
 * namespace and domain names as mentioned below.
 *
 * By default, this file expects that it is placed in the same directory as the .po files for your project.
 *
 * You should use "require_once" to include this file in your library files.
 *
 * Use a require or recommend statement in your composer.json file to tell people to include the QCubed\i18n library in
 * their product to do translations. If they do not, your code will still work, it just won't translate languages.
 */

namespace QCubed\I18n;	// include this file in your namespace so that the functions defined below are unique to your library

const I18N_DOMAIN = 'qcubed/i18n'; // replace this with your package name

/**
 * Translation function specific to your package.
 *
 * @param string $strMsgId			String to translate
 * @param string|null $strContext	Context string, if the same string gets translated in different ways depending on context
 * @return string
 */
function t($strMsgId, $strContext = null)
{
	if (class_exists("\\QCubed\\I18n\\TranslationService") && \QCubed\I18n\TranslationService::instance()->translator()) {
		if (!defined (I18N_DOMAIN . '__BOUND')) {
			define(I18N_DOMAIN . '__BOUND', 1);
			\QCubed\I18n\TranslationService::instance()->bindDomain(I18N_DOMAIN, __DIR__);	// bind the directory containing the .po files to my domain

		}
		return \QCubed\I18n\TranslationService::instance()->translate($strMsgId, I18N_DOMAIN, $strContext);
	}
	return $strMsgId;
}

/**
 * Translation function for plural translations.
 *
 * @param string $strMsgId			Singular string
 * @param string $strMsgId_plural	Plural string
 * @param integer $intNum			Number used to choose which string gets picked.
 * @param string|null $strContext	Context if needed
 * @return string
 */
function tp($strMsgId, $strMsgId_plural, $intNum, $strContext = null)
{
	if (class_exists("\\QCubed\\I18n\\TranslationService") && \QCubed\I18n\TranslationService::instance()->translator()) {
		if (!defined (I18N_DOMAIN . '__BOUND')) {
			define(I18N_DOMAIN . '__BOUND', 1);
			\QCubed\I18n\TranslationService::instance()->bindDomain(I18N_DOMAIN, __DIR__);	// bind the directory containing the .po files to my domain

		}
		return \QCubed\I18n\TranslationService::instance()->translatePlural(
			$strMsgId,
			$strMsgId_plural,
			$intNum,
			I18N_DOMAIN,
			$strContext);
	}
	if ($intNum == 1) {
		return $strMsgId;
	}
	else {
		return $strMsgId_plural;
	}
}