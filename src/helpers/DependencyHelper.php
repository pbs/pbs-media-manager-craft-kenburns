<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\helpers;

use Craft;
use Exception;

use papertiger\mediamanager\base\ConstantAbstract;
use papertiger\mediamanager\MediaManager;

class DependencyHelper
{
    // Public Static Methods
    // =========================================================================
    
    public static function installDependencies()
    {
        self::_installCraftRichtextPlugin();
    }


    // Private Methods
    // =========================================================================

    private static function checkPluginExists( $pluginHandle ): bool
    {
        $plugins = Craft::$app->getPlugins()->getAllPluginInfo();
        $plugins = array_keys( $plugins );

        if( !in_array( $pluginHandle, $plugins ) ) {
            return false;
        }

        return true;
    }

    private static function checkPluginInstalled( $pluginHandle ): bool
    {
        if( !Craft::$app->getPlugins()->isPluginInstalled( $pluginHandle ) ) {
            return false;
        }

        return true;
    }

    private static function checkPluginDisabled( $pluginHandle ): bool
    {
        if( !Craft::$app->getPlugins()->isPluginDisabled( $pluginHandle ) ) {
            return true;
        }

        return false;
    }

    private static function _installCraftRichtextPlugin(): void
    {
        $allowableRichtextPlugins = ConstantAbstract::DEPENDENCY_PLUGIN_CRAFT_RICHTEXT_PLUGINS;
				
        $hasAllowablePluginInstalled = collect($allowableRichtextPlugins)->first(function($plugin, $key){
                return self::checkPluginExists($plugin['handle']);
        });
        
        if($hasAllowablePluginInstalled) {
            $plugin = MediaManager::getInstance();
            $plugin->settings->defaultRichtextField = $hasAllowablePluginInstalled;
            
            return;
        }
        
        $defaultPlugin = ConstantAbstract::DEFAULT_RICHTEXT_TYPE;
        $pluginHandle = $defaultPlugin['handle'];
	      Craft::$app->getComposer()->install( [ $defaultPlugin['handle'] => $defaultPlugin['version'] ] );

        if( self::checkPluginDisabled( $pluginHandle ) && Craft::$app->getPlugins()->getStoredPluginInfo( $pluginHandle ) ) {
            
            Craft::$app->getPlugins()->enablePlugin( $pluginHandle ); // Need to recheck this one
            return;
        }

        if( !self::checkPluginInstalled( $pluginHandle ) ) {
            Craft::$app->getPlugins()->installPlugin( $pluginHandle );
        }
    }

}
