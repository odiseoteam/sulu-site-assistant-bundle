// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Loader, SingleSelect} from 'sulu-admin-bundle/components';
import {Requester, resourceRouteRegistry} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import type {ViewProps} from 'sulu-admin-bundle/containers';
import {formatPercent, formatUsd, label} from '../../utils/format';
import styles from './statistics.scss';

const ALL = '__all';

// The conversations that started in a period, and what came of them: how many went on, the cases
// handed to the team, the contact links opened and what the model cost.
@observer
class Statistics extends React.Component<ViewProps> {
    @observable preset: string = '30d';
    @observable webspace: string = ALL;
    @observable statistics: ?Object = undefined;

    componentDidMount() {
        this.load();
    }

    @action load() {
        this.statistics = undefined;
        const parameters = {period: this.preset, ...(this.webspace === ALL ? {} : {webspace: this.webspace})};
        Requester.get(resourceRouteRegistry.getUrl('detail', 'site_assistant_statistics', parameters))
            .then(action((statistics) => {
                this.statistics = statistics;
            }));
    }

    @action choosePreset = (preset: string) => {
        this.preset = preset;
        this.load();
    };

    @action chooseWebspace = (webspace: string) => {
        this.webspace = webspace;
        this.load();
    };

    render() {
        const {statistics} = this;

        return (
            <div className={styles.statistics}>
                <h1 className={styles.title}>{translate('odiseo_site_assistant.statistics')}</h1>

                <div className={styles.filters}>
                    <div className={styles.periods} role="group" aria-label={translate('odiseo_site_assistant.period')}>
                        {(statistics?.period.presets || ['7d', '30d', 'this_month', 'last_month']).map((preset) => (
                            <button
                                className={preset === this.preset ? styles.periodActive : styles.period}
                                key={preset}
                                onClick={() => this.choosePreset(preset)}
                                type="button"
                            >
                                {translate(`odiseo_site_assistant.period_preset.${preset}`)}
                            </button>
                        ))}
                    </div>

                    {statistics && statistics.webspaces.length > 1 &&
                        <div className={styles.webspace}>
                            <SingleSelect onChange={this.chooseWebspace} value={this.webspace}>
                                <SingleSelect.Option value={ALL}>{translate('odiseo_site_assistant.all_webspaces')}</SingleSelect.Option>
                                {statistics.webspaces.map((webspace) => (
                                    <SingleSelect.Option key={webspace.key} value={webspace.key}>{webspace.name}</SingleSelect.Option>
                                ))}
                            </SingleSelect>
                        </div>
                    }
                </div>

                {!statistics
                    ? <Loader />
                    : (
                        <>
                            <p className={styles.range}>{statistics.period.from} — {statistics.period.to}</p>
                            {statistics.conversations === 0
                                ? <p className={styles.empty}>{translate('odiseo_site_assistant.no_data')}</p>
                                : this.renderStatistics(statistics)
                            }
                        </>
                    )
                }
            </div>
        );
    }

    renderStatistics(statistics: Object) {
        const cards = [
            ['odiseo_site_assistant.conversations', statistics.conversations, null],
            ['odiseo_site_assistant.engaged', statistics.engaged, formatPercent(statistics.engagedRate)],
            ['odiseo_site_assistant.handoffs', statistics.handoffs, formatPercent(statistics.handoffRate)],
            ['odiseo_site_assistant.contacts_opened', statistics.contactOpened, formatPercent(statistics.contactOpenedRate)],
            ['odiseo_site_assistant.spend', formatUsd(statistics.spendUsd), null],
            ['odiseo_site_assistant.cost_per_conversation', formatUsd(statistics.spendPerConversation, 3), null],
        ];
        const topics = Object.entries(statistics.handoffsByTopic);

        return (
            <>
                <div className={styles.cards}>
                    {cards.map(([name, value, rate]) => (
                        <div className={styles.card} key={name}>
                            <span className={styles.value}>{value}</span>
                            <span className={styles.label}>{translate(name)}</span>
                            {rate && <span className={styles.rate}>{translate('odiseo_site_assistant.of_conversations', {rate})}</span>}
                        </div>
                    ))}
                </div>

                {topics.length > 0 &&
                    <>
                        <h2 className={styles.heading}>{translate('odiseo_site_assistant.handoffs_by_topic')}</h2>
                        <table className={styles.table}>
                            <tbody>
                                {topics.map(([topic, count]) => (
                                    <tr key={topic}>
                                        <td>{label('handoff_topic', topic)}</td>
                                        <td>{String(count)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </>
                }

                {statistics.topPages.length > 0 &&
                    <>
                        <h2 className={styles.heading}>{translate('odiseo_site_assistant.top_pages')}</h2>
                        <table className={styles.table}>
                            <tbody>
                                {statistics.topPages.map((row) => (
                                    <tr key={row.page}>
                                        <td>{row.page}</td>
                                        <td>{row.conversations}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </>
                }
            </>
        );
    }
}

export default withToolbar(Statistics, function() {
    return {};
});
