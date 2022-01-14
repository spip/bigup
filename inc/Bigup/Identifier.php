<?php

namespace Spip\Bigup;

/**
 * Gère l'identification du formulaire
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/LogTrait');

/**
 * Gère l'identification du formulaire
 **/
class Identifier {
	use LogTrait;

	/**
	 * Login ou identifiant de l'auteur qui intéragit */
	private string $auteur = '';

	/**
	 * Nom du formulaire qui utilise flow */
	private string $formulaire = '';

	/**
	 * Hash des arguments du formulaire */
	private string $formulaire_args = '';

	/**
	 * Identifie un formulaire par rapport à un autre identique sur la même page ayant un appel différent. */
	private string $formulaire_identifiant = '';

	/**
	 * Nom du champ dans le formulaire qui utilise flow */
	private string $champ = '';

	/**
	 * Token de la forme `champ:time:cle`
	 **/
	private string $token = '';

	/**
	 * Expiration du token (en secondes)
	 *
	 * @todo À définir en configuration
	 * @var int En secondes
	 **/
	private int $token_expiration = 86400;

	/**
	 * Taille du fichier maximum
	 * @var int En Mo
	 */
	private int $max_size_file = 0;

	/**
	 * Constructeur
	 *
	 * @param string $formulaire
	 *     Nom du formulaire.
	 * @param string $formulaire_args
	 *     Hash du formulaire
	 * @param string $token
	 *     Jeton d'autorisation
	 **/
	public function __construct($formulaire = '', $formulaire_args = '', $token = '') {
		$this->token = $token;
		$this->formulaire = $formulaire;
		$this->formulaire_args = $formulaire_args;
		$this->identifier_auteur();
		$this->identifier_formulaire();
		if ($token) {
			$this->obtenir_champ_token();
		}
		$this->recuperer_configuration();
	}

	/**
	 * Constructeur depuis les arguments d'un pipeline
	 *
	 * Le tableau d'argument doit avoir 'form' et 'args'.
	 * La fonction recalcule le hash du formulaire, qui servira au constructeur normal.
	 *
	 * @param array $args Arguments du pipeline, généralement `$flux['args']`
	 * @return Identifier
	 */
	public static function depuisArgumentsPipeline($args) {
		// il nous faut le nom du formulaire et son hash
		// et pas de bol, le hash est pas envoyé dans les pipelines 'formulaires_xx'.
		// (il est calculé après charger). Alors on se recrée un hash pour nous.
		#$post = $args['je_suis_poste'];
		$form = $args['form'];
		// sauf dans le cas du pipeline `formulaire_fond`
		if (!empty($args['formulaire_args'])) {
			$formulaire_args = $args['formulaire_args'];
		} else {
			$args = $args['args'];
			array_unshift($args, $GLOBALS['spip_lang']);
			$formulaire_args = encoder_contexte_ajax($args, $form);
		}
		$identifier = new self($form, $formulaire_args);
		return $identifier;
	}

	/**
	 * Constructeur depuis les paramètres dans l'environnement posté.
	 * @return Identifier
	 */
	public static function depuisRequest() {
		$identifier = new self();
		$identifier->recuperer_parametres();
		return $identifier;
	}

	/**
	 * Pouvoir obtenir les propriétés privées sans les modifier.
	 * @param string $property
	 * @return mixed|null
	 */
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		}
		static::debug("Propriété `$property` demandée mais inexistante.");
		return null;
	}

	/**
	 * Pouvoir obtenir les propriétés privées sans les modifier.
	 * @param string $property
	 * @return bool
	 */
	public function __isset($property) {
		if (property_exists($this, $property)) {
			return isset($this->$property);
		}
		return false;
	}

	/**
	 * Définit certaines configurations
	 */
	public function recuperer_configuration() {
		#$this->token_expiration = 86800; // TODO
		$this->max_size_file = lire_config('bigup/max_size_file', 0);
	}

	/**
	 * Retrouve les paramètres pertinents pour gérer le test ou la réception de fichiers.
	 **/
	public function recuperer_parametres() {
		// obligatoires
		$this->token           = _request('bigup_token');
		$this->formulaire      = _request('formulaire_action');
		$this->formulaire_args = _request('formulaire_action_args');
		$this->identifier_formulaire();
		if ($this->token) {
			$this->obtenir_champ_token();
		}
	}

	/**
	 * Identifier l'auteur qui accède
	 *
	 * Retrouve un identifiant unique, même pour les auteurs anonymes.
	 * Si on connait l'auteur, on essaie de mettre un nom humain
	 * pour une meilleure visibilité du répertoire.
	 *
	 * Retourne un identifiant d'auteur :
	 * - {id_auteur}.{login} sinon
	 * - {id_auteur} sinon
	 * - 0.{session_id}
	 *
	 * @return string
	 **/
	public function identifier_auteur() {
		// un nom d'identifiant humain si possible
		include_spip('inc/session');
		$identifiant = session_get('id_auteur');
		$complement = '';
		// visiteur anonyme ? on prend un identifiant de session PHP.
		if (!$identifiant) {
			if (session_status() == PHP_SESSION_NONE) {
				session_start();
			}
			$complement = '_' . session_id();
		} elseif ($login = session_get('login')) {
			$complement = '_' . GestionRepertoires::nommer_repertoire($login);
		}
		return $this->auteur = $identifiant . strtolower($complement);
	}

	/**
	 * Calcule un identifiant de formulaire en fonction de ses arguments et du secret du site
	 *
	 * Lorsque le formulaire dispose d'une fonction 'identifier', on l'utilise,
	 * sinon tout changement dans les arguments, tel que passer tout l'ENV en option,
	 * ou la date du calcul créerait un nouvel identifiant à chaque affichage du formulaire.
	 * Le cas par exemple se présente sur le formulaire editer_logos.
	 *
	 * Dans notre cas ce n'est pas ce que l'on veut.
	 *
	 * @see \formulaire__identifier()
	 *
	 * @return string l'identifiant
	 **/
	public function identifier_formulaire() {
		include_spip('inc/securiser_action');
		if ($identifier_args = charger_fonction('identifier', 'formulaires/' . $this->formulaire, true)) {
			include_spip('inc/filtres');
			$args = decoder_contexte_ajax($this->formulaire_args, $this->formulaire);
			$identite = call_user_func_array($identifier_args, $args);
		} else {
			$identite = $this->formulaire_args;
		}
		return $this->formulaire_identifiant = substr(md5(secret_du_site() . $identite), 0, 6);
	}

	/**
	 * Récupère le champ du token
	 *
	 * @note
	 *     On permet de le calculer dès la construction de la classe,
	 *     avant même vérifier la validité du token.
	 *     Ça permet au constructeur du Cache d'avoir cette info directement,
	 *     sans utiliser de méthode supplémentaire après la vérification du token.
	 *
	 * @return bool True si le champ est trouvé.
	 */
	function obtenir_champ_token() {
		$_token = explode(':', $this->token, 2);
		if (count($_token) == 2) {
			$this->champ = reset($_token);
			return true;
		}
		return false;
	}

	/**
	 * Vérifier le token utilisé
	 *
	 * Le token doit arriver, de la forme `champ:time:clé`
	 * De même que formulaire_action et formulaire_action_args
	 *
	 * Le temps ne doit pas être trop vieux d'une part,
	 * et la clé de sécurité doit évidemment être valide.
	 *
	 * @return bool
	 **/
	public function verifier_token() {
		if (!$this->token) {
			static::debug('Aucun token');
			return false;
		}

		$_token = explode(':', $this->token);

		if (count($_token) != 3) {
			static::debug('Token mal formé');
			return false;
		}

		[$champ, $time, $cle] = $_token;
		$time = intval($time);
		$now = time();


		if (($now - $time) > $this->token_expiration) {
			static::log('Token expiré');
			return false;
		}

		if (!$this->formulaire) {
			static::log('Vérifier token : nom du formulaire absent');
			return false;
		}

		if (!$this->formulaire_args) {
			static::log('Vérifier token : hash du formulaire absent');
			return false;
		}

		include_spip('inc/securiser_action');
		if (!verifier_action_auteur("bigup/$this->formulaire/$this->formulaire_args/$champ/$time", $cle)) {
			static::error('Token invalide');
			return false;
		}

		$this->champ = $champ;

		static::debug("Token OK : formulaire $this->formulaire, champ $champ, identifiant $this->formulaire_identifiant");

		return true;
	}
}
