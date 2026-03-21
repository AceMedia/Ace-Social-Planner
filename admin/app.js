(function () {
    const config = window.aceSocialPlanner || {};
    const rootNode = document.getElementById('ace-social-planner-app');

    if (!rootNode || !window.wp || !window.wp.element) {
        return;
    }

    const el = window.wp.element.createElement;
    const useState = window.wp.element.useState;
    const Fragment = window.wp.element.Fragment;
    const render = window.wp.element.render;

    const tabs = [
        { key: 'overview', label: 'Overview' },
        { key: 'calendar', label: 'Calendar' },
        { key: 'accounts', label: 'Accounts' },
        { key: 'ai', label: 'AI' },
    ];

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function request(path, options) {
        return fetch(config.restBase + path, Object.assign({
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce,
            },
        }, options || {})).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    throw new Error(data && data.message ? data.message : 'Request failed.');
                }

                return data;
            });
        });
    }

    function TabButton(props) {
        return el(
            'button',
            {
                type: 'button',
                className: 'ace-social-planner-tab' + (props.active ? ' is-active' : ''),
                onClick: function () { props.onSelect(props.tab.key); },
            },
            props.tab.label
        );
    }

    function MetricCard(props) {
        return el('div', { className: 'ace-social-planner-metric' }, [
            el('span', { className: 'ace-social-planner-metric__label', key: 'label' }, props.label),
            el('strong', { className: 'ace-social-planner-metric__value', key: 'value' }, props.value),
            el('span', { className: 'ace-social-planner-metric__hint', key: 'hint' }, props.hint),
        ]);
    }

    function CalendarColumn(props) {
        return el('div', { className: 'ace-social-planner-calendar__column' }, [
            el('div', { className: 'ace-social-planner-calendar__header', key: 'header' }, [
                el('strong', { key: 'day' }, props.day.day),
                el('span', { key: 'date' }, props.day.date),
            ]),
            el('div', { className: 'ace-social-planner-calendar__body', key: 'body' }, props.day.items.length ? props.day.items.map(function (item, index) {
                return el('div', { className: 'ace-social-planner-event', key: index }, [
                    el('span', { className: 'ace-social-planner-event__time', key: 'time' }, item.time),
                    el('strong', { className: 'ace-social-planner-event__title', key: 'title' }, item.title),
                    el('span', { className: 'ace-social-planner-event__network', key: 'network' }, item.network),
                ]);
            }) : el('div', { className: 'ace-social-planner-event ace-social-planner-event--empty' }, 'No scheduled items')),
        ]);
    }

    function SettingField(props) {
        return el('label', { className: 'ace-social-planner-field' }, [
            el('span', { className: 'ace-social-planner-field__label', key: 'label' }, props.label),
            el('input', {
                key: 'input',
                type: props.type || 'text',
                value: props.value || '',
                onChange: function (event) { props.onChange(event.target.value); },
                placeholder: props.placeholder || '',
            }),
        ]);
    }

    function NetworkCard(props) {
        const network = props.network;
        const status = props.status || { configured: false, status: 'Not configured' };

        return el('div', { className: 'ace-social-planner-panel ace-social-planner-network' }, [
            el('div', { className: 'ace-social-planner-network__heading', key: 'heading' }, [
                el('h3', { key: 'title' }, network.label),
                el('span', {
                    key: 'status',
                    className: 'ace-social-planner-badge' + (status.configured ? ' is-ready' : ''),
                }, status.status),
            ]),
            el(SettingField, {
                key: 'account_name',
                label: 'Account Name',
                value: network.account_name,
                onChange: function (value) { props.onChange('account_name', value); },
                placeholder: 'Brand account or profile name',
            }),
            network.app_id !== undefined ? el(SettingField, {
                key: 'app_id',
                label: 'App ID',
                value: network.app_id,
                onChange: function (value) { props.onChange('app_id', value); },
            }) : null,
            network.client_id !== undefined ? el(SettingField, {
                key: 'client_id',
                label: 'Client ID',
                value: network.client_id,
                onChange: function (value) { props.onChange('client_id', value); },
            }) : null,
            network.api_key !== undefined ? el(SettingField, {
                key: 'api_key',
                label: 'API Key',
                value: network.api_key,
                onChange: function (value) { props.onChange('api_key', value); },
            }) : null,
            network.app_secret !== undefined ? el(SettingField, {
                key: 'app_secret',
                type: 'password',
                label: 'App Secret',
                value: network.app_secret,
                onChange: function (value) { props.onChange('app_secret', value); },
            }) : null,
            network.client_secret !== undefined ? el(SettingField, {
                key: 'client_secret',
                type: 'password',
                label: 'Client Secret',
                value: network.client_secret,
                onChange: function (value) { props.onChange('client_secret', value); },
            }) : null,
            network.api_secret !== undefined ? el(SettingField, {
                key: 'api_secret',
                type: 'password',
                label: 'API Secret',
                value: network.api_secret,
                onChange: function (value) { props.onChange('api_secret', value); },
            }) : null,
            el(SettingField, {
                key: 'access_token',
                type: 'password',
                label: 'Access Token',
                value: network.access_token,
                onChange: function (value) { props.onChange('access_token', value); },
                placeholder: 'Optional until direct verification is added',
            }),
        ]);
    }

    function App() {
        const [activeTab, setActiveTab] = useState('overview');
        const [settings, setSettings] = useState(clone(config.settings || {}));
        const [statuses, setStatuses] = useState(clone(config.networkStatuses || {}));
        const [calendar, setCalendar] = useState(clone(config.calendar || []));
        const [apiKey, setApiKey] = useState('');
        const [sourceContent, setSourceContent] = useState('');
        const [aiOutput, setAiOutput] = useState('AI suggestions stay optional. Add a key only when you want generation.');
        const [notice, setNotice] = useState('');
        const [error, setError] = useState('');
        const [isSaving, setIsSaving] = useState(false);
        const [isGenerating, setIsGenerating] = useState(false);
        const hasApiKey = !!config.hasApiKey;

        function updateSetting(key, value) {
            setSettings(function (current) {
                const next = clone(current);
                next[key] = value;
                return next;
            });
        }

        function updateNetwork(networkKey, field, value) {
            setSettings(function (current) {
                const next = clone(current);
                next.networks[networkKey][field] = value;
                return next;
            });
        }

        function saveSettings() {
            setIsSaving(true);
            setNotice('');
            setError('');

            request('settings', {
                method: 'POST',
                body: JSON.stringify({
                    settings: settings,
                    apiKey: apiKey,
                }),
            })
                .then(function (data) {
                    setSettings(clone(data.settings));
                    setStatuses(clone(data.networkStatuses));
                    setCalendar(clone(data.calendar));
                    config.hasApiKey = data.hasApiKey;
                    setApiKey('');
                    setNotice('Settings saved.');
                })
                .catch(function (requestError) {
                    setError(requestError.message);
                })
                .finally(function () {
                    setIsSaving(false);
                });
        }

        function generateDraft() {
            if (!sourceContent.trim()) {
                setError('Add some source content before asking for AI suggestions.');
                setActiveTab('ai');
                return;
            }

            if (!config.hasApiKey) {
                setError('The interface is ready without AI. Add an OpenAI key in the AI tab only when you want suggestions.');
                setActiveTab('ai');
                return;
            }

            setIsGenerating(true);
            setError('');
            setNotice('');
            setAiOutput('Generating...');

            request('ai/generate', {
                method: 'POST',
                body: JSON.stringify({ content: sourceContent }),
            })
                .then(function (data) {
                    setAiOutput(data.output_text || 'No output returned.');
                    setNotice('AI draft generated.');
                })
                .catch(function (requestError) {
                    setAiOutput('No output returned.');
                    setError(requestError.message);
                })
                .finally(function () {
                    setIsGenerating(false);
                });
        }

        const networkKeys = Object.keys(settings.networks || {});
        const configuredCount = networkKeys.filter(function (key) {
            return statuses[key] && statuses[key].configured;
        }).length;

        return el('div', { className: 'ace-social-planner-app' }, [
            el('header', { className: 'ace-social-planner-hero', key: 'hero' }, [
                el('div', { key: 'copy' }, [
                    el('h1', { key: 'title' }, settings.workspace_name || 'ACE Social Planner'),
                    el('p', { key: 'text' }, 'A WordPress-native planning surface for content scheduling, account setup, and optional AI-assisted copy work.'),
                ]),
                el('div', { className: 'ace-social-planner-hero__actions', key: 'actions' }, [
                    el('button', {
                        key: 'save',
                        type: 'button',
                        className: 'button button-primary button-hero',
                        onClick: saveSettings,
                        disabled: isSaving,
                    }, isSaving ? 'Saving...' : 'Save Settings'),
                    el('button', {
                        key: 'calendar',
                        type: 'button',
                        className: 'button',
                        onClick: function () { setActiveTab('calendar'); },
                    }, 'Open Calendar'),
                ]),
            ]),
            notice ? el('div', { className: 'notice notice-success inline', key: 'notice' }, el('p', null, notice)) : null,
            error ? el('div', { className: 'notice notice-error inline', key: 'error' }, el('p', null, error)) : null,
            el('div', { className: 'ace-social-planner-tabs', key: 'tabs' }, tabs.map(function (tab) {
                return el(TabButton, {
                    key: tab.key,
                    tab: tab,
                    active: activeTab === tab.key,
                    onSelect: setActiveTab,
                });
            })),
            activeTab === 'overview' ? el(Fragment, { key: 'overview' }, [
                el('div', { className: 'ace-social-planner-metrics' }, [
                    el(MetricCard, { label: 'Configured Networks', value: String(configuredCount), hint: 'Saved account credentials or tokens' }),
                    el(MetricCard, { label: 'Planner Timezone', value: settings.default_timezone || 'UTC', hint: 'Default schedule timezone' }),
                    el(MetricCard, { label: 'Default Publish Time', value: settings.default_publish_time || '09:00', hint: 'Used for preview schedule blocks' }),
                    el(MetricCard, { label: 'AI Suggestions', value: config.hasApiKey ? 'Enabled' : 'Optional', hint: config.hasApiKey ? 'OpenAI key saved' : 'Interface works without AI' }),
                ]),
                el('div', { className: 'ace-social-planner-layout ace-social-planner-layout--two' }, [
                    el('section', { className: 'ace-social-planner-panel', key: 'workspace' }, [
                        el('h2', null, 'Workspace Settings'),
                        el(SettingField, {
                            label: 'Workspace Name',
                            value: settings.workspace_name,
                            onChange: function (value) { updateSetting('workspace_name', value); },
                        }),
                        el(SettingField, {
                            label: 'Timezone',
                            value: settings.default_timezone,
                            onChange: function (value) { updateSetting('default_timezone', value); },
                        }),
                        el(SettingField, {
                            label: 'Default Publish Time',
                            value: settings.default_publish_time,
                            onChange: function (value) { updateSetting('default_publish_time', value); },
                            placeholder: '09:00',
                        }),
                        el('label', { className: 'ace-social-planner-field' }, [
                            el('span', { className: 'ace-social-planner-field__label', key: 'label' }, 'Week Starts On'),
                            el('select', {
                                key: 'select',
                                value: settings.week_starts_on,
                                onChange: function (event) { updateSetting('week_starts_on', event.target.value); },
                            }, [
                                el('option', { value: 'monday', key: 'monday' }, 'Monday'),
                                el('option', { value: 'sunday', key: 'sunday' }, 'Sunday'),
                            ]),
                        ]),
                    ]),
                    el('section', { className: 'ace-social-planner-panel', key: 'roadmap' }, [
                        el('h2', null, 'Planner Direction'),
                        el('ul', { className: 'ace-social-planner-list' }, [
                            el('li', { key: '1' }, 'Use this screen as the home for planning, accounts, and approvals.'),
                            el('li', { key: '2' }, 'Keep AI optional so the application is still useful before any model is configured.'),
                            el('li', { key: '3' }, 'Treat account setup and scheduling as core product surfaces.'),
                            el('li', { key: '4' }, 'Layer publishing and analytics in after the planner workflow is stable.'),
                        ]),
                    ]),
                ]),
            ]) : null,
            activeTab === 'calendar' ? el('section', { className: 'ace-social-planner-panel', key: 'calendar' }, [
                el('div', { className: 'ace-social-planner-panel__header', key: 'header' }, [
                    el('h2', { key: 'title' }, 'Calendar Preview'),
                    el('p', { key: 'text' }, 'A planning-first weekly view that will later connect to stored drafts, queue items, and approvals.'),
                ]),
                el('div', { className: 'ace-social-planner-calendar' }, calendar.map(function (day, index) {
                    return el(CalendarColumn, { day: day, key: index });
                })),
            ]) : null,
            activeTab === 'accounts' ? el(Fragment, { key: 'accounts' }, [
                el('div', { className: 'ace-social-planner-layout ace-social-planner-layout--accounts' }, networkKeys.map(function (networkKey) {
                    return el(NetworkCard, {
                        key: networkKey,
                        network: settings.networks[networkKey],
                        status: statuses[networkKey],
                        onChange: function (field, value) { updateNetwork(networkKey, field, value); },
                    });
                })),
            ]) : null,
            activeTab === 'ai' ? el('div', { className: 'ace-social-planner-layout ace-social-planner-layout--two', key: 'ai' }, [
                el('section', { className: 'ace-social-planner-panel', key: 'settings' }, [
                    el('h2', null, 'AI Settings'),
                    el('p', null, 'This is optional. The planner UI works without it.'),
                    el(SettingField, {
                        label: 'OpenAI API Key',
                        type: 'password',
                        value: apiKey,
                        onChange: setApiKey,
                        placeholder: hasApiKey ? 'Stored key already present' : 'Paste a key only when needed',
                    }),
                    el('p', { className: 'description' }, hasApiKey ? 'A server-side key is already saved.' : 'No API key saved yet.'),
                ]),
                el('section', { className: 'ace-social-planner-panel', key: 'generator' }, [
                    el('h2', null, 'Suggestion Drafting'),
                    el('label', { className: 'ace-social-planner-field' }, [
                        el('span', { className: 'ace-social-planner-field__label', key: 'label' }, 'Source Content'),
                        el('textarea', {
                            key: 'textarea',
                            rows: 8,
                            value: sourceContent,
                            onChange: function (event) { setSourceContent(event.target.value); },
                            placeholder: 'Paste post notes, campaign ideas, or article copy when you want suggestions.',
                        }),
                    ]),
                    el('div', { className: 'ace-social-planner-actions' }, [
                        el('button', {
                            type: 'button',
                            className: 'button button-secondary',
                            onClick: generateDraft,
                            disabled: isGenerating,
                        }, isGenerating ? 'Generating...' : 'Generate Suggestions'),
                    ]),
                    el('div', { className: 'ace-social-planner-output' }, aiOutput),
                ]),
            ]) : null,
        ]);
    }

    render(el(App), rootNode);
})();
