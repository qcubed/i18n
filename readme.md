#QCubed\i18n
An attempt to put together a comprehensive, standalone language translation tool for PHP.

This was originally a fork of the excellent work by Raúl Ferràs (raul.ferras@gmail.com  
https://github.com/raulferras/PHP-po-parser)
That code has been moved to its own section of this repository for maintainability, 
and is also licensed under MIT. This project builds on that work.

##Why another translation tool?
There are lots of translation tools available on the internet for PHP. The problem is they
are scattered all over, embedded deep within existing frameworks, and only solve a part
of the problem, and sometimes not very well.

The gold standard, GNU's gettext, is rather dated, requires offline precompilation of .po
files into .mo files, placement of those files in a specially formatted hierarchy (which
makes it rather difficult to implement translation files with Composer), and requires
that the language pack of whatever language you are translating be installed into the
operating system.

There is a php-geettext, which improves things a little, but its not quite comprehensive, 
and still requires .mo files to operate.

##Goals
- **Standalone**. Anyone can use it without needing other products. It currently is impemented at
the top layer as a singleton, but this can be easily modified to use an injection container if preferred.
- **Composer compatible**. .PO language files can now be distributed with composer libraries
and easily included into a bigger application. 
Allows you to map a directory to a domain, and expects that Packagist libraries
will map the domain that is the same name as their package name, making it easy for the
package to get to its own translations.
- **Standards Compliant**. PSR-1, PSR-2 and PSR-4 compatible, with a support module that uses a
PSR-16 SimpleCache for caching (more on this below).

##Implementations
Detailed descriptions of each implementation is available in the header of each file. 
These are described in general below.

You are expected to include the *i18n.inc.php* file that is in the tools directory into your
project so that you can directly use the _t and _tp functions there in your source code to
call for translations. This will make everything easy to use.

###GettextTranslator
Uses the GNU gettext translation module buiit-in to PHP. Not ideal, but if you have .mo files,
its a good way to go.

###SimpleCacheTranslator
Can use a PSR-16 compliant simple cache as a store for translations (we are working on
an APCu and Redis implementation of these now.) Directly reads .PO files from a
directory. Will work without a cache, but its better if your provide one.

Supply a temp directory, and it will convert the .po files into json and cache the json,
making subsequent reads faster.

##Typical Use
###Setup
```php
		$cache = new MyCache();
		$translator = new \QCubed\I18n\SimpleCacheTranslator($cache);

		$translator->bindDomain('package/subpackage', __VENDOR_DIR__ . "/package/subpackage/I18n") // directory of .po files
			->bindDomain('project', __PROJECT_DIR__ . "/I18n") // pointer to your specific translations
			->setDefaultDomain('project') // get translations from here if no domain is specified
			->setCache($cache)
			->setTempDir($tempDirPath);

		\QCubed\I18n\TranslationService::instance()->setTranslator($translator);
		\QCubed\I18n\TranslationService::instance()->setLanguage('es'); //Make a particular language the active language.

```

###Getting a translation
```
$str = _t("Hello");	// translate Hello into the currently active translation using the default domain (that is, the .po file from your project for the default language)
$str = _t("Hello", "package/subpackage", "a context"); // get a translation using a domain and context
$str = _tp("A Hello to your", "Many hellos to you", $n);	// Do a plural translation based on the integer $n
$str = _tp("A Hello to your", "Many hellos to you", $n, "package/subpackage", "a context");	// Do a plural translation based on the integer $n with domain and context
```

##RoadMap
* Implement a version based on https://github.com/sevenval/SHMT for super-fast translations.
* Suck in some code that will search through PHP files and build a .pot file. The code is out
there somewhere already for this. Try php-gettext first.
* Implement code to auto wrap strings with _t() as a first step to localizing a file.
* Implement language specific pluralize for languages with more than one plural form. Again,
the code is out there to help with this, just need to pull it in.
* Either reference or pull in other helpful code, like to do .po file updates and merges,
perhaps somewhat automated using .po comments to correlate the source of a string.