<?php
/**
 * ownCloud
 *
 * @author Sam Tuke, Frank Karlitschek
 * @copyright 2012 Sam Tuke samtuke@owncloud.com, 
 * Frank Karlitschek frank@owncloud.org
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// Todo:
//  - Crypt/decrypt button in the userinterface
//  - Setting if crypto should be on by default
//  - Add a setting "Don´t encrypt files larger than xx because of performance reasons"
//  - Transparent decrypt/encrypt in filesystem.php. Autodetect if a file is encrypted (.encrypted extension)
//  - Don't use a password directly as encryption key. but a key which is stored on the server and encrypted with the user password. -> password change faster
//  - IMPORTANT! Check if the block lenght of the encrypted data stays the same

namespace OCA\Encryption;

/**
 * @brief Class for utilities relating to encrypted file storage system
 * @param $view OC_FilesystemView object, expected to have OC '/' as root path
 * @param $client flag indicating status of client side encryption. Currently
 * unused, likely to become obsolete shortly
 */

class Util {
	
	
	# Web UI:
	
	## DONE: files created via web ui are encrypted
	## DONE: file created & encrypted via web ui are readable in web ui
	## DONE: file created & encrypted via web ui are readable via webdav
	
	
	# WebDAV:
	
	## DONE: new data filled files added via webdav get encrypted
	## DONE: new data filled files added via webdav are readable via webdav
	## DONE: reading unencrypted files when encryption is enabled works via webdav
	## DONE: files created & encrypted via web ui are readable via webdav
	
	
	# Legacy support:
	
	## DONE: add method to check if file is encrypted using new system
	## DONE: add method to check if file is encrypted using old system
	## DONE: add method to fetch legacy key
	## DONE: add method to decrypt legacy encrypted data
	
	## TODO: add method to encrypt all user files using new system
	## TODO: add method to decrypt all user files using new system
	## TODO: add method to encrypt all user files using old system
	## TODO: add method to decrypt all user files using old system
	
	
	# Admin UI:
	
	## DONE: changing user password also changes encryption passphrase
	
	## TODO: add support for optional recovery in case of lost passphrase / keys
	## TODO: add admin optional required long passphrase for users
	## TODO: add UI buttons for encrypt / decrypt everything
	## TODO: implement flag system to allow user to specify encryption by folder, subfolder, etc.
	
	
	# Sharing:
	
	## TODO: add support for encrypting to multiple public keys
	## TODO: add support for decrypting to multiple private keys
	
	
	# Integration testing:
	
	## TODO: test new encryption with webdav
	## TODO: test new encryption with versioning
	## TODO: test new encryption with sharing
	## TODO: test new encryption with proxies
	
	
	private $view; // OC_FilesystemView object for filesystem operations
	private $userId; // ID of the currently logged-in user
	private $pwd; // User Password
	private $client; // Client side encryption mode flag
	private $publicKeyDir; // Dir containing all public user keys
	private $encryptionDir; // Dir containing user's files_encryption
	private $keyfilesPath; // Dir containing user's keyfiles
	private $shareKeysPath; // Dir containing env keys for shared files
	private $publicKeyPath; // Path to user's public key
	private $privateKeyPath; // Path to user's private key

	public function __construct( \OC_FilesystemView $view, $userId, $client = false ) {
	
		$this->view = $view;
		$this->userId = $userId;
		$this->client = $client;
		$this->userDir =  '/' . $this->userId;
		$this->userFilesDir =  '/' . $this->userId . '/' . 'files';
		$this->publicKeyDir =  '/' . 'public-keys';
		$this->encryptionDir =  '/' . $this->userId . '/' . 'files_encryption';
		$this->keyfilesPath = $this->encryptionDir . '/' . 'keyfiles';
		$this->shareKeysPath = $this->encryptionDir . '/' . 'share-keys';
		$this->publicKeyPath = $this->publicKeyDir . '/' . $this->userId . '.public.key'; // e.g. data/public-keys/admin.public.key
		$this->privateKeyPath = $this->encryptionDir . '/' . $this->userId . '.private.key'; // e.g. data/admin/admin.private.key
		
	}
	
	public function ready() {
		
		if( 
		!$this->view->file_exists( $this->encryptionDir )
		or !$this->view->file_exists( $this->keyfilesPath )
		or !$this->view->file_exists( $this->shareKeysPath )
		or !$this->view->file_exists( $this->publicKeyPath )
		or !$this->view->file_exists( $this->privateKeyPath ) 
		) {
		
			return false;
			
		} else {
		
			return true;
			
		}
	
	}
	
        /**
         * @brief Sets up user folders and keys for serverside encryption
         * @param $passphrase passphrase to encrypt server-stored private key with
         */
	public function setupServerSide( $passphrase = null ) {
		
		// Create user dir
		if( !$this->view->file_exists( $this->userDir ) ) {
		
			$this->view->mkdir( $this->userDir );
		
		}
		
		// Create user files dir
		if( !$this->view->file_exists( $this->userFilesDir ) ) {
		
			$this->view->mkdir( $this->userFilesDir );
		
		}
		
		// Create shared public key directory
		if( !$this->view->file_exists( $this->publicKeyDir ) ) {
		
			$this->view->mkdir( $this->publicKeyDir );
		
		}
		
		// Create encryption app directory
		if( !$this->view->file_exists( $this->encryptionDir ) ) {
		
			$this->view->mkdir( $this->encryptionDir );
		
		}
		
		// Create mirrored keyfile directory
		if( !$this->view->file_exists( $this->keyfilesPath ) ) {
		
			$this->view->mkdir( $this->keyfilesPath );
		
		}

		// Create mirrored share env keys directory
		if( !$this->view->file_exists( $this->shareKeysPath ) ) {
		
			$this->view->mkdir( $this->shareKeysPath );
		
		}
		
		// Create user keypair
		if ( 
		! $this->view->file_exists( $this->publicKeyPath ) 
		or ! $this->view->file_exists( $this->privateKeyPath ) 
		) {
		
			// Generate keypair
			$keypair = Crypt::createKeypair();
			
			\OC_FileProxy::$enabled = false;
			
			// Save public key
			$this->view->file_put_contents( $this->publicKeyPath, $keypair['publicKey'] );
			
			// Encrypt private key with user pwd as passphrase
			$encryptedPrivateKey = Crypt::symmetricEncryptFileContent( $keypair['privateKey'], $passphrase );
			
			// Save private key
			$this->view->file_put_contents( $this->privateKeyPath, $encryptedPrivateKey );
			
			\OC_FileProxy::$enabled = true;
			
		}
		
		$publicKey = Keymanager::getPublicKey( $this->view, $this->userId );
		
		// Encrypt existing user files:
		$this->encryptAll( $publicKey, $this->userFilesDir );
		
		return true;
	
	}
	
	/**
	 * @brief Find all files and their encryption status within a directory
	 * @param string $directory The path of the parent directory to search
	 * @return mixed false if 0 found, array on success. Keys: name, path
	 */
	public function findFiles( $directory ) {
		
		// Disable proxy - we don't want files to be decrypted before
		// we handle them
		\OC_FileProxy::$enabled = false;
		
		$found = array( 'plain' => array(), 'encrypted' => array(), 'legacy' => array() );
		
		if ( 
		$this->view->is_dir( $directory ) 
		&& $handle = $this->view->opendir( $directory ) 
		) {
		
			while ( false !== ( $file = readdir( $handle ) ) ) {
				
				if (
				$file != "." 
				&& $file != ".."
				) {
					
					$filePath = $directory . '/' . $this->view->getRelativePath( '/' . $file );
					
					// If the path is a directory, search 
					// its contents
					if ( $this->view->is_dir( $filePath ) ) { 
						
						$this->findFiles( $filePath );
					
					// If the path is a file, determine 
					// its encryption status
					} elseif ( $this->view->is_file( $filePath ) ) {
						
						// Disable proxies again, some-
						// how they get re-enabled :/
						\OC_FileProxy::$enabled = false;
						
						// If the file is encrypted
						if ( Keymanager::getFileKey( $this->view, $this->userId, $file ) ) {
						
							$found['encrypted'][] = array( 'name' => $file, 'path' => $filePath );
						
						// If the file uses old 
						// encryption system
						} elseif (  Crypt::isLegacyEncryptedContent( $this->view->file_get_contents( $filePath ) ) ) {
							
							$found['legacy'][] = array( 'name' => $file, 'path' => $filePath );
							
						// If the file is not encrypted
						} else {
						
							$found['plain'][] = array( 'name' => $file, 'path' => $filePath );
						
						}
					
					}
					
				}
				
			}
			
			\OC_FileProxy::$enabled = true;
			
			if ( empty( $found ) ) {
			
				return false;
			
			} else {
			
				return $found;
			
			}
		
		}
		
		\OC_FileProxy::$enabled = true;
		
		return false;

	}
	
        /**
         * @brief Check if a given path identifies an encrypted file
         * @return true / false
         */
	public function isEncryptedPath( $path ) {
	
		// Disable encryption proxy so data retreived is in its 
		// original form
		\OC_FileProxy::$enabled = false;
	
		$data = $this->view->file_get_contents( $path );
		
		\OC_FileProxy::$enabled = true;
		
		return Crypt::isCatfile( $data );
	
	}
	
	/**
	 * @brief Encrypt all files in a directory
	 * @param string $publicKey the public key to encrypt files with
	 * @param string $dirPath the directory whose files will be encrypted
	 * @note Encryption is recursive
	 */
	public function encryptAll( $publicKey, $dirPath ) {
	
		if ( $found = $this->findFiles( $dirPath ) ) {
		
			// Encrypt unencrypted files
			foreach ( $found['plain'] as $plainFilePath ) {
			
				// Fetch data from file
				$plainData = $this->view->file_get_contents( $plainFilePath );
				
				// Encrypt data, generate catfile
				$encrypted = Crypt::keyEncryptKeyfile( $plainData, $publicKey );
				
				// Save catfile
				Keymanager::setFileKey( $this->view, $plainFilePath, $this->userId, $encrypted['key'] );
				
				// Overwrite the existing file with the encrypted one
				$this->view->file_put_contents( $plainFilePath, $encrypted['data'] );
			
			}
			
			// FIXME: Legacy recrypting here isn't finished yet
			// Encrypt legacy encrypted files
			foreach ( $found['legacy'] as $legacyFilePath ) {
			
				// Fetch data from file
				$legacyData = $this->view->file_get_contents( $legacyFilePath );
			
				// Recrypt data, generate catfile
				$recrypted = Crypt::legacyKeyRecryptKeyfile( $legacyData, $legacyPassphrase, $publicKey, $newPassphrase );
				
				// Save catfile
				Keymanager::setFileKey( $this->view, $plainFilePath, $this->userId, $recrypted['key'] );
				
				// Overwrite the existing file with the encrypted one
				$this->view->file_put_contents( $plainFilePath, $recrypted['data'] );
			
			}
		
		}
		
	}
	
	public function getPath( $pathName ) {
	
		switch ( $pathName ) {
			
			case 'publicKeyDir':
			
				return $this->publicKeyDir;
				
				break;
				
			case 'encryptionDir':
			
				return $this->encryptionDir;
				
				break;
				
			case 'keyfilesPath':
			
				return $this->keyfilesPath;
				
				break;
				
			case 'publicKeyPath':
			
				return $this->publicKeyPath;
				
				break;
				
			case 'privateKeyPath':
			
				return $this->privateKeyPath;
				
				break;
			
		}
		
	}

}
