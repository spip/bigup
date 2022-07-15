/** Gérer le formulaire de documents avec Bigup */
function formulaires_documents_avec_bigup () {
	// trouver les input qui envoient des fichiers
	$(".formulaire_joindre_document form .editer_fichier_upload")
		.find("label").hide().end()
		.find("input[type=file].bigup")
		.not('.bigup_document')
		.addClass('bigup_document')
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
					bigup
					.send(data)
					.done(function(html) {
						var message = $(html).find('.reponse_formulaire').html();
						if (message) {
							bigup.presenter_succes(input, message);
						} else {
							bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
						}
						input.addClass('remove').animate({opacity: "0.0"}, 'fast', function(){
							// autoriser de mettre une seconde fois le fichier
							file.bigup_deleted = true;
							file.cancel();
							input.remove();
						});
					})
					.fail(function(data) {
						bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
					});
				}
			})
			.fail(function(data) {
				bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
			});
		});
	// Si les inputs d'upload sont tout seuls dans un .boutons, cacher ce dernier, sinon juste l'input
	$("input[name='joindre_upload']").each(function() {
		var
			$input_upload = $(this),
			$boutons = $input_upload.parents('.boutons');
		if ($boutons.length > 0 && $input_upload.siblings().length === 0) {
			$boutons.hide();
		} else {
			$input_upload.hide();
		}
	});
}
jQuery(function($) {
	formulaires_documents_avec_bigup();
	onAjaxLoad(formulaires_documents_avec_bigup);
});
