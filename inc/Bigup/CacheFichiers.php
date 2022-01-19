<?php

namespace Spip\Bigup;

use Spip\Bigup\CacheRepertoire;
use Spip\Bigup\Identifier;

/**
 * Gère le cache des fichiers dans tmp/bigupload
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */


/**
 * Gère le cache des fichiers dans tmp/bigupload
 *
 **/
class CacheFichiers {
	use LogTrait;

	/**
	 * Cache racine du stockage */
	private ?CacheRepertoire $cache = null;

	/**
	 * Identification du formulaire */
	private ?Identifier $identifier = null;

	/**
	 * Nom du champ */
	private ?string $champ = null;


	/**
	 * Constructeur
	 *
	 * @param CacheRepertoire $cache
	 *     Cache des fichiers pour ce formulaire
	 * @param string $champ
	 *     Nom du champ (valeur de l'attribut name) pour ces fichiers
	 */
	public function __construct(CacheRepertoire $cache, $champ) {
		$this->cache = $cache;
		$this->identifier = $this->cache->cache->identifier;
		$this->champ = $champ;
	}

	/**
	 * Retourne le chemin du cache pour ce champ du formulaire
	 * @return string
	 */
	public function dir_champ() {
		// le nom du champ n'est pas falsifiable, il vient du token.
		// On l'utilise directement malgré les [] présents
		return $this->cache->dir . DIRECTORY_SEPARATOR . $this->champ;
	}

	/**
	 * Retourne le chemin du répertoire cache pour cet identifiant de fichier du formulaire
	 * @return string
	 */
	public function dir_identifiant($identifiant) {
		return $this->dir_champ()
			. DIRECTORY_SEPARATOR
			. static::hash_identifiant($identifiant);
	}

	/**
	 * Retourne le chemin du répertoire cache pour cet identifiant de fichier et nom ce fichier du formulaire
	 * @return string
	 */
	public function dir_fichier($identifiant, $fichier) {
		return $this->dir_identifiant($identifiant)
			. DIRECTORY_SEPARATOR
			. GestionRepertoires::nommer_repertoire($fichier);
	}

	/**
	 * Retourne le chemin du fichier cache pour cet identifiant de fichier et nom ce fichier du formulaire
	 * @param string $identifiant
	 * @param stiring $fichier
	 * @return string
	 */
	public function path_fichier($identifiant, $fichier) {
		return $this->dir_fichier($identifiant, $fichier)
			. DIRECTORY_SEPARATOR
			. 'file';
	}

	/**
	 * Retourne le nom du répertoire / hash relatif à l'identifiant de fichier indiqué.
	 *
	 * Si l'identifiant transmis est déjà un hash, le retourne directement
	 *
	 * @param string $identifiant
	 * @return string
	 */
	public static function hash_identifiant($identifiant) {
		if (
			strlen($identifiant) == 10
			and $identifiant[0] == '@'
			and $identifiant[9] == '@'
			and ctype_xdigit(substr($identifiant, 1, -1))
		) {
			return $identifiant;
		}
		return '@' . substr(md5($identifiant), 0, 8) . '@';
	}


	/**
	 * Retoune le chemin du fichier qui stocke les descriptions d'un fichier dans le cache.
	 * @param string $chemin
	 * @return string
	 */
	public static function chemin_description($chemin) {
		return $chemin . '.bigup.json';
	}

	/**
	 * Indique si ce nom de fichier est un fichier de description
	 * @param string $nom
	 * @return bool true si c'en est un, false sinon.
	 */
	public static function est_fichier_description($nom) {
		return substr($nom, -strlen('.bigup.json')) == '.bigup.json';
	}

	/**
	 * Retourne la description d'un fichier dont le chemin est indiqué
	 *
	 * Cette description est sauvegardée à côté du fichier lors de son
	 * enregistrement dans le cache.
	 *
	 * @uses lire_description_fichier()
	 * @param string $chemin
	 *     Chemin du fichier dans le cache de bigup.
	 * @return array|false
	 *     Description si retrouvée, sinon false
	 **/
	public static function obtenir_description_fichier($chemin) {
		$description = self::lire_description_fichier($chemin);
		if ($description) {
			self::debug('* Description de : ' . $description['name'] . ' (' . $chemin . ')');
		} else {
			self::debug('* Description introuvable pour : ' . $chemin);
		}
		return $description;
	}


	/**
	 * Décrire un fichier (comme dans `$_FILES`)
	 *
	 * @uses decrire_fichier_chemin()
	 * @param string $identifiant
	 *     Identifiant du fichier dans le cache
	 * @param array $infos
	 *     Description du fichier tel que $_FILES le fournit.
	 *     Tableau [ cle => valeur] avec pour clés : name, type, tmp_name, size, error
	 * @return array|false
	 *     Description du fichier, complétée. False si erreur.
	 **/
	public function decrire_fichier($identifiant, $infos) {
		if (!is_array($infos)) {
			self::error('Infos non transmises pour décrire le fichier : ' . $identifiant);
			return false;
		}
		if (empty($infos['tmp_name'])) {
			self::error('Chemin du fichier absent pour décrire le fichier : ' . $identifiant);
			return false;
		}
		$chemin = $infos['tmp_name'];
		if (empty($infos['name'])) {
			self::error('Nom original du fichier absent pour décrire le fichier : ' . $chemin);
			return false;
		}
		if (empty($this->champ)) {
			self::error("Valeur de l'attribut 'name' absente pour décrire le fichier : " . $chemin);
			return false;
		}
		if (empty($this->identifier->formulaire_identifiant)) {
			self::error('Identifiant de formulaire absent pour décrire le fichier : ' . $chemin);
			return false;
		}

		$desc = self::decrire_fichier_description($infos, [
			'formulaire' => $this->identifier->formulaire,
			'formulaire_args' => $this->identifier->formulaire_args,
			'formulaire_identifiant' => $this->identifier->formulaire_identifiant,
			'champ' => $this->champ,
			'identifiant' => CacheFichiers::hash_identifiant($identifiant),
		]);

		if ($desc and self::ecrire_description_fichier($chemin, $desc)) {
			return $desc;
		} else {
			return false;
		}
	}


	/**
	 * Décrire un fichier (comme dans `$_FILES`)
	 *
	 * @param array $infos
	 *     Description du fichier tel que $_FILES le fournit.
	 *     Tableau [ cle => valeur] avec pour clés : name, type, tmp_name, size, error
	 * @return array|false
	 *     Description du fichier, complétée. False si erreur.
	 **/
	public static function decrire_fichier_description($infos, $bigup) {
		if (!$infos or empty($infos['name']) or empty($infos['tmp_name'])) {
			return false;
		}
		$chemin = $infos['tmp_name'];
		if (!file_exists($chemin)) {
			self::error('Fichier introuvable pour description : ' . $chemin);
			return false;
		}

		$obligatoires = [
			'champ',
			'identifiant',
			'formulaire',
			'formulaire_args',
			'formulaire_identifiant',
		];

		if ($diff = array_diff_key(array_flip($obligatoires), $bigup)) {
			self::error('Description manquante dans (' . implode(',', $diff) . ') : ' . $chemin);
			return false;
		}

		$error = 0;
		$size = filesize($chemin);
		if (!empty($infos['size']) and $size != $infos['size']) {
			if ($size <= $infos['size']) {
				$error = UPLOAD_ERR_PARTIAL; // partially uploaded
			} else {
				$error = 99; // erreur de bigup ?
			}
		}

		$extension = pathinfo($infos['name'], PATHINFO_EXTENSION);

		include_spip('action/ajouter_documents');
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$type = finfo_file($finfo, $chemin);
		finfo_close($finfo);
		$extension = corriger_extension(strtolower($extension));
		$desc = [
			// présent dans $_FILES
			'name' => $infos['name'],
			'tmp_name' => $chemin,
			'size' => $size,
			'type' => $type,
			'error' => $error,
			// informations supplémentaires (pas dans $_FILES habituellement)
			'bigup' => $bigup + [
				'extension' => $extension,
				'pathname' => $chemin,
			]
		];

		// calcul d'une miniature
		if ($vignette = self::creer_vignette($desc, 200, 200)) {
			$desc['bigup']['vignette'] = $vignette;
		}

		return $desc;
	}

	/**
	 * Sauvegarde la description du fichier
	 *
	 * @param string $chemin
	 * @param array $description
	 * @return bool
	 */
	public static function ecrire_description_fichier($chemin, $description) {
		$cache = self::chemin_description($chemin);
		$json = json_encode($description, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (json_last_error()) {
			return false;
		}
		ecrire_fichier($cache, $json);
		return true;
	}

	/**
	 * Lit une description de fichier sauvegardée
	 *
	 * @param string $chemin
	 * @return array|bool
	 */
	public static function lire_description_fichier($chemin) {
		$json = null;
		$cache = self::chemin_description($chemin);
		if (!lire_fichier($cache, $json)) {
			return false;
		}
		$description = json_decode($json, true);
		if ($error = json_last_error()) {
			self::error("Erreur de lecture JSON ($error) pour : " . $chemin);
			return false;
		}
		return $description;
	}

	/**
	 * Crée une vignette à partir d'un chemin et retourne un base64 de l'image générée.
	 *
	 * @param array|string $desc
	 *     Description d'un fichier
	 * @param int $width
	 * @param int $height
	 * @return array|false
	 *     False si vignette non calculée, tableau avec clés : 'width', 'height', 'data'
	 */
	public static function creer_vignette($desc, $width, $height) {
		$vignette = null;
		// description de fichier bigup / files
		if (
			!is_array($desc)
			or empty($desc['tmp_name'])
			or empty($desc['type'])
			or empty($desc['size'])
		) {
			return false;
		}

		$source = $desc['tmp_name'];
		$type = $desc['type'];
		$size = $desc['size'];

		if (
			strpos($type, 'image/') === 0
			and $size <= 10 * 1024 * 1024 // 10Mo max.
		) {
			// enlever le charset éventuel et obtenir l'extension.
			$_type = explode(';', substr($type, 6));
			$extension = reset($_type);
			if (in_array($extension, ['jpeg', 'png', 'gif'])) {
				include_spip('inc/filtres');
				include_spip('inc/filtres_images_mini');
				// il faut l'extension dans le chemin pour les filtres d'images… pff
				self::debug('Calcul d\'une vignette pour' . $source);
				if (!file_exists($source)) {
					self::debug('Image absente pour vignette : ' . $source);
					return false;
				}
				rename($source, $image = $source . '.' . $extension);
				$img = image_reduire($image, $width, $height);
				rename($image, $source);

				if (!$img or $img == $image) {
					return false;
				}

				$src = extraire_attribut($img, 'src');
				$src = supprimer_timestamp($src);
				$width = extraire_attribut($img, 'width');
				$height = extraire_attribut($img, 'height');
				lire_fichier($src, $vignette);
				$vignette = 'data:image/' . $extension . ';base64,' . base64_encode($vignette);
				supprimer_fichier($src);

				return [
					'width' => $width,
					'height' => $height,
					'data' => $vignette
				];
			}
		}

		return false;
	}
}
