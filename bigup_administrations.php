<?php

/**
 * Fichier gérant l'installation et désinstallation du plugin Big Upload
 *
 * @plugin     Big Upload
 * @copyright  2015
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Installation
 */

if (!defined('_ECRIRE_INC_VERSION')) { return;
}


/**
 * Fonction d'installation et de mise à jour du plugin Big Upload.
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @param string $version_cible
 *     Version du schéma de données dans ce plugin (déclaré dans paquet.xml)
 * @return void
**/
function bigup_upgrade($nom_meta_base_version, $version_cible) {
	$maj = [];

	$max_file_size_php = 0;
	include_spip('medias_fonctions');
	if (function_exists('medias_inigetoctets')) {
		$max_file_size_php = medias_inigetoctets('upload_max_filesize') / (1024 * 1024);
	}

	// Configuration par défaut
	$config_defaut = [
		'max_file_size' => max($max_file_size_php, 10),
	];

	$maj['create'] = [['ecrire_meta', 'bigup', serialize($config_defaut)]];

	$maj['1.0.1'] = [['ecrire_meta', 'bigup', serialize($config_defaut)]];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}


/**
 * Fonction de désinstallation du plugin Big Upload.
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @return void
**/
function bigup_vider_tables($nom_meta_base_version) {
	effacer_meta($nom_meta_base_version);
}
