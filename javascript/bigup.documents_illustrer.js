/** Gérer le formulaire d’illustration de documents avec Bigup */
function formulaires_documents_illustrer_avec_bigup () {
	// trouver les input qui envoient des fichiers
	$(".formulaire_illustrer_document")
		.find("form .editer_fichier_upload")
		.find("label").hide().end()
		.find("input[type=file].bigup_illustration")
		.not('.bigup_done')
		.bigup()
		.on('bigup.fileSuccess', function(event, file, description) {
			const bigup = file.bigup;
			const input = file.emplacement;

			const data = bigup.buildFormData();
			data.set('joindre_upload', true);
			data.set('joindre_zip', true); // les zips sont conservés zippés systématiquement.
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
					var conteneur = bigup.form.parents('.formulaire_illustrer_documents');
					conteneur.animateLoading();
					// Faire le traitement prévu, supposant qu'il n'y aura pas d'erreur...
					bigup
					.send(data)
					.done(function(html) {
						bigup.presenter_succes(input, _T('bigup:succes_vignette_envoyee'));
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
		.closest('.editer').find('.dropfiletext').html(_T('bigup:deposer_la_vignette_ici'));
}
jQuery(function($) {
	formulaires_documents_illustrer_avec_bigup();
	onAjaxLoad(formulaires_documents_illustrer_avec_bigup);
});
