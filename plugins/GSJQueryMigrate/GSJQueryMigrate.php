<?php
/*
Plugin Name: GSJQueryMigrate
Description: implements jquery Migrate for backwards compatability of jquery code older than 1.9
Version: 1.0
Author: getSimpleCMS
Author URI: http://get-simple-info
*/

$thisfile_GSJQM = basename(__FILE__, ".php");

function jQuery_migrate_init(){
    GLOBAL = $thisfile_GSJQM;
    i18n_merge($thisfile_GSJQM ) || i18n_merge($thisfile_GSJQM , 'en_US');

    # register plugin
    register_plugin(
        $thisfile_GSJQM ,                              # ID of plugin, should be filename minus php
        i18n_r($thisfile_GSJQM .'/GSJQMigrate_TITLE'), # Title of plugin
        '1.0',                                  # Version of plugin
        'GetSimpleCMS',                         # Author of plugin
        'http://get-simple-info',               # Author URL
        i18n_r($thisfile_GSJQM .'/GSJQMigrate_DESC'),  # Plugin Description
        '',                                     # Page type of plugin
        ''                                      # Function that displays content
    );

    add_action('common','GSJQueryMigrate_script');

}

function GSJQueryMigrate_script(){
    GLOBAL $thisfile_GSJQM , $SITEURL;
    $url = $SITEURL.'plugins/'.$thisfile_GSJQM;
    register_script('jquerymigrate', $url, '', FALSE);
    queue_script('jquerymigrate');
}

jQuery_migrate_init();