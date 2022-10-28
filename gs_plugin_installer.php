<?php defined('IN_GS') or die('Cannot load plugin directly.');
/*
Plugin Name: GS Plugin Installer
Description: Lets you browse, install and uninstall plugins from your administration area.
Version: 1.0.4
Author: Helge Sverre
Author URI: https://helgesverre.com/
*/


error_reporting(E_ALL);
ini_set("display_errors", "on");

// Gets the plugin id, which is pretty much just the filename without the extension
$thisfile = basename(__FILE__, ".php");

// set a constant for our plugin cache file
define("CACHE_FILE", GSPLUGINPATH . '/' . $thisfile . '/plugin_cache.json');

// Register this plugin
register_plugin(
    $thisfile,
    'GS Plugin Installer',
    '1.0.4',
    'Helge Sverre',
    'https://helgesverre.com/',
    'Let\'s you browse, install and uninstall plugins from your administration area.',
    'plugins',
    'gs_plugin_installer_main'
);


// Enque the datatables js from CDN
register_script('datatables_js', 'http://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js', '1.0');
queue_script('datatables_js', GSBACK);

// Enque the datatables css from CDN
register_style('datatables_css', 'http://cdn.datatables.net/1.10.7/css/jquery.dataTables.min.css', '1.0', 'screen');
queue_style('datatables_css', GSBACK);

// add a link in the admin tab 'plugins'
add_action('plugins-sidebar', 'createSideMenu', array($thisfile, 'Plugin Installer'));


// Main plugin function
function gs_plugin_installer_main()
{
    global $SITEURL;

    if (isset($_GET["update"])) {
        delete_plugin_cache(CACHE_FILE);
        ?>
        <script>
        $(function () {
            $('div.bodycontent').before('<div class="updated" style="display:block;">Plugin Cache refreshed</div>');
                $('.updated, .error').fadeOut(300).fadeIn(500);
            });
        </script>
        <?php
    }



    if (isset($_GET["install"])) {
        // PLUGIN INSTALLATION
        //----------------------------------------------------------------------------------------------------------------------
        $plugin_id = $_GET["install"];
        $installed = install_plugin($plugin_id);
        $install_msg = ($installed ? "Plugin installed sucesfully" : "Plugin installation failed");

        ?>
        <script>
            $(function () {
                $('div.bodycontent').before('<div class="<?php echo ($installed ? 'updated' : 'error'); ?>" style="display:block;">' + "<?php echo $install_msg ?>" + '</div>');
                $('.updated, .error').fadeOut(300).fadeIn(500);
            });
        </script>

    <?php
    }

    if (isset($_GET["uninstall"])) {
        // PLUGIN UNINSTALLATION
        //----------------------------------------------------------------------------------------------------------------------
        $plugin_id = $_GET["uninstall"];
        $uninstalled = uninstall_plugin($plugin_id);
        $uninstall_msg = ($uninstalled ? "Plugin uninstalled succesfully" : "Plugin uninstallation failed");

        ?>
        <script>
            $(function () {
                $('div.bodycontent').before('<div class="<?php echo ($uninstalled ? 'updated' : 'error'); ?>" style="display:block;">' + "<?php echo $uninstall_msg ?>" + '</div>');
                $('.updated, .error').fadeOut(300).fadeIn(500);
            });
        </script>
    <?php

    }

    // PLUGIN LIST
    //----------------------------------------------------------------------------------------------------------------------
    $plugins = get_plugins();

    ?>

    <h3 class="floated">Plugins</h3>
    <div class="edit-nav clearfix">
        <a href="<?php echo $SITEURL . "admin/load.php?id=gs_plugin_installer"?>&update" title="Update">Refresh List</a>
    </div>

    <table id="plugin_table" class="highlight">
        <thead>
        <tr>
            <th>Updated</th>
            <th>Plugin</th>
            <th>Description</th>
            <th>Install</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($plugins as $index => $plugin): ?>
            <tr id="tr-<?php echo $index ?>">
                <td><?php $plugin->updated_date ?></td>
                <td style="width:150px"><a href="<?php echo $plugin->path?>" target="_blank"><b><?php echo $plugin->name ?></b></a></td>
                <td>
                    <div class="description"><?php echo trim(strip_tags(nl2br($plugin->description), "<br><br/>")) ?></div>
                    <b>Version <?php echo $plugin->version ?></b>
                        — Author: <a href="<?php echo $plugin->author_url ?>" target="_blank"><?php echo $plugin->owner ?></a>
                    
                </td>
                <td style="width:60px;">
                    <?php if (is_plugin_installed($plugin)): ?>
                        <a class="cancel" href="<?php echo $SITEURL . "admin/load.php?id=gs_plugin_installer" ?>&uninstall=<?php echo $plugin->id ?>">Uninstall</a>
                    <?php else: ?>
                        <a href="<?php echo $SITEURL . "admin/load.php?id=gs_plugin_installer" ?>&install=<?php echo $plugin->id ?>">Install</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

	<style>
		#plugin_table .description {
			display:block;
			min-height: 45px; /* so when we are hovering the description wont shrink */
			max-height: 45px;
			overflow-y: hidden;
			transition: max-height .8s;
		}

		/* When hovering the table row, show the entire description */
		#plugin_table tr:hover td .description {
			max-height: 5000px; /* Workaround for transition issues with height: auto; */
			transition: max-height .8s;
		}
	</style>

    <script>
        $(document).ready(function () {
            $('#plugin_table').DataTable({
            	 "columnDefs": [
	            	 {
		                "targets": [ 0 ],
		                "visible": false,
		                "searchable": true
		           	 },
		           	 {
		                "targets": [ 3 ],
		                "visible": true,
		                "searchable": false // exlude "install" column from search
		           	 },
	           	]
            });
        });
    </script>
<?php

}


//----------------------------------------------------------------------------------------------------------------------


/**
 * NOTE: This function takes a little time to execute since there is no paging
 * support in the api I have to grab EVERYTHING for on every request :(
 * Goes out to the Extend API and fetches all currently available plugins
 * @return array list of plugin objects
 */
function get_plugins()
{
    $plugins = array();

    // Check if we have a cached version of the plugins json file
    if (file_exists(CACHE_FILE)) {

        // Get the last time that the cache was modified
        $cache_age = (time() - filemtime(CACHE_FILE));

        // If the cache is older than 24 hours, we fetch new data from the API
        if (($cache_age) > (24 * 3600)) {

            // Fetch the plugins from the api
            $plugins = fetch_plugins_from_api();

            // Let's cache the plugin list, so we don't have to query the Extend API every time.
            save_to_cache(CACHE_FILE, $plugins);

        } else {
            // If the cache is fresh enough, we just load the data from it instead.
            $cachedata = file_get_contents(CACHE_FILE);
            $plugins = json_decode($cachedata);
        }
    } else {
        // We have no cache file, fetch from API
        $plugins = fetch_plugins_from_api();

        // Then let's save it to the cache
        save_to_cache(CACHE_FILE, $plugins);
    }


    // Return all plugins
    return $plugins;
}



/**
 * @param string $file the filepath of the cache file.
 * @param mixed $data array to save as json
 */
function save_to_cache($file, $data)
{
    $data = json_encode($data);

    $cache_directory = dirname($file);

	// If the folder does not exist, create it
    if (!file_exists($cache_directory)) {
    	mkdir($cache_directory);
    	chmod($cache_directory, 0755);
    }

    file_put_contents($file, $data);
}


/**
 * deletes a file
 * @param string $file pass it the cache file to delete
 */
function delete_plugin_cache($file)
{
    unlink($file);
}



/**
 * Fetches all plugins from the Extend API.
 * @return array array of plugins from the Extend API
 */
function fetch_plugins_from_api()
{
    $plugins = array();

    // Fetch all items from the api
    $items = query_api("http://get-simple.info/api/extend/all.php");

    // Sort through all the items, we only want the Plugins, they have a category of "Plugin"
    foreach ($items as $item) {
        if (isset($item->category) && $item->category == "Plugin") {

            // If the plugin does not have a main file, it is not installable with this plugin, so ignore it.
            if ($item->filename_id !== "") {

                // Put the plugin in the plugins array
                array_push($plugins, $item);
            }
        }
    }

    return $plugins;
}


/**
 * @param string $url the url to go and fetch json data from
 * @return mixed returns the appropriate PHP Type from the
 * JSON_DECODE'ed data from the Extend API, returns false if conversion fails.
 */
function query_api($url)
{
    $json = file_get_contents($url);
    $data = json_decode($json);

    return $data;
}


/**
 * Installs the plugin by downloading the zip archive with the plugin files to
 * the plugins/ folder giving it a unique randomized name, then unzipping it,
 * after it's unzipped it will remove the zip file.
 * @param int $id the id of the plugin
 * @return bool true on success, false on failure
 */
function install_plugin($id)
{
    if (is_numeric($id)) {

        $data = query_api("http://get-simple.info/api/extend/?id=" . $id);

        // Define the tmp filepath for the zip file
        $filepath = GSPLUGINPATH . "/" . uniqid() . ".zip";

        // Create a file stream to the plugin zip file on Extend
        $pluginFile = fopen($data->file, 'r');

        // Put the zip file in the filepath
        file_put_contents($filepath, $pluginFile);

        // If it exists
        if (file_exists($filepath)) {

            // Open the zip file object
            $zip = new ZipArchive;

            // If we can open the file
            if ($zip->open($filepath)) {

                // extract/install the plugin into the GetSimple Plugin folder
                $zip->extractTo(GSPLUGINPATH);

                // Close the resource handle
                $zip->close();

                // delete the temp file
                unlink($filepath);

                return true; // Installation successfull
            } else {
                return false; // Insallation failed
            }
        }
    }

    // Invalid plugin id
    return false;
}


/**
 * Checks if a plugin is installed by checking for the main plugin file.
 * @param object $plugin the plugin object (json_decoded object from the extend JSON API for a single plugin
 * @return bool true if it is installed, false if it is not
 */
function is_plugin_installed($plugin)
{
    $plugin_file = GSPLUGINPATH . "/" . $plugin->filename_id;

    if (file_exists($plugin_file)) {
        return true;
    }

    return false;
}


/**
 * Removes the files and folders associated with a plugin, it does this by querying
 * the Extend api and getting the main filename of the plugin and guessing the folder
 * for the plugin if it exists, NOTE that this function assumes the plugin developer
 * followed the naming standards of plugin folders (having the same name as the main plugin file
 * @param int $id the id of the plugin to uninstall
 * @return bool true on success, false on failure
 */
function uninstall_plugin($id)
{
    // We need to get the main plugin file name.
    $plugin = query_api("http://get-simple.info/api/extend/?id=" . $id);

    // This is assuming that the plugin keeps the GetSimple naming convention
    $plugin_folder = GSPLUGINPATH . "/" . trim($plugin->filename_id, ".php");
    $plugin_file = GSPLUGINPATH . "/" . $plugin->filename_id;


    // check if the plugin file exists
    if (file_exists($plugin_file)) {
        if (!unlink($plugin_file))
            return false;
    }

    // check if the plugin folder exists, this might not always be the case.
    if (file_exists($plugin_folder)) {
        if (!delete_directory_tree($plugin_folder))
            return false;
    }

    // We succesfully uninstalled this plugin
    return true;
}


/**
 * deletes a folder and everything inside of it by using recursion
 * @param string $dir the folder to delete the contents of
 * @return bool true on success, false on failure
 */
function delete_directory_tree($dir)
{
    // Exclude the current and parent folder, we don't want to delete those.
    $files = array_diff(scandir($dir), array('.', '..'));

    // foreach item in the folder
    foreach ($files as $file) {
        if ((is_dir($dir . '/' . $file))) {
            // If the item is a folder, we call ourself (recursion)
            delete_directory_tree($dir . '/' . $file);
        } else {
            // if the item is a file, delete it.
            unlink($dir . '/' . $file);
        }
    }

    return rmdir($dir);
}

?>