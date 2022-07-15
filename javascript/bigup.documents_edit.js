/** Gérer le formulaire de modification de documents avec Bigup */
function formulaires_documents_edit_avec_bigup () {

	// trouver les input qui envoient des fichiers
	$(".formulaire_editer_document")
		.find("form .editer_fichier_upload")
		.find("label").hide().end()
		.find("input[type=file].bigup")
		.not('.bigup_document_edit')
		.addClass('bigup_document_edit')
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

					// Faire le traitement prévu, supposant qu'il n'y aura pas d'erreur...
					var conteneur = bigup.form.parents('.formulaire_editer_document');
					conteneur.animateLoading();
					// Faire le traitement prévu, supposant qu'il n'y aura pas d'erreur...
					bigup
					.send(data)
					.done(function(html) {
						bigup.presenter_succes(input, _T('bigup:succes_fichier_envoye'));
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
		});
}
jQuery(function($) {
	formulaires_documents_edit_avec_bigup();
	onAjaxLoad(formulaires_documents_edit_avec_bigup);
});
