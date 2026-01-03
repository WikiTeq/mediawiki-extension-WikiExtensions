<?php

// Extra settings loaded for PHPUnit tests - the
// `wikiteq/mediawiki-phpunit-action` enables the current extension
// automatically but we also need to load examples and set it up

// Unfortunately examples cannot be installed directly in the normal
// "extensions" directory since it gets downloaded *before* the rest of
// MediaWiki. This file is at
// /home/runner/work/mediawiki-extension-WikiExtensions/mediawiki-extension-
//    WikiExtensions/mediawiki/extensions/WikiExtensions/.github/PHPUnitSettings.php
// and we install examples into the same directory as MediaWiki itself:
// So, go up 4 levels and then into the extension
wfLoadExtension(
	'examples',
	__DIR__ . '/../../../../examples/extension.json'
);
