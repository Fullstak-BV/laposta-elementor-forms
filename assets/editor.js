window.addEventListener("elementor:loaded", () => {
    let editor, editedModel, fieldControls, fieldIds, settingsModel, inputFields;
    fieldControls = {};

    const config = window.lapostaElementorForms || {};
    const labels = config.mappingLabels || {};
    const isDebug = !!config.debug;
    const debugLog = function () {
        if (!isDebug || !window.console || !console.log) {
            return;
        }
        const args = Array.prototype.slice.call(arguments);
        args.unshift('[Laposta]');
        console.log.apply(console, args);
    };
    const debugError = function () {
        if (!isDebug || !window.console || !console.error) {
            return;
        }
        const args = Array.prototype.slice.call(arguments);
        args.unshift('[Laposta]');
        console.error.apply(console, args);
    };
	const text = {
		formOption: labels.formOption || 'Form field option',
		lapostaOption: labels.lapostaOption || 'Laposta option',
		selectPrompt: labels.selectPrompt || 'Select...',
		noFormOptions: labels.noFormOptions || 'Selecteer een formulier veld met opties om een mapping te maken.',
		noLapostaOptions: labels.noLapostaOptions || 'Dit Laposta veld heeft geen opties.',
		noMappingNeeded: labels.noMappingNeeded || 'Geen extra mapping nodig voor dit veld.'
	};

	const getResponseMessage = function (payload, fallback) {
		if (payload && payload.message) {
			return payload.message;
		}
		if (payload && payload.data && payload.data.message) {
			return payload.data.message;
		}
		if (payload && payload.responseJSON && payload.responseJSON.data && payload.responseJSON.data.message) {
			return payload.responseJSON.data.message;
		}
		return fallback;
	};

    let mappingStylesInjected = false;

    const escapeHtml = function (string) {
        if (null === string || undefined === string) {
            return '';
        }
        return String(string)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const normalizeLapostaOptions = function (field) {
        if (!field) {
            return [];
        }
        if (Array.isArray(field.options_full) && field.options_full.length) {
            return field.options_full.map(option => {
                const value = option.value !== undefined ? option.value : option.id;
                return {
                    value,
                    label: option.label || option.name || option.value || option.id
                };
            });
        }
        if (Array.isArray(field.options) && field.options.length) {
            return field.options.map(option => {
                if (typeof option === 'string') {
                    return {value: option, label: option};
                }
                if (option && typeof option === 'object') {
                    const value = option.value !== undefined ? option.value : option.id;
                    return {
                        value,
                        label: option.label || option.name || value
                    };
                }
                return {value: '', label: ''};
            });
        }
        return [];
    };

    const normalizeMappingObject = function (mapping) {
        const normalized = {};
        if (!mapping || typeof mapping !== 'object' || Array.isArray(mapping)) {
            return normalized;
        }

        Object.keys(mapping).forEach(key => {
            if (key === undefined || key === null || key === '') {
                return;
            }
            const value = mapping[key];
            if (value === undefined || value === null || value === '') {
                return;
            }
            normalized[String(key)] = String(value);
        });

        return normalized;
    };

    const getFormFieldModel = function (customId) {
        if (!settingsModel || !customId) {
            return null;
        }
        const formFields = settingsModel.get('form_fields');
        if (!formFields || !formFields.findWhere) {
            return null;
        }
        return formFields.findWhere({custom_id: customId});
    };

    const fieldOptionsToArray = function (fieldOptions) {
        if (!fieldOptions) {
            return [];
        }
        if (Array.isArray(fieldOptions)) {
            return fieldOptions.map(option => {
                if (typeof option === 'string') {
                    return {value: option, label: option};
                }
                if (option && typeof option === 'object') {
                    const value = option.value !== undefined ? option.value : option.id;
                    return {
                        value,
                        label: option.label || option.name || value
                    };
                }
                return null;
            }).filter(Boolean);
        } else if (typeof fieldOptions === 'string') {
            return fieldOptions.split('\n').map(option => {
                const trimmed = option.trim();
                if (!trimmed) {
                    return null;
                }
                const parts = trimmed.split('|');
                if (parts.length === 2) {
                    return {value: parts[0].trim(), label: parts[1].trim()};
                }
                return {value: trimmed, label: trimmed};
            }).filter(Boolean);
        }
        debugError("Unknown fieldOptions format", fieldOptions);
        return [];
    }

    const getFormFieldOptions = function (customId) {
        const formFieldModel = getFormFieldModel(customId);
        debugLog('Resolved form field model', customId, formFieldModel);
        if (!formFieldModel) {
            return [];
        }
        let options = [];
        const fieldOptions = formFieldModel.get('field_options');
        if (fieldOptions) {
            options = fieldOptionsToArray(fieldOptions);
        }
        debugLog('Initial form field options', customId, options);
        if (!options.length) {
            const modelOptions = formFieldModel.get('options');
            if (Array.isArray(modelOptions)) {
                options = modelOptions.map(option => {
                    if (typeof option === 'string') {
                        return {value: option, label: option};
                    }
                    if (option && typeof option === 'object') {
                        const value = option.value !== undefined ? option.value : option.id;
                        return {
                            value,
                            label: option.label || option.name || option.text || value
                        };
                    }
                    return null;
                }).filter(Boolean);
            } else if (modelOptions && typeof modelOptions === 'object') {
                options = Object.keys(modelOptions).map(key => ({
                    value: key,
                    label: modelOptions[key]
                }));
            }
        }
        return options;
    };

    const parseLegacyMappingString = function (mappingString) {
        const mapping = {};
        const lines = mappingString.split(/\r\n|\r|\n/);
        lines.forEach(line => {
            const trimmed = line.trim();
            if (!trimmed) {
                return;
            }
            const matches = trimmed.match(/\s*(.+?)\s*(=>|=|:)\s*(.*)$/);
            if (matches) {
                const source = matches[1].trim();
                const target = matches[3].trim();
                if (source !== '') {
                    mapping[source] = target;
                }
            }
        });
        return mapping;
    };

    const readMapping = function (fieldKey) {
        if (!settingsModel) {
            return {};
        }
        const control = fieldControls[fieldKey];
        if (!control || !control.mappingControlName) {
            return {};
        }
        const rawValue = settingsModel.get(control.mappingControlName);
        if (!rawValue) {
            return {};
        }
        if (typeof rawValue === 'object') {
            return normalizeMappingObject(rawValue);
        }
        if (typeof rawValue === 'string') {
            const trimmed = rawValue.trim();
            if (!trimmed) {
                return {};
            }
            try {
                const parsed = JSON.parse(trimmed);
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    return normalizeMappingObject(parsed);
                }
            } catch (e) {
                // Fallback to legacy format
            }
            return normalizeMappingObject(parseLegacyMappingString(trimmed));
        }
        return {};
    };

    const writeMapping = function (fieldKey, mapping) {
        if (!settingsModel) {
            return;
        }
        const control = fieldControls[fieldKey];
        if (!control || !control.mappingControlName) {
            return;
        }
        const sanitized = normalizeMappingObject(mapping);
        const serialized = JSON.stringify(sanitized);
        if (settingsModel.get(control.mappingControlName) === serialized) {
            return;
        }
        settingsModel.set(control.mappingControlName, serialized);
        debugLog('Persisted mapping', fieldKey, sanitized);
    };

    const injectMappingStyles = function () {
        if (mappingStylesInjected) {
            return;
        }
        mappingStylesInjected = true;
        const styleElement = document.createElement('style');
        styleElement.type = 'text/css';
        styleElement.textContent = '.laposta-mapping-wrapper{margin:8px 0;}' +
            '.laposta-mapping-table{display:flex;flex-direction:column;gap:6px;}' +
            '.laposta-mapping-header,.laposta-mapping-row{display:flex;gap:8px;align-items:center;}' +
            '.laposta-mapping-header span{font-weight:600;flex:1;}' +
            '.laposta-mapping-row select{flex:1;min-height:32px;}';
        document.head.appendChild(styleElement);
    };

    const ensureMappingContainer = function (fieldKey) {
        const control = fieldControls[fieldKey];
        if (!control || !control.mappingInput) {
            return null;
        }

        const mappingInputView = editor.children.findByModelCid(control.mappingInput.cid);
        if (!mappingInputView) {
            return null;
        }

        mappingInputView.$el.hide();

        let wrapper = mappingInputView.$el.next('.laposta-mapping-wrapper[data-laposta-mapping="' + fieldKey + '"]');
        if (!wrapper.length) {
            wrapper = jQuery('<div class="elementor-control laposta-mapping-wrapper" data-laposta-mapping="' + fieldKey + '"><div class="laposta-mapping-table"></div></div>');
            mappingInputView.$el.after(wrapper);
            debugLog('Created mapping wrapper', fieldKey);
        }

        control.container = wrapper;
        return wrapper.find('.laposta-mapping-table');
    };

    const renderMappingInterface = function (fieldKey) {
        const control = fieldControls[fieldKey];
        if (!control || !control.mappingInput) {
            return;
        }
        injectMappingStyles();

        const container = ensureMappingContainer(fieldKey);
        if (!container) {
            debugLog('Mapping container not ready, deferring render', fieldKey);
            requestAnimationFrame(() => renderMappingInterface(fieldKey));
            return;
        }

        if (!control.lapostaOptions || !control.lapostaOptions.length) {
            container.html('<em>' + escapeHtml(text.noLapostaOptions) + '</em>');
            debugLog('Laposta field has no options to map', fieldKey);
            return;
        }

        const selectedFormField = settingsModel.get(control.selectControlName);
        const formOptions = getFormFieldOptions(selectedFormField);
        if (!formOptions.length) {
            container.html('<em>' + escapeHtml(text.noFormOptions) + '</em>');
            debugLog('No form options present for selected Elementor field', fieldKey, selectedFormField);
            return;
        }

        const mappingData = readMapping(fieldKey);
        const lapostaValuesSet = new Set(control.lapostaOptions.map(option => String(option.value)));
        const lapostaToForm = {};
        Object.keys(mappingData).forEach(formValue => {
            const mappedLapostaValue = mappingData[formValue];
            if (mappedLapostaValue === undefined || mappedLapostaValue === null) {
                return;
            }
            const lapostaKey = String(mappedLapostaValue);
            if (!lapostaValuesSet.has(lapostaKey)) {
                return;
            }
            lapostaToForm[lapostaKey] = formValue;
        });

        const formOptionsHtml = ['<option value="">' + escapeHtml(text.selectPrompt) + '</option>'].concat(formOptions.map(option => {
            const value = option.value !== undefined ? option.value : option.label;
            const label = option.label !== undefined ? option.label : option.value;
            return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
        })).join('');

        const lapostaSelectBase = function (selectedValue) {
            return control.lapostaOptions.map(option => {
                const optionValue = option.value !== undefined ? option.value : option.label;
                const optionLabel = option.label !== undefined ? option.label : option.value;
                const isSelected = String(optionValue) === String(selectedValue);
                return '<option value="' + escapeHtml(optionValue) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(optionLabel) + '</option>';
            }).join('');
        };

        const rowsHtml = control.lapostaOptions.map(option => {
            const lapostaValue = option.value !== undefined ? option.value : option.label;
            const selectedFormOption = lapostaToForm[String(lapostaValue)] || '';
            const lapostaSelectHtml = '<select class="laposta-mapping-select laposta-mapping-select-laposta" data-laposta-field="' + escapeHtml(fieldKey) + '" data-laposta-option="' + escapeHtml(lapostaValue) + '" disabled aria-disabled="true">' + lapostaSelectBase(lapostaValue) + '</select>';
            const formSelectHtml = '<select class="laposta-mapping-select laposta-mapping-select-form" data-laposta-field="' + escapeHtml(fieldKey) + '" data-laposta-option="' + escapeHtml(lapostaValue) + '">' + formOptionsHtml + '</select>';

            return '<div class="laposta-mapping-row">' + lapostaSelectHtml + formSelectHtml + '</div>';
        }).join('');

        container.html('<div class="laposta-mapping-header"><span>' + escapeHtml(text.lapostaOption) + '</span><span>' + escapeHtml(text.formOption) + '</span></div>' + rowsHtml);

        container.find('.laposta-mapping-select-form').each(function () {
            const select = jQuery(this);
            const lapostaValue = String(select.data('laposta-option'));
            const mappedFormValue = lapostaToForm[lapostaValue] || '';
            select.val(mappedFormValue);
        });

        container.off('change', '.laposta-mapping-select-form');
        container.on('change', '.laposta-mapping-select-form', function () {
            const select = jQuery(this);
            const lapostaValue = String(select.data('laposta-option'));
            const selectedFormValueRaw = select.val();
            const selectedFormValue = selectedFormValueRaw ? String(selectedFormValueRaw) : '';
            const updatedMapping = Object.assign({}, readMapping(fieldKey));

            Object.keys(updatedMapping).forEach(formValue => {
                if (String(updatedMapping[formValue]) === lapostaValue || formValue === selectedFormValue) {
                    delete updatedMapping[formValue];
                }
            });

            if (selectedFormValue) {
                updatedMapping[selectedFormValue] = lapostaValue;
            }

            writeMapping(fieldKey, updatedMapping);
        });
    };

    const renderAllMappingInterfaces = function () {
        if (!fieldControls) {
            return;
        }
        Object.keys(fieldControls).forEach(fieldKey => {
            renderMappingInterface(fieldKey);
        });
    };

    const detachMappingListeners = function (fieldKey) {
        const control = fieldControls[fieldKey];
        if (!control) {
            return;
        }
        if (control.listeners && control.listeners.select) {
            settingsModel.off('change:' + control.selectControlName, control.listeners.select);
        }
        if (control.listeners && control.listeners.mapping) {
            settingsModel.off('change:' + control.mappingControlName, control.listeners.mapping);
        }
        if (control.container && control.container.length) {
            control.container.off('change', '.laposta-mapping-select-form');
            control.container.remove();
        }
        control.container = null;
        if (control.listeners) {
            control.listeners = null;
        }
        debugLog('Detached mapping listeners for', fieldKey);
    };

    const attachMappingListeners = function (fieldKey) {
        const control = fieldControls[fieldKey];
        if (!control || !settingsModel) {
            return;
        }
        if (control.listeners) {
            return;
        }
        const selectListener = function () {
            renderMappingInterface(fieldKey);
        };
        const mappingListener = function () {
            renderMappingInterface(fieldKey);
        };
        settingsModel.on('change:' + control.selectControlName, selectListener);
        settingsModel.on('change:' + control.mappingControlName, mappingListener);
        control.listeners = {
            select: selectListener,
            mapping: mappingListener
        };
        debugLog('Attached mapping listeners for', fieldKey);
    };
    const updateFieldControls = function () {
        //console.log("editor",editor)
        fieldIds = {
            'listid': editor.collection.findIndex(c => {
                return c.attributes.section === 'section_laposta' && c.attributes.name === 'listid';
            }),
            'laposta_api_fields': editor.collection.findIndex(c => {
                return c.attributes.section === 'section_laposta' && c.attributes.name === 'laposta_api_fields';
            })
        }
    };
    const updateInputFieldsList = function () {
        let settingsModel = editedModel.get('settings'),
            fieldModels = settingsModel.get('form_fields').where({
                // field_type: 'email'
            }).filter((model) => model.attributes.field_label !== '');

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
            if (!fieldControls[control] || !fieldControls[control].select) {
                continue;
            }
            fieldControls[control].select.set('options', inputFields.reduce((acc, item) => {
                acc[item.id] = item.label;
                return acc;
            }, {}));
            const view = editor.children.findByModelCid(fieldControls[control].select.cid);
            if (view)
                view.render();
        }
        renderAllMappingInterfaces();
    };

    const onFormFieldsChange = function (changedModel) {
        // If it's repeater field
        if (changedModel.get('custom_id')) {
            //if ('email' === changedModel.get('field_type')) {
            updateOptions();
            //}
        }
        renderAllMappingInterfaces();
    };

    let apiKeyInputListener = null;

    // Function to set up the listener for the API key input
    function setupApiKeyInputListener() {
        // Make sure we don't add multiple listeners
        if (apiKeyInputListener) {
            jQuery('input[data-setting="laposta_api_key"]').off('change', apiKeyInputListener);
        }

        apiKeyInputListener = function () {
            const apiKey = jQuery(this).val();
            if (apiKey) {
                // Fetch Laposta boards
                fetchLists(apiKey);
            }
        };

        // Attach the event listener
        jQuery('input[data-setting="laposta_api_key"]').on('change', apiKeyInputListener);
        if (jQuery('input[data-setting="laposta_api_key"]').val().length > 5)
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
            success: function (response) {
                if (!response || response.success === false) {
                    const message = getResponseMessage(response, 'Failed to fetch boards. Please check your API key.');
                    if (isDebug) {
                        debugError('Laposta lists endpoint returned an error response', response);
                    }
                    alert(message);
                    return;
                }

                const lists = response.data;
                if (isDebug) {
                    debugLog('Fetched Laposta lists', lists);
                }
                let listOptions = '<option value="">Select a Board</option>';
                const listID = settingsModel.attributes.listid;
                let match = false;
                // Populate lists dropdown
                lists.forEach(function (list) {
                    if (listID === list.list.list_id)
                        match = true;
                    listOptions += '<option ' + (listID === list.list.list_id ? "selected='selected'" : "") + ' value="' + list.list.list_id + '">' + list.list.name + ' ( ' + list.list.state + ' )</option>';
                });
                jQuery('select[data-setting="listid"]').html(listOptions);
                // Listen for list selection changes
                jQuery('select[data-setting="listid"]').off('change').on('change', function () {
                    //console.log(settingsModel)
                    if (Object.keys(fieldControls).length > 0) {
                        for (let control in fieldControls) {
                            if (!fieldControls[control]) {
                                continue;
                            }
                            detachMappingListeners(control);
                            if (fieldControls[control].select) {
                                editor.collection.remove(fieldControls[control].select);
                            }
                            if (fieldControls[control].mappingInput) {
                                editor.collection.remove(fieldControls[control].mappingInput);
                            }
                            if (fieldControls[control].selectControlName) {
                                settingsModel.set(fieldControls[control].selectControlName, '');
                            }
                            if (fieldControls[control].mappingControlName) {
                                settingsModel.set(fieldControls[control].mappingControlName, '{}');
                            }
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
                if (match)
                    fetchListFields(apiKey, listID);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                if (isDebug) {
                    debugError('Failed to fetch Laposta lists', textStatus, errorThrown, jqXHR);
                }
                const fallback = 'Failed to fetch boards. Please check your API key.';
                alert(getResponseMessage(jqXHR, fallback));
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
                if (!response || response.success === false) {
                    const message = getResponseMessage(response, 'Failed to fetch Laposta fields.');
                    if (isDebug) {
                        debugError('Laposta list fields endpoint returned an error response', response);
                    }
                    alert(message);
                    return;
                }

                fieldControls = fieldControls || {};
                let fields = Array.isArray(response.data) ? response.data : [];
                let lastField = fieldIds['laposta_api_fields'];
                fields = fields.sort((a, b) => parseInt(b.field.pos, 10) - parseInt(a.field.pos, 10));
                const activeKeys = [];
                for (let i = fields.length - 1; i >= 0; i--) {
                    const field = fields[i].field;
                    const lapostaFieldKey = field.custom_name || 'email';
                    const selectControlName = '_laposta_field_' + lapostaFieldKey;
                    const mappingControlName = '_laposta_field_mapping_' + lapostaFieldKey;
                    const lapostaOptions = normalizeLapostaOptions(field);
                    activeKeys.push(lapostaFieldKey);
                    if (isDebug) {
                        debugLog('Processing Laposta field', lapostaFieldKey, lapostaOptions);
                    }

                    let selectControl = editor.collection.findWhere({name: selectControlName});
                    if (!selectControl) {
                        editor.collection.push({
                            name: selectControlName,
                            label: field.name,
                            type: 'select',
                            section: 'section_laposta',
                            default: settingsModel && settingsModel.attributes ? (settingsModel.attributes[selectControlName] || '') : '',
                            options: inputFields.reduce((acc, item) => {
                                acc[item.id] = item.label;
                                return acc;
                            }, {}),
                            show_label: true,
                            label_block: true,
                            tab: 'content',
                            condition: {
                                'submit_actions': 'laposta'
                            },
                        }, {at: lastField + 1});
                        lastField = editor.collection.findIndex(c => c.attributes.section === 'section_laposta' && c.attributes.name === selectControlName);
                        selectControl = editor.collection.findWhere({name: selectControlName});
                    } else {
                        lastField = editor.collection.findIndex(c => c.attributes.section === 'section_laposta' && c.attributes.name === selectControlName);
                    }

                    let mappingInputControl = null;

                    if (lapostaOptions.length) {
                        mappingInputControl = editor.collection.findWhere({name: mappingControlName});
                        if (!mappingInputControl) {
                            editor.collection.push({
                                name: mappingControlName,
                                label: '',
                                type: 'textarea',
                                section: 'section_laposta',
                                default: settingsModel && settingsModel.attributes ? (settingsModel.attributes[mappingControlName] || '{}') : '{}',
                                rows: 2,
                                placeholder: '{}',
                                show_label: false,
                                label_block: false,
                                tab: 'content',
                                condition: {
                                    'submit_actions': 'laposta'
                                },
                            }, {at: lastField + 1});
                            lastField = editor.collection.findIndex(c => c.attributes.section === 'section_laposta' && c.attributes.name === mappingControlName);
                            mappingInputControl = editor.collection.findWhere({name: mappingControlName});
                        }
                    } else {
                        const staleMappingInput = editor.collection.findWhere({name: mappingControlName});
                        if (staleMappingInput) {
                            editor.collection.remove(staleMappingInput);
                        }
                        mappingInputControl = null;
                    }

                    fieldControls[lapostaFieldKey] = fieldControls[lapostaFieldKey] || {};
                    fieldControls[lapostaFieldKey].select = selectControl;
                    fieldControls[lapostaFieldKey].selectControlName = selectControlName;
                    fieldControls[lapostaFieldKey].mappingInput = mappingInputControl;
                    fieldControls[lapostaFieldKey].mappingControlName = mappingControlName;
                    fieldControls[lapostaFieldKey].lapostaOptions = lapostaOptions;

                    if (lapostaOptions.length) {
                        attachMappingListeners(lapostaFieldKey);
                    } else {
                        detachMappingListeners(lapostaFieldKey);
                    }
                }

                Object.keys(fieldControls).forEach(fieldKey => {
                    if (activeKeys.indexOf(fieldKey) === -1) {
                        detachMappingListeners(fieldKey);
                        if (fieldControls[fieldKey].select) {
                            editor.collection.remove(fieldControls[fieldKey].select);
                        }
                        if (fieldControls[fieldKey].mappingInput) {
                            editor.collection.remove(fieldControls[fieldKey].mappingInput);
                        }
                        delete fieldControls[fieldKey];
                        if (isDebug) {
                            debugLog('Removed stale Laposta field mapping', fieldKey);
                        }
                    }
                });

                renderAllMappingInterfaces();
            },
            error: function (jqXHR, textStatus, errorThrown) {
                if (isDebug) {
                    debugError('Failed to fetch Laposta list fields', textStatus, errorThrown, jqXHR);
                }
                const fallback = 'Failed to fetch Laposta fields.';
                alert(getResponseMessage(jqXHR, fallback));
            }
        });
    }

    // Create a mutation observer to watch for changes in the DOM
    const observer = new MutationObserver(function (mutationsList, observer) {
        mutationsList.forEach(function (mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function (node) {
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

        observer.observe(document.body, {childList: true, subtree: true});
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
