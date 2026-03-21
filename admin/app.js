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
        { key: 'calendar', label: 'Scheduler' },
        { key: 'accounts', label: 'Connections' },
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
                    el('span', { className: 'ace-social-planner-event__network', key: 'network' }, item.network + ' · ' + item.status),
                ]);
            }) : el('div', { className: 'ace-social-planner-event ace-social-planner-event--empty' }, 'No scheduled posts')),
        ]);
    }

    function SettingField(props) {
        return el('label', { className: 'ace-social-planner-field' }, [
            el('span', { className: 'ace-social-planner-field__label', key: 'label' }, props.label),
            props.type === 'textarea'
                ? el('textarea', {
                    key: 'input',
                    rows: props.rows || 4,
                    value: props.value || '',
                    onChange: function (event) { props.onChange(event.target.value); },
                    placeholder: props.placeholder || '',
                })
                : el(props.type === 'select' ? 'select' : 'input', Object.assign({
                    key: 'input',
                    value: props.value || '',
                    onChange: function (event) { props.onChange(event.target.value); },
                }, props.type === 'select'
                    ? {}
                    : { type: props.type || 'text', placeholder: props.placeholder || '' }), props.type === 'select'
                    ? (props.options || []).map(function (option) {
                        return el('option', { key: option.value, value: option.value }, option.label);
                    })
                    : null),
        ]);
    }

    function AccountCard(props) {
        const network = props.network;
        const status = props.status || { configured: false, status: 'Not configured' };
        const actions = [];

        if (props.networkKey === 'x') {
            actions.push(el('button', {
                key: 'connect',
                type: 'button',
                className: 'button button-secondary',
                onClick: props.onConnect,
                disabled: props.isBusy,
            }, status.connected ? 'Reconnect X' : 'Connect X'));

            if (status.connected) {
                actions.push(el('button', {
                    key: 'disconnect',
                    type: 'button',
                    className: 'button button-link-delete',
                    onClick: props.onDisconnect,
                    disabled: props.isBusy,
                }, 'Disconnect'));
            }
        }

        return el('div', { className: 'ace-social-planner-panel ace-social-planner-network' }, [
            el('div', { className: 'ace-social-planner-network__heading', key: 'heading' }, [
                el('h3', { key: 'title' }, network.label),
                el('span', {
                    key: 'status',
                    className: 'ace-social-planner-badge' + (status.connected || status.configured ? ' is-ready' : ''),
                }, status.status),
            ]),
            props.networkKey === 'x' ? el('div', { className: 'ace-social-planner-callout ace-social-planner-callout--x', key: 'callout' }, [
                el('strong', { key: 'title' }, 'X OAuth callback'),
                el('code', { key: 'code' }, status.callback_url || ''),
                el('p', { key: 'text' }, 'Set this exact callback URL in your X developer app. PKCE is used for approval and the user account is verified after return.'),
            ]) : null,
            el(SettingField, {
                key: 'account_name',
                label: 'Account Label',
                value: network.account_name,
                onChange: function (value) { props.onChange('account_name', value); },
                placeholder: 'Internal label for this social account',
            }),
            network.client_id !== undefined ? el(SettingField, {
                key: 'client_id',
                label: props.networkKey === 'x' ? 'OAuth Client ID' : 'Client ID',
                value: network.client_id,
                onChange: function (value) { props.onChange('client_id', value); },
            }) : null,
            network.client_secret !== undefined ? el(SettingField, {
                key: 'client_secret',
                type: 'password',
                label: props.networkKey === 'x' ? 'Client Secret (optional for now)' : 'Client Secret',
                value: network.client_secret,
                onChange: function (value) { props.onChange('client_secret', value); },
            }) : null,
            network.app_id !== undefined ? el(SettingField, {
                key: 'app_id',
                label: 'App ID',
                value: network.app_id,
                onChange: function (value) { props.onChange('app_id', value); },
            }) : null,
            network.app_secret !== undefined ? el(SettingField, {
                key: 'app_secret',
                type: 'password',
                label: 'App Secret',
                value: network.app_secret,
                onChange: function (value) { props.onChange('app_secret', value); },
            }) : null,
            el('div', { className: 'ace-social-planner-actions ace-social-planner-actions--tight', key: 'actions' }, actions),
            props.networkKey === 'x' && status.connected ? el('p', { className: 'description', key: 'connected' }, 'Connected as @' + (status.username || '') + (status.connected_at ? ' on ' + status.connected_at : '') + '.') : null,
        ]);
    }

    function App() {
        const [activeTab, setActiveTab] = useState('overview');
        const [settings, setSettings] = useState(clone(config.settings || {}));
        const [statuses, setStatuses] = useState(clone(config.networkStatuses || {}));
        const [calendar, setCalendar] = useState(clone(config.calendar || []));
        const [apiKey, setApiKey] = useState('');
        const [sourceContent, setSourceContent] = useState('');
        const [aiOutput, setAiOutput] = useState('AI suggestions remain optional. Use them when you want stronger platform-specific drafts.');
        const [notice, setNotice] = useState(config.notices && config.notices.success ? config.notices.success : '');
        const [error, setError] = useState(config.notices && config.notices.error ? config.notices.error : '');
        const [isSaving, setIsSaving] = useState(false);
        const [isGenerating, setIsGenerating] = useState(false);
        const [isConnectingX, setIsConnectingX] = useState(false);
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

        function applyBootstrap(data) {
            setSettings(clone(data.settings));
            setStatuses(clone(data.networkStatuses));
            setCalendar(clone(data.calendar));
            config.hasApiKey = data.hasApiKey;
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
                    applyBootstrap(data);
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

        function connectX() {
            setIsConnectingX(true);
            setError('');
            setNotice('');

            request('providers/x/connect-url', { method: 'GET' })
                .then(function (data) {
                    window.location.href = data.authorizeUrl;
                })
                .catch(function (requestError) {
                    setError(requestError.message);
                    setIsConnectingX(false);
                });
        }

        function disconnectX() {
            setIsConnectingX(true);
            setError('');
            setNotice('');

            request('providers/x/disconnect', { method: 'POST', body: JSON.stringify({}) })
                .then(function () {
                    return request('settings', { method: 'GET' });
                })
                .then(function (data) {
                    applyBootstrap(data);
                    setNotice('X connection removed.');
                })
                .catch(function (requestError) {
                    setError(requestError.message);
                })
                .finally(function () {
                    setIsConnectingX(false);
                });
        }

        function generateDraft() {
            if (!sourceContent.trim()) {
                setError('Add some source content before asking for AI suggestions.');
                setActiveTab('ai');
                return;
            }

            if (!config.hasApiKey) {
                setError('The scheduler works without AI. Add an OpenAI key only when you want AI draft suggestions.');
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
            return statuses[key] && (statuses[key].configured || statuses[key].connected);
        }).length;
        const scheduledCount = calendar.reduce(function (count, day) {
            return count + day.items.length;
        }, 0);
        const xStatus = statuses.x || {};

        return el('div', { className: 'ace-social-planner-app' }, [
            el('header', { className: 'ace-social-planner-hero', key: 'hero' }, [
                el('div', { key: 'copy' }, [
                    el('h1', { key: 'title' }, settings.workspace_name || 'ACE Social Planner'),
                    el('p', { key: 'text' }, 'A WordPress-native social scheduler for queueing posts, managing social connections, and layering AI suggestions in when they are useful.'),
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
                        key: 'x',
                        type: 'button',
                        className: 'button',
                        onClick: connectX,
                        disabled: isConnectingX,
                    }, xStatus.connected ? 'Reconnect X' : 'Connect X'),
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
                    el(MetricCard, { label: 'Connected Networks', value: String(configuredCount), hint: 'Accounts ready for scheduling' }),
                    el(MetricCard, { label: 'Scheduled Posts', value: String(scheduledCount), hint: 'Visible in the weekly scheduler preview' }),
                    el(MetricCard, { label: 'Default Send Time', value: settings.default_publish_time || '09:00', hint: 'Applied to new scheduled slots' }),
                    el(MetricCard, { label: 'X Connection', value: xStatus.connected ? '@' + (xStatus.username || 'connected') : 'Pending', hint: xStatus.connected ? 'User approval completed' : 'Set client ID then approve the app' }),
                ]),
                el('div', { className: 'ace-social-planner-layout ace-social-planner-layout--two' }, [
                    el('section', { className: 'ace-social-planner-panel', key: 'workspace' }, [
                        el('h2', null, 'Scheduler Defaults'),
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
                            label: 'Default Schedule Time',
                            value: settings.default_publish_time,
                            onChange: function (value) { updateSetting('default_publish_time', value); },
                            placeholder: '09:00',
                        }),
                        el(SettingField, {
                            label: 'Week Starts On',
                            type: 'select',
                            value: settings.week_starts_on,
                            onChange: function (value) { updateSetting('week_starts_on', value); },
                            options: [
                                { value: 'monday', label: 'Monday' },
                                { value: 'sunday', label: 'Sunday' },
                            ],
                        }),
                    ]),
                    el('section', { className: 'ace-social-planner-panel', key: 'queue' }, [
                        el('h2', null, 'Queue Focus'),
                        el('ul', { className: 'ace-social-planner-list' }, [
                            el('li', { key: '1' }, 'The calendar is a scheduler for social posts, not an editorial planner.'),
                            el('li', { key: '2' }, 'Connection approval comes first so scheduling can target real accounts.'),
                            el('li', { key: '3' }, 'X is the first provider path and should become the pattern for later networks.'),
                            el('li', { key: '4' }, 'AI remains optional and should support the queue, not define the app.'),
                        ]),
                    ]),
                ]),
            ]) : null,
            activeTab === 'calendar' ? el('section', { className: 'ace-social-planner-panel', key: 'calendar' }, [
                el('div', { className: 'ace-social-planner-panel__header', key: 'header' }, [
                    el('h2', { key: 'title' }, 'Social Scheduler'),
                    el('p', { key: 'text' }, 'This weekly view represents scheduled social posts and queue slots, similar to a lightweight Buffer-style scheduler inside WordPress.'),
                ]),
                el('div', { className: 'ace-social-planner-calendar' }, calendar.map(function (day, index) {
                    return el(CalendarColumn, { day: day, key: index });
                })),
            ]) : null,
            activeTab === 'accounts' ? el(Fragment, { key: 'accounts' }, [
                el('div', { className: 'ace-social-planner-layout ace-social-planner-layout--accounts' }, networkKeys.map(function (networkKey) {
                    return el(AccountCard, {
                        key: networkKey,
                        networkKey: networkKey,
                        network: settings.networks[networkKey],
                        status: statuses[networkKey],
                        isBusy: isConnectingX && networkKey === 'x',
                        onChange: function (field, value) { updateNetwork(networkKey, field, value); },
                        onConnect: connectX,
                        onDisconnect: disconnectX,
                    });
                })),
            ]) : null,
            activeTab === 'ai' ? el('div', { className: 'ace-social-planner-layout ace-social-planner-layout--two', key: 'ai' }, [
                el('section', { className: 'ace-social-planner-panel', key: 'settings' }, [
                    el('h2', null, 'AI Settings'),
                    el('p', null, 'AI supports stronger copy suggestions for queued social posts but should never be required for scheduling or connections.'),
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
                    el('h2', null, 'Social Draft Suggestions'),
                    el(SettingField, {
                        label: 'Source Content',
                        type: 'textarea',
                        rows: 8,
                        value: sourceContent,
                        onChange: setSourceContent,
                        placeholder: 'Paste post notes, excerpt text, or a published article summary.',
                    }),
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
