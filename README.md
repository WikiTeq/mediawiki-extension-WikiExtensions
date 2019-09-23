WikiExtensions
==============

The extension allows to manage extensions installed via GIT - it provides
a special page and an API endpoint to list available updates and perform
upgrades via `git pull`.

Extensions installed without GIT are not supported and being ignored.

Requirements
------------

* Mediawiki 1.31+
* Composer
* Git 1.7+

Setup
-----

* Clone into `extensions` folder as `WikiExtensions`
* Load via `wfLoadExtension('WikiExtensions')`

Usage
---

* Navigate to `Special:WikiExtensions` to see extensions updates and do upgrades
* Use `wikiextensions` API endpoint to list extensions updates and do upgrades

Configuration
-------------

* Grant `wikiextensions_update` right to users allowed to perform 
updates (granted to `sysop` by default)
* Grant `wikiextensions_view` right to users to allow viewing of special page
and list extensions statuses
* Use `wgWikiExtensionsAllowedIP` configuration array to list IPs
allowed to perform upgrades without extra authentication

Contributing
------------

Tip: if you'd like to test things out and bored with joggling git repo
just pick a stale repository and do `git reset --keep HEAD@{1}` after updates,
it will allow you to quickly rollback to before `git pull` state. 
