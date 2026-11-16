// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Loader} from 'sulu-admin-bundle/components';
import {Requester, resourceRouteRegistry} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import type {ViewProps} from 'sulu-admin-bundle/containers';
import {formatDate, label} from '../../utils/format';
import styles from './handoff.scss';

const RESOURCE_KEY = 'site_assistant_handoffs';

// One case handed to the team: what the visitor needs, how to reach them, and moving it along.
@observer
class Handoff extends React.Component<ViewProps> {
    @observable handoff: ?Object = undefined;
    @observable failed: boolean = false;
    @observable saving: ?string = undefined;
    @observable error: ?string = undefined;
    showSuccess = observable.box(false);

    componentDidMount() {
        Requester.get(this.url())
            .then(action((handoff) => {
                this.handoff = handoff;
            }))
            .catch(action(() => {
                this.failed = true;
            }));
    }

    url(parameters: Object = {}): string {
        return resourceRouteRegistry.getUrl('detail', RESOURCE_KEY, {id: this.props.router.attributes.id, ...parameters});
    }

    @action move = (moveAction: string) => {
        this.saving = moveAction;
        this.error = undefined;
        Requester.put(this.url({action: moveAction}), {})
            .then(action((handoff) => {
                this.handoff = handoff;
                this.saving = undefined;
                this.showSuccess.set(true);
            }))
            .catch(action((response) => {
                this.saving = undefined;
                this.error = translate('sulu_admin.unexpected_error');
                if (response && typeof response.json === 'function') {
                    response.json().then(action((data) => {
                        this.error = data.message || this.error;
                    }));
                }
            }));
    };

    openConversation = () => {
        const {router} = this.props;
        router.navigate(router.route.options.conversationView, {id: this.handoff?.conversationId});
    };

    render() {
        const {handoff, failed} = this;
        const {router} = this.props;

        if (failed) {
            return <p className={styles.empty}>{translate('sulu_admin.unexpected_error')}</p>;
        }
        if (!handoff) {
            return <Loader />;
        }

        return (
            <div className={styles.handoff}>
                <h1 className={styles.title}>
                    {translate('odiseo_site_assistant.case')} <code>{handoff.reference}</code>
                    <span className={`${styles.state} ${styles[`state_${handoff.state}`] || ""}`}>{label('handoff_state', handoff.state)}</span>
                </h1>

                <dl className={styles.facts}>
                    <Fact label={translate('odiseo_site_assistant.topic')} value={label('handoff_topic', handoff.topic)} />
                    <Fact label={translate('odiseo_site_assistant.path')} value={handoff.path ? label('handoff_path', handoff.path) : translate('odiseo_site_assistant.no_path')} />
                    <Fact label={translate('odiseo_site_assistant.created')} value={formatDate(handoff.createdAt)} />
                    <Fact label={translate('odiseo_site_assistant.last_activity')} value={formatDate(handoff.updatedAt)} />
                    <Fact label={translate('odiseo_site_assistant.notified')} value={formatDate(handoff.notifiedAt)} />
                    <Fact label={translate('odiseo_site_assistant.webspace')} value={handoff.webspaceKey} />
                    <Fact label={translate('odiseo_site_assistant.language')} value={handoff.locale} />
                    <Fact label={translate('odiseo_site_assistant.last_page')} value={handoff.page} />
                </dl>

                <h2 className={styles.heading}>{translate('odiseo_site_assistant.summary')}</h2>
                <p className={styles.summary}>{handoff.summary}</p>

                <h2 className={styles.heading}>{translate('odiseo_site_assistant.contact')}</h2>
                {!handoff.contactName && !handoff.contactEmail && !handoff.contactPhone
                    ? <p className={styles.empty}>{translate('odiseo_site_assistant.no_contact')}</p>
                    : (
                        <dl className={styles.facts}>
                            <Fact label={translate('odiseo_site_assistant.contact_name')} value={handoff.contactName} />
                            <Fact
                                href={handoff.contactEmail ? `mailto:${handoff.contactEmail}?subject=${encodeURIComponent(handoff.reference)}` : undefined}
                                label={translate('odiseo_site_assistant.contact_email')}
                                value={handoff.contactEmail}
                            />
                            <Fact
                                href={handoff.contactPhone ? `tel:${handoff.contactPhone.replace(/[^\d+]/g, '')}` : undefined}
                                label={translate('odiseo_site_assistant.contact_phone')}
                                value={handoff.contactPhone}
                            />
                        </dl>
                    )
                }

                {handoff.conversationId && router.route.options.conversationView &&
                    <p>
                        <button className={styles.link} onClick={this.openConversation} type="button">
                            {translate('odiseo_site_assistant.open_conversation')}
                        </button>
                    </p>
                }
            </div>
        );
    }
}

function Fact({label: name, value, href}: {href?: string, label: string, value: ?string}) {
    return (
        <div className={styles.fact}>
            <dt>{name}</dt>
            <dd>{value ? (href ? <a href={href}>{value}</a> : value) : '—'}</dd>
        </div>
    );
}

const ACTION_ICONS = {take: 'su-user', resolve: 'su-check', reopen: 'su-sync'};

export default withToolbar(Handoff, function() {
    const {router} = this.props;
    const items = [];

    if (router.route.options.canEdit && this.handoff) {
        for (const moveAction of this.handoff.actions) {
            items.push({
                disabled: !!this.saving,
                icon: ACTION_ICONS[moveAction],
                label: translate(`odiseo_site_assistant.handoff_action.${moveAction}`),
                loading: this.saving === moveAction,
                onClick: () => this.move(moveAction),
                type: 'button',
            });
        }
    }

    return {
        backButton: {
            onClick: () => router.restore(router.route.options.backView),
        },
        errors: this.error ? [this.error] : [],
        items,
        showSuccess: this.showSuccess,
    };
});
