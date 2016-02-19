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

	/**
	 *
	 * @var \Composer\IO\ConsoleIO
	 */
	protected $io;

	/**
	 * Apply plugin modifications to composer
	 *
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer	 = $composer;
		$this->io		 = $io;

		$installer = new WordPressCoreInstaller( $io, $composer );
		$composer->getInstallationManager()->addInstaller( $installer );
	}

	public static function getSubscribedEvents() {
		return array(
			ScriptEvents::POST_INSTALL_CMD	 => array(
				array( 'onPostInstallUpdate', 0 )
			),
			ScriptEvents::POST_UPDATE_CMD	 => array(
				array( 'onPostInstallUpdate', 0 )
			),
		);
	}

	public function onPostInstallUpdate( Event $event ) {
		$extra = $event->getComposer()->getPackage()->getExtra();

		if ( isset( $extra[ 'installer-paths' ] ) ) {
			foreach ( $extra[ 'installer-paths' ] AS $installerPath => $installerPathConfig ) {
				if ( in_array( 'type:wordpress-muplugin', $installerPathConfig ) ) {
					$baseDir		 = getcwd();
					$muInstallerPath = $baseDir . DIRECTORY_SEPARATOR . dirname( $installerPath );
					break;
				}
			}
		}
		if ( file_exists( $muInstallerPath ) ) {
			foreach ( new \DirectoryIterator( $muInstallerPath ) AS $directoryNode ) {
				if ( !$directoryNode->isDot() && $directoryNode->isDir() ) {
					$muPluginDirectory	 = $directoryNode->getPathname();
					$muBootstrapableFile = $this->checkForPluginFile( $muPluginDirectory );
					if ( !$muBootstrapableFile ) {
						$this->io->writeError( 'Psycle wordpress-core-installer error: Unable to create MU Plugin Bootstrap file for ' . $muPluginDirectory );
						continue;
					}
					$muBootstrapFile = dirname( $muPluginDirectory ) . DIRECTORY_SEPARATOR . '_' . basename( $muPluginDirectory ) . '.php';
					if ( file_exists( $muBootstrapableFile ) ) {
						$relativeFilePath = 'dirname(__FILE__) . DIRECTORY_SEPARATOR . "' . $directoryNode->getFilename() . '" . DIRECTORY_SEPARATOR . "' . basename( $muBootstrapableFile ) . '"';
						file_put_contents( $muBootstrapFile, '<?php if(file_exists(' . $relativeFilePath . ')) require_once(' . $relativeFilePath . ');' );
						$this->io->writeError( 'Psycle wordpress-core-installer: PsycleCreated MU Plugin Bootstrap file for ' . $muPluginDirectory );
					}
				}
			}
		}
	}

	public function checkForPluginFile( $directory ) {
		foreach ( new \DirectoryIterator( $directory ) AS $directoryNode ) {
			if ( $directoryNode->getExtension() == 'php' ) {
				$fileData = $this->get_file_data($directoryNode->getPathname());
				if(!empty($fileData['Name'])) {
					$this->io->write( 'Psycle wordpress-core-installer: Found plugin file ' . $directoryNode->getPathname() . ' for MU plugin ' . $fileData['Name'] );
					return $directoryNode->getPathname();
				}
			}
		}
		return false;
	}

	/**
	 * Retrieve metadata from a file.
	 *
	 * Searches for metadata in the first 8kiB of a file, such as a plugin or theme.
	 * Each piece of metadata must be on its own line. Fields can not span multiple
	 * lines, the value will get cut at the end of the first line.
	 *
	 * If the file data is not within that first 8kiB, then the author should correct
	 * their plugin file and move the data headers to the top.
	 *
	 * @link https://codex.wordpress.org/File_Header
	 *
	 * @since 2.9.0
	 *
	 * @param string $file            Path to the file.
	 * @param array  $default_headers List of headers, in the format array('HeaderKey' => 'Header Name').
	 * @param string $context         Optional. If specified adds filter hook "extra_{$context}_headers".
	 *                                Default empty.
	 * @return array Array of file headers in `HeaderKey => Header Value` format.
	 */
	function get_file_data( $file ) {
		// We don't need to write to the file, so just open for reading.
		$fp = fopen( $file, 'r' );

		// Pull only the first 8kiB of the file in.
		$file_data = fread( $fp, 8192 );

		// PHP will close file handle, but we are good citizens.
		fclose( $fp );

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );

		$all_headers = array(
			'Name' => 'Plugin Name',
			'PluginURI' => 'Plugin URI',
			'Version' => 'Version',
			'Description' => 'Description',
			'Author' => 'Author',
			'AuthorURI' => 'Author URI',
			'TextDomain' => 'Text Domain',
			'DomainPath' => 'Domain Path',
			'Network' => 'Network',
			// Site Wide Only is deprecated in favor of Network.
			'_sitewide' => 'Site Wide Only',
		);

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[ 1 ] ) {
				$all_headers[ $field ]	 = trim(preg_replace("/\s*(?:\*\/|\?>).*/", '',  $match[ 1 ]));
			}
			else {
				$all_headers[ $field ]	 = '';
			}
				
		}

		return $all_headers;
	}

}
