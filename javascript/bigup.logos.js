/** Gérer le formulaire de logo avec Bigup */
function formulaires_logos_avec_bigup() {
	// trouver les input qui envoient des fichiers
	var formulaire_editer_logo = $(".formulaire_editer_logo form");
	formulaire_editer_logo
		.find(".editer_logo_on, .editer_logo_off")
		.find("label").hide().end()
		.find("input[type=file].bigup_logo")
		.not('.bigup_done')
		.bigup()
		.on('bigup.fileSuccess', function(event, file, description) {
			const bigup = file.bigup;
			const input = file.emplacement;

			const data = bigup.buildFormData();
			data.set('formulaire_action_verifier_json', true);
			data.set('bigup_reinjecter_uniquement', [description.bigup.identifiant]);

			// verifier les champs
			bigup
			.send(data, {dataType: 'json'})
			.done(function(erreurs) {
				var erreur = erreurs[bigup.name] || erreurs.message_erreur;
				if (erreur) {
					bigup.presenter_erreur(input, erreur);
				} else {
					data.delete('formulaire_action_verifier_json');
					var conteneur = bigup.form.parents('.formulaire_editer_logo');
					conteneur.animateLoading();
					// Faire le traitement prévu, supposant qu'il n'y aura pas d'erreur...
					bigup
					.send(data)
					.done(function(html) {
						bigup.presenter_succes(input, _T('bigup:succes_logo_envoye'));
						bigup.form.parents('.formulaire_spip').parent().html(html);
					})
					.fail(function(data) {
						conteneur.endLoading();
						bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
					});
				}
			})
			.fail(function(data) {
				bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
			});
		})
		.closest('.editer').find('.dropfiletext').html(_T('bigup:deposer_le_logo_ici'));
	// Si l'input d'upload est tout seul dans un .boutons, cacher ce dernier, sinon juste l'input
	var
		$input_upload = formulaire_editer_logo.find('.btn-upload'),
		$boutons = $input_upload.parents('.boutons');
	if ($boutons.length > 0 && $input_upload.siblings().length === 0) {
		$boutons.hide();
	} else {
		$input_upload.hide();
	}
}

jQuery(function($) {
	formulaires_logos_avec_bigup();
	onAjaxLoad(formulaires_logos_avec_bigup);
});
