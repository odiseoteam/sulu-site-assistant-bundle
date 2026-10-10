// @flow
import React from 'react';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Loader} from 'sulu-admin-bundle/components';
import {Requester, resourceRouteRegistry} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import type {ViewProps} from 'sulu-admin-bundle/containers';
import styles from './conversation.scss';

type State = {|
    conversation: ?Object,
    failed: boolean,
|};

const RESOURCE_KEY = 'site_assistant_conversations';

// One conversation as the visitor saw it: their messages, the assistant's replies and what it
// looked up or showed on the way. Tool results stay out; the model's inputs are enough to follow.
class Conversation extends React.Component<ViewProps, State> {
    state = {conversation: undefined, failed: false};

    componentDidMount() {
        const {router} = this.props;
        const url = resourceRouteRegistry.getUrl('detail', RESOURCE_KEY, {id: router.attributes.id});

        Requester.get(url)
            .then((conversation) => this.setState({conversation}))
            .catch(() => this.setState({failed: true}));
    }

    render() {
        const {conversation, failed} = this.state;

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
                    <Fact label={translate('odiseo_site_assistant.language')} value={conversation.locale} />
                    <Fact label={translate('odiseo_site_assistant.last_page')} value={conversation.lastPage} />
                    <Fact label={translate('odiseo_site_assistant.handoff_shown')} value={formatDate(conversation.handoffShownAt)} />
                    <Fact label={translate('odiseo_site_assistant.handoff_clicked')} value={formatDate(conversation.handoffClickedAt)} />
                    <Fact label={translate('odiseo_site_assistant.spend')} value={`US$ ${conversation.spend.usd.toFixed(4)}`} />
                    <Fact
                        label={translate('odiseo_site_assistant.tokens')}
                        value={`${conversation.spend.inputTokens} / ${conversation.spend.outputTokens}`}
                    />
                </dl>

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
                                <p className={styles.tool} key={toolIndex}>
                                    <strong>{translate(`odiseo_site_assistant.tool.${tool.name}`)}</strong>
                                    {' '}
                                    {describeTool(tool)}
                                </p>
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
            </div>
        );
    }
}

function Fact({label, value}: {label: string, value: ?string}) {
    return (
        <div className={styles.fact}>
            <dt>{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

function formatDate(value: ?string): ?string {
    return value ? new Date(value).toLocaleString() : null;
}

// A search shows its query; a card or handoff its title or summary.
function describeTool(tool: Object): string {
    const input = tool.input || {};
    if (typeof input.query === 'string') {
        return `“${input.query}”`;
    }
    if (typeof input.content_id === 'string') {
        return input.content_id;
    }

    return input.summary || input.title || '';
}

export default withToolbar(Conversation, function() {
    const {router} = this.props;

    return {
        backButton: {
            onClick: () => router.restore(router.route.options.backView),
        },
    };
});
