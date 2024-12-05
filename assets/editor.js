window.addEventListener("elementor:loaded",() => {
	let editor, editedModel, fields, fieldControls,fieldIds, settingsModel, inputFields;
	const updateFieldControls = function () {
		//console.log("editor",editor)
		fieldIds = {
			'listid': editor.collection.findIndex(c=>{return c.attributes.section==='section_laposta'&&c.attributes.name==='listid';}),
			'laposta_api_fields': editor.collection.findIndex(c=>{return c.attributes.section==='section_laposta'&&c.attributes.name==='laposta_api_fields';})
		}
	};
	const updateInputFieldsList = function () {
		let settingsModel = editedModel.get('settings'),
			fieldModels = settingsModel.get('form_fields').where({
				// field_type: 'email'
			}).filter((model) => model.attributes.field_label!=='');

		const defaultField = {
			id: '',
			label: 'Select field'
		}
		inputFields = _.map(fieldModels, function (model) {
			return {
				id: model.get('custom_id'),
				label: model.get('field_label')
			};
		});
		inputFields.unshift(defaultField);
	}
	const updateOptions = function () {
		updateInputFieldsList();
		for (let control in fieldControls) {
			fieldControls[control].set('options', inputFields.reduce((acc, item) => {
				acc[item.id] = item.label;
				return acc;
			}, {}));
			//console.log(fieldControls[control],fieldControls[control].cid)
			const view = editor.children.findByModelCid(fieldControls[control].cid);
			if(view)
				view.render();
		}
	};

	const onFormFieldsChange = function (changedModel) {
		// If it's repeater field
		if (changedModel.get('custom_id')) {
			//if ('email' === changedModel.get('field_type')) {
				updateOptions();
			//}
		}
	};

	let apiKeyInputListener = null;
	// Function to set up the listener for the API key input
	function setupApiKeyInputListener() {
		// Make sure we don't add multiple listeners
		if (apiKeyInputListener) {
			jQuery('input[data-setting="laposta_api_key"]').off('change', apiKeyInputListener);
		}

		apiKeyInputListener = function() {
			const apiKey = jQuery(this).val();
			if (apiKey) {
				// Fetch Laposta boards
				fetchLists(apiKey);
			}
		};

		// Attach the event listener
		jQuery('input[data-setting="laposta_api_key"]').on('change', apiKeyInputListener);
		if(jQuery('input[data-setting="laposta_api_key"]').val().length>5)
			fetchLists(jQuery('input[data-setting="laposta_api_key"]').val());
	}

	// Function to fetch lists from Laposta
	function fetchLists(apiKey) {
		updateInputFieldsList();
		const listOptions = '<option value="">Loading...</option>';
		jQuery('select[data-setting="listid"]').html(listOptions);
		jQuery.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'fetch_laposta_lists',
				api_key: apiKey
			},
			success: function(response) {
				const lists = response.data;
				let listOptions = '<option value="">Select a Board</option>';
				const listID = settingsModel.attributes.listid;
				let match = false;
				// Populate lists dropdown
				lists.forEach(function(list) {
					if(listID === list.list.list_id)
						match = true;
					listOptions += '<option ' + (listID === list.list.list_id?"selected='selected'":"") + ' value="' + list.list.list_id + '">' + list.list.name + ' ( ' + list.list.state + ' )</option>';
				});
				jQuery('select[data-setting="listid"]').html(listOptions);
				// Listen for list selection changes
				jQuery('select[data-setting="listid"]').off('change').on('change', function() {
					//console.log(settingsModel)
					if(Object.keys(fieldControls).length>0) {
						for (let control in fieldControls) {
							editor.collection.remove(fieldControls[control]);
							settingsModel.set('_laposta_field_'+control);
						}
						fieldControls = {};
					}

					const listId = jQuery(this).val();
					const apiKey = jQuery('input[data-setting="laposta_api_key"]').val();
					//console.log(listId);
					if (listId && apiKey) {
						fetchListFields(apiKey, listId);
					}
				});
				if(match)
					fetchListFields(apiKey, listID);
			},
			error: function() {
				alert('Failed to fetch boards. Please check your API key.');
			}
		});
	}

	function fetchListFields(apiKey, listId) {
		jQuery.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'fetch_laposta_list_fields',
				api_key: apiKey,
				list_id: listId
			},
			success: function (response) {
				fieldControls = fieldControls || {};
				let fields = response.data;
				//console.log("ids",fieldIds)
				//console.log("fields",fields)
				let lastField = fieldIds['laposta_api_fields']
				fields = fields.sort((a, b) => parseInt(b.field.pos) - parseInt(a.field.pos));
				for (let i = fields.length - 1; i >= 0; i--) {
					const field = fields[i].field;
					const check = editor.collection.findWhere({name: '_laposta_field_'+ (field.custom_name || 'email')});
					//console.log("field",inputFields)
					if(check) {
						lastField = editor.collection.findIndex(c=>{return c.attributes.section==='section_laposta'&&c.attributes.name==='_laposta_field_'+(field.custom_name || 'email');})
						continue;
					}
					//console.log(editor)
					editor.collection.push({
						name: '_laposta_field_'+(field.custom_name || 'email'),
						label: field.name,
						type: 'select',
						section: 'section_laposta',
						default: '',
						options: inputFields.reduce((acc, item) => {
							acc[item.id] = item.label;
							return acc;
						}, {}),
						show_label: true,
						label_block: true,
						tab: "content",
						condition: {
							"submit_actions": "laposta"
						},
					}, {at: lastField+1});
					lastField = editor.collection.findIndex(c=>{return c.attributes.section==='section_laposta'&&c.attributes.name==='_laposta_field_'+(field.custom_name || 'email');})
					fieldControls[field.custom_name || 'email'] = editor.collection.findWhere({name: '_laposta_field_'+(field.custom_name || 'email')});
				}
			}
		});
	}

	// Create a mutation observer to watch for changes in the DOM
	const observer = new MutationObserver(function(mutationsList, observer) {
		mutationsList.forEach(function(mutation) {
			if (mutation.type === 'childList') {
				mutation.addedNodes.forEach(function(node) {
					// Check if the added node is the API key input field
					if (node.nodeType === 1 && node.classList.contains('elementor-control-laposta_api_key')) {
						// Initialize the listener if the API key input is found
						setupApiKeyInputListener();
					}
				});
			}
		});
	});

	const onPanelShow = function (panel, model) {
		editor = panel.getCurrentPageView();
		editedModel = model;

		observer.observe(document.body, { childList: true, subtree: true });
		updateFieldControls();
		settingsModel = editedModel.get('settings');
		//console.log("settings",settingsModel)
		elementor.stopListening(settingsModel.get('form_fields'), 'change', onFormFieldsChange)
		elementor.listenTo(settingsModel.get('form_fields'), 'change', onFormFieldsChange)
		elementor.stopListening(settingsModel.get('form_fields'), 'remove', onFormFieldsChange)
		elementor.listenTo(settingsModel.get('form_fields'), 'remove', onFormFieldsChange)
		//updateOptions();
	};
	const init = function () {
		elementor.hooks.addAction('panel/open_editor/widget/form', onPanelShow);
	};
	init();
})