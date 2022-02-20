WikiExtensions
==============

The extension allows to manage extensions installed via GIT - it provides
a special page and an API endpoint to list available updates and perform
upgrades via `git pull`.

<img width="1089" alt="image" src="https://user-images.githubusercontent.com/592009/154856047-b7ce6253-20c2-40cf-8616-94ab2ba2acb6.png">

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

* Navigate to `Special:Extensions` to see extensions updates and do upgrades
* Use `wikiextensions` API endpoint to list extensions updates and do upgrades

API
---

The `wikiextensions` provides following methods:

* `do=list` - lists all extensions available and their states
* `do=update&extensions=Vector` - tries to update the extension
* `do=extension&extension=Vector` - fetch single extension state

The response structure is:

```json
{ 
   "wikiextensions":{ 
      "status":"OK",
      "extensions": [
        {
             "name":"Vector",
             "version":"1.0",
             "is_writable":1,
             "is_git":1,
             "has_updates":1,
             "has_uncommitted_changes":0,
             "can_pull":1,
             "git":{ 
                "branch":"master",
                "remote_commit":"b149101e60ca1c9d57f144ee6bb55f94c5e50b80",
                "local_commit":"b149101e60ca1c9d57f144ee6bb55f94c5e50b80",
                "remote_commit_short":"b149101",
                "local_commit_short":"b149101"
             },
             "error":null
        },
        { ... },
        { ... }
      ]
   }
}
```

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

Troubleshooting
---------------

* Ensure your extensions files has correct permissions for git operations to
be run under web server user, eg: files need to be owned by www-user
* Ensure your server knows about git hosts you're going to use, eg.: sometimes
there could be issues with bitbucket.org & github.com, as a solution you can
do `ssh-keyscan github.com >> ~/.ssh/known_hosts` and `ssh-keyscan bitbucket.org >> ~/.ssh/known_hosts`
and better option would be to use HTTP(s) to clone extensions instead of SSH.
