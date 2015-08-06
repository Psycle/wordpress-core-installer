<?php

namespace psycle\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Script\ScriptEvents;
use Composer\Script\Event;

class WordPressCorePlugin implements PluginInterface, EventSubscriberInterface {

	protected $composer;
    protected $io;
	
	/**
	 * Apply plugin modifications to composer
	 *
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
        $this->io = $io;
		
		$installer = new WordPressCoreInstaller( $io, $composer );
		$composer->getInstallationManager()->addInstaller( $installer );		
	}
	
	public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => array(
                array('onPostInstallUpdate', 0)
            ),
			ScriptEvents::POST_UPDATE_CMD => array(
                array('onPostInstallUpdate', 0)
            ),
        );
    }
	
	public function onPostInstallUpdate(Event $event) {
		$extra = $event->getComposer()->getPackage()->getExtra();
		
		if(isset($extra['installer-paths'])) {
			foreach ($extra['installer-paths'] AS $installerPath => $installerPathConfig) {				
				if(  in_array( 'type:wordpress-muplugin', $installerPathConfig )) {
					$baseDir = getcwd();
					$muInstallerPath = $baseDir . DIRECTORY_SEPARATOR . dirname($installerPath);
					break;
				}
			}
		}
		foreach ( new \DirectoryIterator($muInstallerPath) AS $directoryNode) {
			if(!$directoryNode->isDot() && $directoryNode->isDir()) {
				$muPluginDirectory = $directoryNode->getPathname();
				$muBootstrapFile = dirname($muPluginDirectory) . '_' . basename($muPluginDirectory) . '.php';

				foreach (new \DirectoryIterator($muPluginDirectory) AS $muBootstrapableFile) {
					if($muBootstrapableFile->isFile() && preg_match( '@\.php$@', $muBootstrapableFile )) {
						$relativeFilePath = '__DIR__ . "/' . $directoryNode->getFilename() . '/' . $muBootstrapableFile->getFilename() . '"';
						file_put_contents($muBootstrapFile, '<?php if(file_exists('.$relativeFilePath.')) require_once('.$relativeFilePath.');');
					}
				}
			}
		}
		
		
	}
}
