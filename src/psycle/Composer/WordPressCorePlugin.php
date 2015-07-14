<?php

namespace psycle\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use \Composer\Installer\PackageEvent;

class WordPressCorePlugin implements PluginInterface {

	/**
	 * Apply plugin modifications to composer
	 *
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$installer = new WordPressCoreInstaller( $io, $composer );
		$composer->getInstallationManager()->addInstaller( $installer );		
	}
	
	public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_PACKAGE_INSTALL => array(
                array('onPreFileDownload', 0)
            ),
			ScriptEvents::POST_PACKAGE_UPDATE => array(
				array('onPostPackageUpdate', 0)
			),
        );
    }
	
	public function onPostPackageUpdate(PackageEvent $event) {
		var_dump($event->getName());
		
		die(var_dump($event));
	}
}
