<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager;

use Craft;
use Exception;
use yii\base\Event;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\ModelEvent;
use craft\helpers\UrlHelper;
use craft\services\Utilities;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\web\twig\variables\CraftVariable;

use papertiger\mediamanager\models\SettingsModel;
use papertiger\mediamanager\helpers\SetupHelper;
use papertiger\mediamanager\helpers\SettingsHelper;
use papertiger\mediamanager\helpers\DependencyHelper;
use papertiger\mediamanager\behaviors\MediaBehavior;
use papertiger\mediamanager\services\Api as ApiService;
use papertiger\mediamanager\services\Show as ShowService;
use papertiger\mediamanager\services\OldSettings as OldSettingsService;
use papertiger\mediamanager\helpers\aftersavesettings\FieldLayoutHelper;
use papertiger\mediamanager\helpers\aftersavesettings\ApiColumnFieldsHelper;
use papertiger\mediamanager\helpers\aftersavesettings\ShowFieldLayoutHelper;
use papertiger\mediamanager\helpers\aftersavesettings\ShowApiColumnFieldsHelper;
use papertiger\mediamanager\helpers\aftersavesettings\OldSettingsHelper;

class MediaManager extends Plugin
{
    // Static Properties
    // =========================================================================
    public static $plugin;


    // Public Properties
    // =========================================================================
    public $hasCpSettings = true;
    public $hasCpSection  = true;
		
		public $schemaVersion = '1.0.1';
    // Public Methods
    // =========================================================================

    public static function t( $message, $params = [], $language = null )
    {
        return Craft::t( 'mediamanager', $message, $params, $language );
    }

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->registerRoutes();
        $this->registerInstallationEvents();
        $this->registerBehaviors();
        $this->registerPluginServices();

        Craft::info(
            Craft::t(
                'mediamanager',
                '{name} plugin loaded',
                [ 'name' => $this->name ]
            ),
            __METHOD__
        );
    }

    public function beforeInstall(): bool
    {
        if( version_compare( Craft::$app->getInfo()->version, '3.0', '<' ) ) {
            throw new Exception( 'Media Manager 3 requires Craft CMS 3.0+ in order to run.' );
        }

        return true;
    }

    public function afterSaveSettings()
    {
        ApiColumnFieldsHelper::process();
        FieldLayoutHelper::process();
        ShowApiColumnFieldsHelper::process();
        ShowFieldLayoutHelper::process();
        OldSettingsHelper::process();
    }

    public function getSettingsResponse()
    {
        // This way we can have more flexibility on displaying settings template
        return Craft::$app->controller->renderTemplate( 'mediamanager/settings', SettingsHelper::templateVariables() );
    }

    public function getCpNavItem(): array
    {
        $navigation = parent::getCpNavItem();

        $navigation[ 'label' ] = self::t( 'Media Manager' );

        $navigation[ 'subnav' ][ 'shows' ] = [
            'label' => self::t( 'Shows' ),
            'url'   => 'mediamanager/shows'
        ];

        $navigation[ 'subnav' ][ 'synchronize' ] = [
            'label' => self::t( 'Synchronize' ),
            'url'   => 'mediamanager/synchronize'
        ];

        if( SettingsHelper::get( 'mediaSection' ) ) {

            $navigation[ 'subnav' ][ 'entries' ] = [
                'label' => self::t( 'Entries' ),
                'url'   => 'mediamanager/entries'
            ];
        }

        $navigation[ 'subnav' ][ 'clean' ] = [
            'label' => self::t( 'Clean Garbage Entries' ),
            'url'   => 'mediamanager/clean'
        ];
				
				$navigation['subnav']['stale-media'] = [
					'label' => self::t('Manage Stale Media'),
					'url'   => 'mediamanager/stale-media'
				];

        $navigation[ 'subnav' ][ 'settings' ] = [
            'label' => self::t( 'Settings' ),
            'url'   => UrlHelper::cpUrl( 'settings/plugins/mediamanager' )
        ];

        return $navigation;
    }


    // Private Methods
    // =========================================================================

    private function registerRoutes()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {

                if( SettingsHelper::get( 'mediaSection' ) ) {
                    $event->rules[ 'mediamanager/entries' ] = 'mediamanager/main/entries';
                }

                $event->rules[ 'mediamanager/shows' ]               = 'mediamanager/show';
                $event->rules[ 'mediamanager/shows/<entryId:\d+>' ] = 'mediamanager/show';
                
                $event->rules[ 'mediamanager/synchronize' ]                    = 'mediamanager/synchronize';
                $event->rules[ 'mediamanager/synchronize/<entryId:\d+>' ]      = 'mediamanager/synchronize';
                $event->rules[ 'mediamanager/synchronize/all' ]                = 'mediamanager/synchronize/all';
                $event->rules[ 'mediamanager/synchronize/single' ]             = 'mediamanager/synchronize/single';
                $event->rules[ 'mediamanager/synchronize/show-entries' ]       = 'mediamanager/synchronize/show-entries';
                $event->rules[ 'mediamanager/synchronize/synchronize-show' ]   = 'mediamanager/synchronize/synchronize-show';
                $event->rules[ 'mediamanager/synchronize/synchronize-single' ] = 'mediamanager/synchronize/synchronize-single';
                $event->rules[ 'mediamanager/synchronize/synchronize-all' ]    = 'mediamanager/synchronize/synchronize-all';
                $event->rules[ 'mediamanager/synchronize/synchronize-show-entries' ] = 'mediamanager/synchronize/synchronize-show-entries';

                $event->rules[ 'mediamanager/clean' ] = 'mediamanager/synchronize/clean';
            }
        );
    }

    private function registerInstallationEvents()
    {
        // Before plugin installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_BEFORE_INSTALL_PLUGIN,
            function( PluginEvent $event ) {
                if( $event->plugin === $this ) {
                    // Make sure dependencies installed
                    DependencyHelper::installDependencies();
                }
            }
        );

        // After plugin installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function( PluginEvent $event ) {
                if( $event->plugin === $this ) {
                    SetupHelper::registerRequiredComponents();
                }
            }
        );

        // Before plugin uninstalled
        Event::on(
            Plugins::class,
            Plugins::EVENT_BEFORE_UNINSTALL_PLUGIN,
            function( PluginEvent $event ) {
                if( $event->plugin === $this ) {
                    SetupHelper::unregisterRequiredComponents();
                }
            }
        );
    }

    private function registerBehaviors()
    {
        // Attach behaviors
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function( Event $e ) {

                $variable = $e->sender;
                $variable->attachBehaviors([
                    MediaBehavior::class,
                ]);
            }
        );
    }

    private function registerPluginServices()
    {
        // Register service
        $this->setComponents([
            'show'        => ShowService::class,
            'api'         => ApiService::class,
            'oldsettings' => OldSettingsService::class,
        ]);
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): SettingsModel
    {
        return new SettingsModel();
    }
}
