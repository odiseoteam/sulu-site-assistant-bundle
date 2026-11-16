// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Dialog, Loader} from 'sulu-admin-bundle/components';
import {Requester, resourceRouteRegistry} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import type {ViewProps} from 'sulu-admin-bundle/containers';
import {formatDate, formatUsd, label} from '../../utils/format';
import styles from './conversation.scss';

const RESOURCE_KEY = 'site_assistant_conversations';

// One conversation as the visitor saw it: their messages, the assistant's replies and what it
// looked up or showed on the way, with the cases it opened and what it remembers.
@observer
class Conversation extends React.Component<ViewProps> {
    @observable conversation: ?Object = undefined;
    @observable failed: boolean = false;
    @observable deleting: boolean = false;
    @observable confirmDelete: boolean = false;

    componentDidMount() {
        Requester.get(this.url())
            .then(action((conversation) => {
                this.conversation = conversation;
            }))
            .catch(action(() => {
                this.failed = true;
            }));
    }

    url(): string {
        return resourceRouteRegistry.getUrl('detail', RESOURCE_KEY, {id: this.props.router.attributes.id});
    }

    @action requestDelete = () => {
        this.confirmDelete = true;
    };

    @action cancelDelete = () => {
        this.confirmDelete = false;
    };

    @action delete = () => {
        const {router} = this.props;
        this.deleting = true;
        Requester.delete(this.url()).then(() => router.restore(router.route.options.backView));
    };

    openHandoff = (id: number) => {
        const {router} = this.props;
        router.navigate(router.route.options.handoffView, {id});
    };

    render() {
        const {conversation, failed} = this;

        if (failed) {
            return <p className={styles.empty}>{translate('sulu_admin.unexpected_error')}</p>;
        }
        if (!conversation) {
            return <Loader />;
        }

        return (
            <div className={styles.conversation}>
                <h1 className={styles.title}>
                    {conversation.firstMessage || translate('odiseo_site_assistant.conversation')}
                </h1>

                <dl className={styles.facts}>
                    <Fact label={translate('odiseo_site_assistant.started')} value={formatDate(conversation.createdAt)} />
                    <Fact label={translate('odiseo_site_assistant.last_activity')} value={formatDate(conversation.updatedAt)} />
                    <Fact label={translate('odiseo_site_assistant.webspace')} value={conversation.webspaceKey} />
                    <Fact label={translate('odiseo_site_assistant.language')} value={conversation.locale} />
                    <Fact label={translate('odiseo_site_assistant.last_page')} value={conversation.lastPage} />
                    <Fact label={translate('odiseo_site_assistant.messages')} value={String(conversation.turnCount)} />
                    <Fact label={translate('odiseo_site_assistant.contact_opened')} value={formatDate(conversation.contactOpenedAt)} />
                    <Fact label={translate('odiseo_site_assistant.models')} value={conversation.models.join(', ')} />
                    <Fact label={translate('odiseo_site_assistant.spend')} value={formatUsd(conversation.spend.usd, 4)} />
                    <Fact
                        label={translate('odiseo_site_assistant.tokens')}
                        value={`${conversation.spend.inputTokens} / ${conversation.spend.outputTokens}`}
                    />
                </dl>

                {conversation.handoffs.length > 0 &&
                    <>
                        <h2 className={styles.heading}>{translate('odiseo_site_assistant.handoffs')}</h2>
                        <ul className={styles.handoffs}>
                            {conversation.handoffs.map((handoff) => (
                                <li key={handoff.id}>
                                    <button className={styles.link} onClick={() => this.openHandoff(handoff.id)} type="button">
                                        {handoff.reference}
                                    </button>
                                    {' · '}{label('handoff_topic', handoff.topic)}
                                    {' · '}{label('handoff_state', handoff.state)}
                                    {handoff.path && <>{' · '}{label('handoff_path', handoff.path)}</>}
                                    {' · '}{formatDate(handoff.createdAt)}
                                </li>
                            ))}
                        </ul>
                    </>
                }

                <h2 className={styles.heading}>{translate('odiseo_site_assistant.transcript')}</h2>
                <ol className={styles.transcript}>
                    {conversation.transcript.map((entry, index) => (
                        <li className={entry.role === 'user' ? styles.visitor : styles.assistant} key={index}>
                            <span className={styles.who}>
                                {translate(entry.role === 'user'
                                    ? 'odiseo_site_assistant.visitor'
                                    : 'odiseo_site_assistant.assistant')}
                                <time>{formatDate(entry.createdAt)}</time>
                            </span>
                            {entry.text && <p className={styles.text}>{entry.text}</p>}
                            {entry.tools.map((tool, toolIndex) => (
                                <div className={styles.tool} key={toolIndex}>
                                    <strong>{label('tool', tool.name)}</strong>
                                    {' '}
                                    {describeTool(tool)}
                                    {tool.pages.length > 0 &&
                                        <ul className={styles.pages}>
                                            {tool.pages.map((page) => (
                                                <li key={page.content_id}>
                                                    {page.title || page.content_id}
                                                    {page.note && <span className={styles.note}> — {page.note}</span>}
                                                </li>
                                            ))}
                                        </ul>
                                    }
                                </div>
                            ))}
                        </li>
                    ))}
                </ol>

                <h2 className={styles.heading}>{translate('odiseo_site_assistant.memory')}</h2>
                {conversation.memory.length === 0
                    ? <p className={styles.empty}>{translate('odiseo_site_assistant.no_memory')}</p>
                    : (
                        <dl className={styles.memory}>
                            {conversation.memory.map((fact) => (
                                <Fact key={fact.key} label={fact.key} value={fact.value} />
                            ))}
                        </dl>
                    )
                }

                <Dialog
                    cancelText={translate('sulu_admin.cancel')}
                    confirmLoading={this.deleting}
                    confirmText={translate('sulu_admin.ok')}
                    onCancel={this.cancelDelete}
                    onConfirm={this.delete}
                    open={this.confirmDelete}
                    title={translate('sulu_admin.delete_warning_title')}
                >
                    {translate('odiseo_site_assistant.delete_conversation_warning')}
                </Dialog>
            </div>
        );
    }
}

function Fact({label: name, value}: {label: string, value: ?string}) {
    return (
        <div className={styles.fact}>
            <dt>{name}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

// A search shows its query; a card or a case its title, topic or summary.
function describeTool(tool: Object): string {
    if (tool.query) {
        return `“${tool.query}”`;
    }
    if (tool.topic) {
        return `${label('handoff_topic', tool.topic)}${tool.summary ? ` — ${tool.summary}` : ''}`;
    }

    return tool.title || '';
}

export default withToolbar(Conversation, function() {
    const {router} = this.props;
    const items = [];

    if (router.route.options.canDelete) {
        items.push({
            disabled: !this.conversation,
            icon: 'su-trash-alt',
            label: translate('sulu_admin.delete'),
            onClick: this.requestDelete,
            type: 'button',
        });
    }

    return {
        backButton: {
            onClick: () => router.restore(router.route.options.backView),
        },
        items,
    };
});
