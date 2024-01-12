window.addEventListener("elementor:loaded",() => {
	var editor, editedModel, fields, emailControl;
	var setEmailControl = function () {
		emailControl = editor.collection.findWhere({
			name: 'laposta_email'
		});
	};
	var getEmailView = function () {
		return editor.children.findByModelCid(emailControl.cid);
	};
	var refreshEmailElement = function () {
		var emailView = getEmailView();
		if (emailView) {
			emailView.render();
		}
	};
	var updateEmailOptions = function () {
		var settingsModel = editedModel.get('settings'),
			emailModels = settingsModel.get('form_fields').where({
				field_type: 'email'
			}),
			emailFields;
		emailModels = _.reject(emailModels, {
			field_label: ''
		});
		emailFields = _.map(emailModels, function (model) {
			return {
				id: model.get('custom_id'),
				label: `${model.get('field_label')} field`
			};
		});
		emailControl.set('options', {
			'': emailControl.get('options')['']
		});
		_.each(emailFields, function (emailField) {
			emailControl.get('options')[emailField.id] = emailField.label;
		});
		refreshEmailElement();
	};

	var onFormFieldsChange = function (changedModel) {
		// If it's repeater field
		if (changedModel.get('custom_id')) {
			if ('email' === changedModel.get('field_type')) {
				updateEmailOptions();
			}
		}
	};
	var onPanelShow = function (panel, model) {
		editor = panel.getCurrentPageView();
		editedModel = model;

		setEmailControl();
		var settingsModel = editedModel.get('settings');
		elementor.stopListening(settingsModel.get('form_fields'),'change',onFormFieldsChange)
		elementor.listenTo(settingsModel.get('form_fields'),'change',onFormFieldsChange)
		elementor.stopListening(settingsModel.get('form_fields'),'remove',onFormFieldsChange)
		elementor.listenTo(settingsModel.get('form_fields'),'remove',onFormFieldsChange)
		updateEmailOptions();
	};
	var init = function () {
		elementor.hooks.addAction('panel/open_editor/widget/form', onPanelShow);
	};
	init();
})