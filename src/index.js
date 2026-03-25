import { createElement, Fragment, render, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, Modal, SelectControl, TextControl, TextareaControl, Notice } from '@wordpress/components';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import './style.css';

const config = window.aceSocialPlanner || {};
const rootNode = document.getElementById('ace-social-planner-app');

const platformOptions = [
    { label: 'X', value: 'X' },
    { label: 'Facebook', value: 'Facebook' },
    { label: 'Instagram', value: 'Instagram' },
    { label: 'LinkedIn', value: 'LinkedIn' },
];

const statusOptions = [
    { label: 'Not planned', value: 'not_planned' },
    { label: 'Drafted', value: 'drafted' },
    { label: 'Awaiting approval', value: 'awaiting_approval' },
    { label: 'Approved', value: 'approved' },
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'Published', value: 'published' },
    { label: 'Failed', value: 'failed' },
    { label: 'Archived', value: 'archived' },
];

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function request(path, options = {}) {
    return fetch(config.restBase + path, {
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce,
        },
        ...options,
    }).then((response) =>
        response.json().then((data) => {
            if (!response.ok) {
                throw new Error(data && data.message ? data.message : 'Request failed.');
            }

            return data;
        })
    );
}

function SchedulerCalendar({ events, onCreate, onEdit }) {
    const calendarRef = useRef(null);
    const hostRef = useRef(null);
    const latestHandlers = useRef({ onCreate, onEdit });

    latestHandlers.current = { onCreate, onEdit };

    useEffect(() => {
        if (!hostRef.current) {
            return undefined;
        }

        const calendar = new Calendar(hostRef.current, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
            selectable: true,
            editable: false,
            nowIndicator: true,
            height: 'auto',
            events,
            select(selectionInfo) {
                latestHandlers.current.onCreate({
                    title: '',
                    platform: 'X',
                    status: 'drafted',
                    start: selectionInfo.startStr,
                    end: selectionInfo.endStr,
                    allDay: selectionInfo.allDay,
                    notes: '',
                });
                calendar.unselect();
            },
            eventClick(clickInfo) {
                latestHandlers.current.onEdit({
                    id: clickInfo.event.id,
                    title: clickInfo.event.title,
                    platform: clickInfo.event.extendedProps.platform || 'X',
                    status: clickInfo.event.extendedProps.status || 'drafted',
                    start: clickInfo.event.startStr,
                    end: clickInfo.event.endStr || clickInfo.event.startStr,
                    allDay: clickInfo.event.allDay,
                    notes: clickInfo.event.extendedProps.notes || '',
                });
            },
            eventContent(eventInfo) {
                const wrapper = document.createElement('div');
                wrapper.className = 'ace-calendar-event';

                const time = document.createElement('div');
                time.className = 'ace-calendar-event__time';
                time.textContent = eventInfo.timeText || '';

                const title = document.createElement('div');
                title.className = 'ace-calendar-event__title';
                title.textContent = eventInfo.event.title;

                const meta = document.createElement('div');
                meta.className = 'ace-calendar-event__meta';
                meta.textContent = `${eventInfo.event.extendedProps.platform || ''} · ${String(eventInfo.event.extendedProps.status || '').replace(/_/g, ' ')}`;

                wrapper.appendChild(time);
                wrapper.appendChild(title);
                wrapper.appendChild(meta);

                return { domNodes: [wrapper] };
            },
        });

        calendar.render();
        calendarRef.current = calendar;

        return () => {
            calendar.destroy();
            calendarRef.current = null;
        };
    }, []);

    useEffect(() => {
        if (!calendarRef.current) {
            return;
        }

        calendarRef.current.removeAllEvents();
        events.forEach((event) => {
            calendarRef.current.addEvent(event);
        });
    }, [events]);

    return createElement('div', { className: 'ace-social-planner-calendar-shell' }, createElement('div', { ref: hostRef }));
}

function EventModal({ eventDraft, onClose, onSave, onDelete }) {
    if (!eventDraft) {
        return null;
    }

    const isNew = !eventDraft.id;

    return createElement(
        Modal,
        {
            title: isNew ? 'Create scheduled post' : 'Edit scheduled post',
            onRequestClose: onClose,
            className: 'ace-social-planner-modal',
        },
        createElement(Fragment, null, [
            createElement(TextControl, {
                key: 'title',
                label: 'Post title',
                value: eventDraft.title,
                onChange: (value) => onSave({ ...eventDraft, title: value }, true),
            }),
            createElement(SelectControl, {
                key: 'platform',
                label: 'Platform',
                value: eventDraft.platform,
                options: platformOptions,
                onChange: (value) => onSave({ ...eventDraft, platform: value }, true),
            }),
            createElement(SelectControl, {
                key: 'status',
                label: 'Status',
                value: eventDraft.status,
                options: statusOptions,
                onChange: (value) => onSave({ ...eventDraft, status: value }, true),
            }),
            createElement(TextControl, {
                key: 'start',
                label: 'Start',
                type: eventDraft.allDay ? 'date' : 'datetime-local',
                value: eventDraft.start,
                onChange: (value) => onSave({ ...eventDraft, start: value }, true),
            }),
            createElement(TextControl, {
                key: 'end',
                label: 'End',
                type: eventDraft.allDay ? 'date' : 'datetime-local',
                value: eventDraft.end,
                onChange: (value) => onSave({ ...eventDraft, end: value }, true),
            }),
            createElement(TextareaControl, {
                key: 'notes',
                label: 'Notes',
                value: eventDraft.notes,
                onChange: (value) => onSave({ ...eventDraft, notes: value }, true),
            }),
            createElement('div', { key: 'actions', className: 'ace-social-planner-modal__actions' }, [
                !isNew
                    ? createElement(Button, { key: 'delete', variant: 'secondary', isDestructive: true, onClick: () => onDelete(eventDraft.id) }, 'Delete')
                    : null,
                createElement(Button, { key: 'cancel', variant: 'tertiary', onClick: onClose }, 'Cancel'),
                createElement(Button, { key: 'save', variant: 'primary', onClick: () => onSave(eventDraft, false) }, isNew ? 'Create' : 'Save'),
            ]),
        ])
    );
}

function App() {
    const [settings, setSettings] = useState(clone(config.settings || {}));
    const [networkStatuses, setNetworkStatuses] = useState(clone(config.networkStatuses || {}));
    const [plannerItems, setPlannerItems] = useState(clone(config.plannerItems || []));
    const [notice, setNotice] = useState(config.notices?.success || '');
    const [error, setError] = useState(config.notices?.error || '');
    const [activeTab, setActiveTab] = useState('calendar');
    const [isSavingSettings, setIsSavingSettings] = useState(false);
    const [isConnectingX, setIsConnectingX] = useState(false);
    const [isConnectingFacebook, setIsConnectingFacebook] = useState(false);
    const [isPublishingX, setIsPublishingX] = useState(false);
    const [isPublishingFacebook, setIsPublishingFacebook] = useState(false);
    const [eventDraft, setEventDraft] = useState(null);
    const [apiKey, setApiKey] = useState('');
    const [sourceContent, setSourceContent] = useState('');
    const [aiOutput, setAiOutput] = useState('AI suggestions remain optional. Use them when you want stronger platform-specific drafts.');
    const [isGenerating, setIsGenerating] = useState(false);
    const [xTestText, setXTestText] = useState('Testing ACE Social Planner -> X.');
    const [facebookTestMessage, setFacebookTestMessage] = useState('Testing ACE Social Planner -> Facebook Page.');
    const [facebookTestLink, setFacebookTestLink] = useState('');
    const statuses = networkStatuses || {};
    const hasApiKey = !!config.hasApiKey;

    const events = useMemo(
        () => plannerItems.map((item) => ({
            id: String(item.id),
            title: item.title,
            start: item.start,
            end: item.end,
            allDay: !!item.allDay,
            extendedProps: {
                platform: item.platform,
                status: item.status,
                notes: item.notes || '',
            },
        })),
        [plannerItems]
    );

    function refreshBootstrap(data) {
        config.networkStatuses = data.networkStatuses || config.networkStatuses;
        config.hasApiKey = data.hasApiKey;
        setSettings(clone(data.settings));
        setNetworkStatuses(clone(data.networkStatuses || config.networkStatuses || {}));
        setPlannerItems(clone(data.plannerItems || []));
    }

    function saveSettings() {
        setIsSavingSettings(true);
        setNotice('');
        setError('');

        request('settings', {
            method: 'POST',
            body: JSON.stringify({ settings, apiKey }),
        })
            .then((data) => {
                refreshBootstrap(data);
                setApiKey('');
                setNotice('Settings saved.');
            })
            .catch((requestError) => setError(requestError.message))
            .finally(() => setIsSavingSettings(false));
    }

    function connectX() {
        setIsConnectingX(true);
        setNotice('');
        setError('');

        request('providers/x/connect-url', { method: 'GET' })
            .then((data) => {
                window.location.href = data.authorizeUrl;
            })
            .catch((requestError) => {
                setError(requestError.message);
                setIsConnectingX(false);
            });
    }

    function disconnectX() {
        setIsConnectingX(true);
        setNotice('');
        setError('');

        request('providers/x/disconnect', { method: 'POST', body: JSON.stringify({}) })
            .then(() => request('settings', { method: 'GET' }))
            .then((data) => {
                refreshBootstrap(data);
                setNotice('X connection removed.');
            })
            .catch((requestError) => setError(requestError.message))
            .finally(() => setIsConnectingX(false));
    }

    function connectFacebook() {
        setIsConnectingFacebook(true);
        setNotice('');
        setError('');

        request('providers/facebook/connect-url', { method: 'GET' })
            .then((data) => {
                window.location.href = data.authorizeUrl;
            })
            .catch((requestError) => {
                setError(requestError.message);
                setIsConnectingFacebook(false);
            });
    }

    function disconnectFacebook() {
        setIsConnectingFacebook(true);
        setNotice('');
        setError('');

        request('providers/facebook/disconnect', { method: 'POST', body: JSON.stringify({}) })
            .then(() => request('settings', { method: 'GET' }))
            .then((data) => {
                refreshBootstrap(data);
                setNotice('Facebook connection removed.');
            })
            .catch((requestError) => setError(requestError.message))
            .finally(() => setIsConnectingFacebook(false));
    }

    function selectFacebookPage(pageId) {
        setNotice('');
        setError('');

        request('providers/facebook/select-page', {
            method: 'POST',
            body: JSON.stringify({ pageId }),
        })
            .then(() => request('settings', { method: 'GET' }))
            .then((data) => {
                refreshBootstrap(data);
                setNotice('Facebook page selected.');
            })
            .catch((requestError) => setError(requestError.message));
    }

    function publishTestX() {
        setNotice('');
        setError('');
        setIsPublishingX(true);

        request('providers/x/publish-test', {
            method: 'POST',
            body: JSON.stringify({ text: xTestText }),
        })
            .then((data) => {
                setNotice('Posted test tweet ' + data.tweetId + '.');
            })
            .catch((requestError) => setError(requestError.message))
            .finally(() => setIsPublishingX(false));
    }

    function publishTestFacebook() {
        setNotice('');
        setError('');
        setIsPublishingFacebook(true);

        request('providers/facebook/publish-test', {
            method: 'POST',
            body: JSON.stringify({
                message: facebookTestMessage,
                link: facebookTestLink,
            }),
        })
            .then((data) => {
                setNotice('Posted to Facebook page ' + data.pageName + '. Post ID: ' + data.postId + '.');
            })
            .catch((requestError) => setError(requestError.message))
            .finally(() => setIsPublishingFacebook(false));
    }

    function handleDraftChange(nextDraft, silent) {
        setEventDraft(nextDraft);
        if (!silent) {
            savePlannerItem(nextDraft);
        }
    }

    function savePlannerItem(item) {
        setNotice('');
        setError('');

        request('planner-items', {
            method: 'POST',
            body: JSON.stringify({ item }),
        })
            .then((data) => {
                setPlannerItems(clone(data.items || []));
                setEventDraft(null);
                setNotice(item.id ? 'Scheduled post updated.' : 'Scheduled post created.');
            })
            .catch((requestError) => setError(requestError.message));
    }

    function deletePlannerItem(id) {
        setNotice('');
        setError('');

        request(`planner-items/${id}`, { method: 'DELETE' })
            .then((data) => {
                setPlannerItems(clone(data.items || []));
                setEventDraft(null);
                setNotice('Scheduled post deleted.');
            })
            .catch((requestError) => setError(requestError.message));
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
        setNotice('');
        setError('');
        setAiOutput('Generating...');

        request('ai/generate', {
            method: 'POST',
            body: JSON.stringify({ content: sourceContent }),
        })
            .then((data) => {
                setAiOutput(data.output_text || 'No output returned.');
                setNotice('AI draft generated.');
            })
            .catch((requestError) => {
                setAiOutput('No output returned.');
                setError(requestError.message);
            })
            .finally(() => setIsGenerating(false));
    }

    return createElement('div', { className: 'ace-social-planner-app' }, [
        createElement('header', { className: 'ace-social-planner-hero', key: 'hero' }, [
            createElement('div', { key: 'copy' }, [
                createElement('h1', { key: 'title' }, settings.workspace_name || 'ACE Social Planner'),
                createElement('p', { key: 'text' }, 'A WordPress-native social scheduler for queueing posts, managing social connections, and layering AI suggestions in when they are useful.'),
            ]),
            createElement('div', { className: 'ace-social-planner-hero__actions', key: 'actions' }, [
                createElement(Button, { key: 'save', variant: 'primary', onClick: saveSettings, disabled: isSavingSettings }, isSavingSettings ? 'Saving...' : 'Save Settings'),
                createElement(Button, { key: 'x', variant: 'secondary', onClick: connectX, disabled: isConnectingX }, statuses.x?.connected ? 'Reconnect X' : 'Connect X'),
                statuses.x?.connected ? createElement(Button, { key: 'disconnect', variant: 'tertiary', isDestructive: true, onClick: disconnectX, disabled: isConnectingX }, 'Disconnect X') : null,
            ]),
        ]),
        notice ? createElement(Notice, { key: 'notice', status: 'success', isDismissible: true, onRemove: () => setNotice('') }, notice) : null,
        error ? createElement(Notice, { key: 'error', status: 'error', isDismissible: true, onRemove: () => setError('') }, error) : null,
        createElement('div', { className: 'ace-social-planner-tabs', key: 'tabs' }, [
            ['overview', 'Overview'],
            ['calendar', 'Scheduler'],
            ['accounts', 'Connections'],
            ['ai', 'AI'],
        ].map(([key, label]) =>
            createElement('button', {
                key,
                type: 'button',
                className: 'ace-social-planner-tab' + (activeTab === key ? ' is-active' : ''),
                onClick: () => setActiveTab(key),
            }, label)
        )),
        activeTab === 'overview' ? createElement('div', { key: 'overview', className: 'ace-social-planner-metrics' }, [
            createElement('div', { className: 'ace-social-planner-metric', key: 'scheduled' }, [
                createElement('span', { className: 'ace-social-planner-metric__label' }, 'Scheduled posts'),
                createElement('strong', { className: 'ace-social-planner-metric__value' }, String(plannerItems.length)),
                createElement('span', { className: 'ace-social-planner-metric__hint' }, 'Live scheduler events in WordPress'),
            ]),
            createElement('div', { className: 'ace-social-planner-metric', key: 'x' }, [
                createElement('span', { className: 'ace-social-planner-metric__label' }, 'X connection'),
                createElement('strong', { className: 'ace-social-planner-metric__value' }, statuses.x?.connected ? '@' + statuses.x.username : 'Pending'),
                createElement('span', { className: 'ace-social-planner-metric__hint' }, statuses.x?.connected ? 'Ready for future publishing flows' : 'Connect an account to start publishing setup'),
            ]),
        ]) : null,
        activeTab === 'calendar' ? createElement('section', { className: 'ace-social-planner-panel', key: 'calendar' }, [
            createElement('div', { className: 'ace-social-planner-panel__header', key: 'header' }, [
                createElement('h2', { key: 'title' }, 'Social Scheduler'),
                createElement('p', { key: 'text' }, 'Month, week, and day views for scheduling social posts. Click and drag on the calendar to create a scheduled item, then edit the platform, status, and notes.'),
            ]),
            createElement(SchedulerCalendar, {
                key: 'calendar-ui',
                events,
                onCreate: setEventDraft,
                onEdit: setEventDraft,
            }),
        ]) : null,
        activeTab === 'accounts' ? createElement('section', { className: 'ace-social-planner-panel', key: 'accounts' }, [
            createElement('h2', { key: 'title' }, 'Connections and Posting'),
            createElement('p', { key: 'intro', className: 'description' }, 'Goal: save credentials once, click connect, approve in the social platform, and run a real test post from WordPress.'),
            createElement('div', { key: 'x-card', className: 'ace-social-planner-network-card' }, [
                createElement('h3', { key: 'x-heading' }, 'X (Twitter)'),
                createElement(TextControl, {
                    key: 'x-client-id',
                    label: 'OAuth Client ID',
                    value: settings.networks?.x?.client_id || '',
                    onChange: (value) => setSettings((current) => ({ ...current, networks: { ...current.networks, x: { ...current.networks.x, client_id: value } } })),
                }),
                createElement('p', { key: 'x-callback', className: 'description' }, 'Callback URL: ' + (statuses.x?.callback_url || '')),
                createElement('p', { key: 'x-help-1', className: 'description' }, createElement('a', { href: 'https://developer.x.com/en/portal/dashboard', target: '_blank', rel: 'noreferrer noopener' }, 'Create/manage X app in Developer Portal')),
                createElement('p', { key: 'x-help-2', className: 'description' }, createElement('a', { href: 'https://developer.x.com/en/docs/authentication/oauth-2-0/authorization-code', target: '_blank', rel: 'noreferrer noopener' }, 'X OAuth 2.0 Authorization Code + PKCE guide')),
                createElement('p', { key: 'x-status', className: 'description' }, statuses.x?.connected ? 'Connected as @' + statuses.x.username : 'Save settings, then click Connect X.'),
                createElement('div', { key: 'x-actions', className: 'ace-social-planner-inline-actions' }, [
                    createElement(Button, { key: 'x-connect', variant: 'secondary', onClick: connectX, disabled: isConnectingX }, statuses.x?.connected ? 'Reconnect X' : 'Connect X'),
                    statuses.x?.connected ? createElement(Button, { key: 'x-disconnect', variant: 'tertiary', isDestructive: true, onClick: disconnectX, disabled: isConnectingX }, 'Disconnect X') : null,
                ]),
                createElement(TextareaControl, {
                    key: 'x-test-text',
                    label: 'Test post text for X',
                    value: xTestText,
                    onChange: setXTestText,
                }),
                createElement(Button, {
                    key: 'x-test-publish',
                    variant: 'primary',
                    onClick: publishTestX,
                    disabled: isPublishingX || !statuses.x?.connected,
                }, isPublishingX ? 'Posting to X...' : 'Send Test Post to X'),
            ]),
            createElement('div', { key: 'facebook-card', className: 'ace-social-planner-network-card' }, [
                createElement('h3', { key: 'facebook-heading' }, 'Facebook'),
                createElement(TextControl, {
                    key: 'facebook-app-id',
                    label: 'App ID',
                    value: settings.networks?.facebook?.app_id || '',
                    onChange: (value) => setSettings((current) => ({ ...current, networks: { ...current.networks, facebook: { ...current.networks.facebook, app_id: value } } })),
                }),
                createElement(TextControl, {
                    key: 'facebook-app-secret',
                    label: 'App Secret',
                    type: 'password',
                    value: settings.networks?.facebook?.app_secret || '',
                    onChange: (value) => setSettings((current) => ({ ...current, networks: { ...current.networks, facebook: { ...current.networks.facebook, app_secret: value } } })),
                }),
                createElement('p', { key: 'facebook-callback', className: 'description' }, 'Callback URL: ' + (statuses.facebook?.callback_url || '')),
                createElement('p', { key: 'facebook-help-1', className: 'description' }, createElement('a', { href: 'https://developers.facebook.com/apps/', target: '_blank', rel: 'noreferrer noopener' }, 'Create/manage Facebook app in Meta for Developers')),
                createElement('p', { key: 'facebook-help-2', className: 'description' }, createElement('a', { href: 'https://developers.facebook.com/docs/permissions/reference/pages_manage_posts/', target: '_blank', rel: 'noreferrer noopener' }, 'Permission: pages_manage_posts')),
                createElement('p', { key: 'facebook-help-3', className: 'description' }, createElement('a', { href: 'https://developers.facebook.com/docs/permissions/reference/pages_show_list/', target: '_blank', rel: 'noreferrer noopener' }, 'Permission: pages_show_list')),
                createElement('p', { key: 'facebook-status', className: 'description' }, statuses.facebook?.connected
                    ? 'Connected as ' + (statuses.facebook.name || 'Facebook user')
                    : 'Save settings, then click Connect Facebook.'),
                createElement('div', { key: 'facebook-actions', className: 'ace-social-planner-inline-actions' }, [
                    createElement(Button, { key: 'facebook-connect', variant: 'secondary', onClick: connectFacebook, disabled: isConnectingFacebook }, statuses.facebook?.connected ? 'Reconnect Facebook' : 'Connect Facebook'),
                    statuses.facebook?.connected ? createElement(Button, { key: 'facebook-disconnect', variant: 'tertiary', isDestructive: true, onClick: disconnectFacebook, disabled: isConnectingFacebook }, 'Disconnect Facebook') : null,
                ]),
                createElement(SelectControl, {
                    key: 'facebook-page-select',
                    label: 'Page to publish to',
                    value: statuses.facebook?.selected_page_id || '',
                    options: [{ label: statuses.facebook?.connected ? 'Select a Page' : 'Connect Facebook first', value: '' }]
                        .concat((statuses.facebook?.pages || []).map((page) => ({ label: page.name, value: page.id }))),
                    onChange: (value) => {
                        if (value) {
                            selectFacebookPage(value);
                        }
                    },
                }),
                createElement('p', { key: 'facebook-page-note', className: 'description' }, statuses.facebook?.profile_posting_supported
                    ? 'Profile publishing is enabled for this app.'
                    : 'Facebook API typically publishes to Pages, not personal profiles. Choose a Page above.'),
                createElement(TextareaControl, {
                    key: 'facebook-test-message',
                    label: 'Test post message for Facebook',
                    value: facebookTestMessage,
                    onChange: setFacebookTestMessage,
                }),
                createElement(TextControl, {
                    key: 'facebook-test-link',
                    label: 'Optional link URL',
                    value: facebookTestLink,
                    onChange: setFacebookTestLink,
                }),
                createElement(Button, {
                    key: 'facebook-test-publish',
                    variant: 'primary',
                    onClick: publishTestFacebook,
                    disabled: isPublishingFacebook || !statuses.facebook?.connected || !statuses.facebook?.selected_page_id,
                }, isPublishingFacebook ? 'Posting to Facebook...' : 'Send Test Post to Facebook Page'),
            ]),
        ]) : null,
        activeTab === 'ai' ? createElement('section', { className: 'ace-social-planner-panel', key: 'ai' }, [
            createElement(TextControl, {
                key: 'api-key',
                label: 'OpenAI API Key',
                type: 'password',
                value: apiKey,
                onChange: setApiKey,
                help: hasApiKey ? 'A server-side key is already saved.' : 'Optional. The scheduler works without AI.',
            }),
            createElement(TextareaControl, {
                key: 'source',
                label: 'Source content',
                value: sourceContent,
                onChange: setSourceContent,
            }),
            createElement(Button, { key: 'generate', variant: 'secondary', onClick: generateDraft, disabled: isGenerating }, isGenerating ? 'Generating...' : 'Generate Suggestions'),
            createElement('div', { key: 'output', className: 'ace-social-planner-output' }, aiOutput),
        ]) : null,
        createElement(EventModal, {
            key: 'modal',
            eventDraft,
            onClose: () => setEventDraft(null),
            onSave: handleDraftChange,
            onDelete: deletePlannerItem,
        }),
    ]);
}

if (rootNode) {
    render(createElement(App), rootNode);
}
