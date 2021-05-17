<?php
/**
 * Utilisations de pipelines par Big Upload
 *
 * @plugin     Big Upload
 * @copyright  2015
 * @author     Matthieu Marcillaud
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Charger des scripts jquery
 *
 * @pipeline jquery_plugins
 * @param array $scripts Liste à charger
 * @return array Liste complétée
**/
function bigup_jquery_plugins($scripts) {
	include_spip('inc/config');
	if (test_espace_prive() or lire_config('bigup/charger_public', false)) {
		$scripts[] = 'javascript/bigup.utils.js';
		$scripts[] = produire_fond_statique('javascript/bigup.trads.js', [
			'lang' => $GLOBALS['spip_lang'],
		]);
		$scripts[] = 'lib/flow/flow.js';
		$scripts[] = 'javascript/bigup.js';
		$scripts[] = 'javascript/bigup.loader.js';
	}
	return $scripts;
}

/**
 * Charger des JS dans l’espace public
 *
 * @pipeline insert_head_css
 * @param string $flux Code html des styles CSS à charger
 * @return string Code html complété
 **/
function bigup_insert_head($flux) {
	include_spip('inc/config');
	if (lire_config('bigup/charger_public', false)) {
		$flux = bigup_header_prive($flux);
	}
	return $flux;
}

/**
 * Charger des JS dans l’espace prive
 *
 * @pipeline insert_head_css
 * @param string $flux Code html des styles CSS à charger
 * @return string Code html complété
 **/
function bigup_header_prive($flux) {
	include_spip('inc/config');
	$maxFileSize = intval(lire_config('bigup/max_file_size', 0));
	$formatLogos = json_encode($GLOBALS['formats_logos']);
	$flux .= <<<EOS
<script type='text/javascript'>jQuery.bigup_config = {maxFileSize: $maxFileSize, formatsLogos: $formatLogos}</script>
EOS;
	return $flux;
}

/**
 * Charger des styles CSS dans l’espace public
 *
 * @pipeline insert_head_css
 * @param string $flux Code html des styles CSS à charger
 * @return string Code html complété
**/
function bigup_insert_head_css($flux) {
	include_spip('inc/config');
	if (lire_config('bigup/charger_public', false)) {
		$flux = bigup_header_prive_css($flux);
	}
	return $flux;
}

/**
 * Charger des styles CSS dans l'espace privé
 *
 * @pipeline insert_head_css
 * @param string $flux Code html des styles CSS à charger
 * @return string Code html complété
**/
function bigup_header_prive_css($flux) {
	$flux .= '<link rel="stylesheet" href="' . produire_fond_statique('css/vignettes.css') . '" type="text/css" />' . "\n";
	$flux .= '<link rel="stylesheet" href="' . timestamp(find_in_path('css/bigup.css')) . '" type="text/css" />' . "\n";
	return $flux;
}


/**
 * Obtenir une instance de la classe bigup pour ce formulaire
 *
 * @param array $flux
 *     Flux, tel que présent dans les pipelines des formulaires CVT
 * @return \SPIP\Bigup\Bigup()
**/
function bigup_get_bigup($flux) {
	include_spip('inc/Bigup');
	$bigup = new \Spip\Bigup\Bigup(
		\Spip\Bigup\Identifier::depuisArgumentsPipeline($flux['args'])
	);
	return $bigup;
}

/**
 * Recherche de fichiers uploadés pour ce formulaire
 *
 * La recherche est conditionné par la présence dans le contexte
 * de la clé `_bigup_rechercher_fichiers`. Ceci permet d'éviter de chercher
 * des fichiers pour les formulaires qui n'en ont pas besoin.
 *
 * Réinsère les fichiers déjà présents pour ce formulaire
 * dans `$_FILES` (a priori assez peu utile dans le chargement)
 * et ajoute la description des fichiers présents pour chaque champ,
 * dans l'environnement.
 *
 * Ajoute également un hidden, qui s'il est posté, demandera à recréer `$_FILES`
 * juste avant la fonction verifier(). Voir `bigup_formulaire_receptionner()`
 *
 * @see bigup_formulaire_receptionner():
 * @param array $flux
 * @return array
**/
function bigup_formulaire_charger($flux) {

	if (empty($flux['data']['_bigup_rechercher_fichiers'])) {
		return $flux;
	}

	// s'il y a des fichiers pour ce formulaire / visiteur, on ajoute la liste à l'environnement.
	$bigup = bigup_get_bigup($flux);
	if ($fichiers = $bigup->retrouver_fichiers()) {
		$flux['data']['_bigup_fichiers'] = [];
		foreach ($fichiers as $racine => $listes) {
			$flux['data']['_bigup_fichiers'][$racine] = $fichiers[$racine];
		}
	}

	if (empty($flux['data']['_hidden'])) {
		$flux['data']['_hidden'] = '';
	}
	$flux['data']['_hidden'] .= '<input type="hidden" name="bigup_retrouver_fichiers" value="1" />';

	return $flux;
}


/**
 * Branchement sur la réception d'un formulaire (avant verifier())
 *
 * On remet `$_FILES` avec les fichiers présents pour ce formulaire,
 * et avant que la fonction verifier native du formulaire soit utilisée,
 * de sorte qu'elle ait accès à $_FILES rempli.
 *
 * @pipeline formulaire_receptionner
 * @param array $flux
 * @return array
 */
function bigup_formulaire_receptionner($flux) {
	if (_request('bigup_retrouver_fichiers')) {
		$bigup = bigup_get_bigup($flux);
		$bigup->gerer_fichiers_postes(); // les fichiers postés sans JS
		$liste = $bigup->reinserer_fichiers(_request('bigup_reinjecter_uniquement'));
		$bigup->surveiller_fichiers($liste);
	}
	return $flux;
}

/**
 * Branchement sur verifier
 * 
 * - Si on a demandé la suppression d'un fichier, le faire
 * - Nettoyer les fichiers injectés effacés de $_FILES.
 *
 * @param array $flux
 * @return array
**/
function bigup_formulaire_verifier($flux) {
	$identifiant = _request('bigup_enlever_fichier');
	if ($identifiant or _request('bigup_retrouver_fichiers')) {
		$bigup = bigup_get_bigup($flux);
		// enlever un fichier dont on demande sa suppression
		if ($identifiant) {
			if ($bigup->supprimer_fichiers($identifiant)) {
				// on n'affiche pas les autres erreurs
				$flux['data'] = [];
				$flux['data']['message_erreur'] = '';
				$flux['data']['message_ok'] = _T('bigup:fichier_efface');
				$flux['data']['_erreur'] = true;
			}
		} else {
			// nettoyer nos fichiers réinsérés s'ils ont été enlevés de $_FILES
			$bigup->verifier_fichiers_surveilles();
		}
	}
	return $flux;
}


/**
 * Branchement sur traiter
 *
 * - Si on a effectué les traitements sans erreur,
 * tous les fichiers restants doivent disparaître
 * du cache.
 * - Nettoyer les fichiers injectés effacés de $_FILES.
 *
 * @param array $flux
 * @return array
 **/
function bigup_formulaire_traiter($flux) {
	if (_request('bigup_retrouver_fichiers')) {
		$bigup = bigup_get_bigup($flux);
		// à voir si on cherche systématiquement
		// ou uniquement lorsqu'on a demandé à recuperer les fichiers
		if (empty($flux['data']['message_erreur'])) {
			$bigup->supprimer_fichiers(_request('bigup_reinjecter_uniquement'));
		} else {
			// nettoyer nos fichiers réinsérés s'ils ont été enlevés de $_FILES
			$bigup->verifier_fichiers_surveilles();
		}
	}
	return $flux;
}

/**
 * Liste les formulaires où BigUP se charge automatiquement
 * (necessite un traitement spécifique)
 *
 * @return array
 */
function bigup_medias_formulaires_traitements_automatiques() {
	return [
		'configurer_image_fond_login',
		'editer_logo', 
		'editer_document', 
		'illustrer_document', 
		'formidable',
		'joindre_document', 
	];
}

/**
 * Ajouter bigup sur certains formulaires
 *
 * - le documents du plugin Medias
 * - le formulaire de logo de SPIP
 *
 * @param array $flux
 * @return array
 **/
function bigup_medias_formulaire_charger($flux) {
	if (
		in_array($flux['args']['form'], bigup_medias_formulaires_traitements_automatiques())
		and is_array($flux['data'])
	) {
		$flux['data']['_bigup_rechercher_fichiers'] = true;
	}
	return $flux;
}

/**
 * Utiliser Bigup sur certains formulaires
 *
 * - le documents du plugin Medias
 * - le formulaire de logo de SPIP
 *
 * @param array $flux
 * @return array
 **/
function bigup_medias_formulaire_fond($flux) {
	if (
		!empty($flux['args']['contexte']['_bigup_rechercher_fichiers'])
		and $form = $flux['args']['form']
	  and $bigup_medias_formulaire = charger_fonction('bigup_medias_formulaire_'.$form, 'inc', true)
	) {
		$bigup = bigup_get_bigup(['args' => $flux['args']['contexte']]);
		$formulaire = $bigup->formulaire($flux['data'], $flux['args']['contexte']);

		$formulaire = $bigup_medias_formulaire($flux['args'], $formulaire);

		$flux['data'] = $formulaire->get();
	}

	return $flux;
}

/**
 * @param array $args
 * @param \Spip\Bigup\Formulaire $formulaire
 * @return \Spip\Bigup\Formulaire
 */
function inc_bigup_medias_formulaire_joindre_document_dist($args, $formulaire) {
	$formulaire->preparer_input(
		'fichier_upload[]',
		[
			'previsualiser' => true,
			'drop-zone-extended' => '#contenu'
		]
	);
	$formulaire->inserer_js('bigup.documents.js');
	return $formulaire;
}

/**
 * @param array $args
 * @param \Spip\Bigup\Formulaire $formulaire
 * @return \Spip\Bigup\Formulaire
 */
function inc_bigup_medias_formulaire_editer_document_dist($args, $formulaire) {
	$formulaire->preparer_input(
		'fichier_upload[]',
		[
			'multiple' => false,
			'previsualiser' => true
		]
	);
	$formulaire->inserer_js('bigup.documents_edit.js');
	return $formulaire;
}

/**
 * @param array $args
 * @param \Spip\Bigup\Formulaire $formulaire
 * @return \Spip\Bigup\Formulaire
 */
function inc_bigup_medias_formulaire_illustrer_document_dist($args, $formulaire) {
	$formulaire->preparer_input(
		'fichier_upload[]',
		[
			'multiple' => false,
			'accept' => bigup_get_accept_logos(),
			'previsualiser' => true,
			'input_class' => 'bigup_illustration',
			'drop-zone-extended' => '.formulaire_illustrer_document .editer_fichier',
		]
	);
	$formulaire->inserer_js('bigup.documents_illustrer.js');
	return $formulaire;
}

/**
 * @param array $args
 * @param \Spip\Bigup\Formulaire $formulaire
 * @return \Spip\Bigup\Formulaire
 */
function inc_bigup_medias_formulaire_editer_logo_dist($args, $formulaire) {
	$options = [
		'accept' => bigup_get_accept_logos(),
		'previsualiser' => true,
		'input_class' => 'bigup_logo',
	];
	if (intval($args['args'][1]) or $args['args'][0] !== 'site') {
		$options['drop-zone-extended'] = '#navigation';
	}
	$formulaire->preparer_input(
		['logo_on', 'logo_off'],
		$options
	);
	$formulaire->inserer_js('bigup.logos.js');
	return $formulaire;
}

/**
 * @param array $args
 * @param \Spip\Bigup\Formulaire $formulaire
 * @return \Spip\Bigup\Formulaire
 */
function inc_bigup_medias_formulaire_formidable_dist($args, $formulaire) {
	$formulaire->preparer_input_class(
		'bigup', // 'file' pour rendre automatique.
		['previsualiser' => true]
	);
	return $formulaire;
}


/**
 * @param array $args
 * @param \Spip\Bigup\Formulaire $formulaire
 * @return \Spip\Bigup\Formulaire
 */
function inc_bigup_medias_formulaire_configurer_image_fond_login_dist($args, $formulaire) {
	$formulaire->preparer_input(
		'upload_image_fond_login',
		[
			'multiple' => false,
			'previsualiser' => true,
			'input_class' => 'bigup_simple',
		]
	);
	$formulaire->inserer_js('bigup.simples.js');
	return $formulaire;
}
