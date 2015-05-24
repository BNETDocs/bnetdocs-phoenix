BNETDocs: Phoenix
=================

Preface
-------
BNETDocs is a web content management system (CMS) for the Battle.net&trade;
online gaming service, with its purpose to document the Battle.net&trade;
protocol.

BNETDocs is in no way affiliated with or endorsed by Blizzard
Entertainment&trade; or its brands, and is not intended to compete with
or undermine Blizzard Entertainment&trade; or Battle.net&trade;. Persons who
use this software understand that the information provided within is
collected knowledge from years of observations and may be inaccurate. See the
LICENSE file at the root of this repository for more info.

**BNETDocs: Phoenix** is the successor to
[BNETDocs: Redux](https://github.com/carlbennett/bnetdocs-web/tree/redux).

Installation
------------

1. Clone this repository to a local directory on your development environment.
 - Recommended location: `/home/nginx/bnetdocs-local/`
2. Setup an nginx/php-fpm web server using
   [nginx-conf](https://github.com/carlbennett/nginx-conf) as the config.
 - Modify the example server config to use `local.bnetdocs.org` instead.
 - Add the following to the `local.bnetdocs.org` server config file:<br/>
   `include conf.d/php.conf;`
 - Add the following to the `local.bnetdocs.org` server config file:<br/>
   `location / { try_files /static$document_uri /main.php?$args; }`
3. Start nginx and php-fpm on your server and ensure they begin running.
4. Import and setup sample database (TODO: create sample database).
5. Copy `/config.sample.json` to `/config.json` and modify it to your
   environment.
6. Try accessing this endpoint:
   [local.bnetdocs.org](https://local.bnetdocs.org)
 - You may need to modify your `/etc/hosts` file if your development
   environment is not your localhost.