MCI Local Location Registry
========================

This application will help to populate the location from the BBS geo-code excel file

Requirements
=============
 - PHP 5.4+
 - Composer
 - Other dependencies will be downloaded by composer


To run the application
======================
- Download the source
- copy app/config/parameters.yml.dist app/config/parameters.yml and set all the configuration as you need
- Run `composer install` command
- Finally run `php app/console mci:import:lr` command
