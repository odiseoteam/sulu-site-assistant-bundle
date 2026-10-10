// @flow
import React from 'react';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Loader} from 'sulu-admin-bundle/components';
import {Requester, resourceRouteRegistry} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import type {ViewProps} from 'sulu-admin-bundle/containers';
import styles from './statistics.scss';

type State = {|
    days: number,
    statistics: ?Object,
|};

const PERIODS = [7, 30, 90];

// The assistant's funnel for a period: conversations, the ones that went on, handoffs and cost.
class Statistics extends React.Component<ViewProps, State> {
    state = {days: 30, statistics: undefined};

    componentDidMount() {
        this.load(this.state.days);
    }

    load(days: number) {
        this.setState({days, statistics: undefined});
        const url = resourceRouteRegistry.getUrl('detail', 'site_assistant_statistics', {days});
        Requester.get(url).then((statistics) => this.setState({statistics}));
    }

    render() {
        const {days, statistics} = this.state;

        return (
            <div className={styles.statistics}>
                <h1 className={styles.title}>{translate('odiseo_site_assistant.statistics')}</h1>
                <div className={styles.periods} role="group" aria-label={translate('odiseo_site_assistant.period')}>
                    {PERIODS.map((period) => (
                        <button
                            className={period === days ? styles.periodActive : styles.period}
                            key={period}
                            onClick={() => this.load(period)}
                            type="button"
                        >
                            {translate('odiseo_site_assistant.last_days', {days: period})}
                        </button>
                    ))}
                </div>

                {!statistics
                    ? <Loader />
                    : statistics.conversations === 0
                        ? <p className={styles.empty}>{translate('odiseo_site_assistant.no_data')}</p>
                        : this.renderStatistics(statistics)
                }
            </div>
        );
    }

    renderStatistics(statistics: Object) {
        const cards = [
            ['odiseo_site_assistant.conversations', statistics.conversations],
            ['odiseo_site_assistant.engaged', statistics.engaged],
            ['odiseo_site_assistant.handoffs_shown', statistics.handoffsShown],
            ['odiseo_site_assistant.handoffs_clicked', statistics.handoffsClicked],
            ['odiseo_site_assistant.handoff_rate', `${Math.round(statistics.handoffRate * 100)}%`],
            ['odiseo_site_assistant.spend', `US$ ${statistics.spendUsd.toFixed(2)}`],
            ['odiseo_site_assistant.cost_per_conversation', `US$ ${statistics.costPerConversation.toFixed(3)}`],
        ];

        return (
            <>
                <div className={styles.cards}>
                    {cards.map(([label, value]) => (
                        <div className={styles.card} key={label}>
                            <span className={styles.value}>{value}</span>
                            <span className={styles.label}>{translate(label)}</span>
                        </div>
                    ))}
                </div>

                {statistics.topPages.length > 0 &&
                    <>
                        <h2 className={styles.heading}>{translate('odiseo_site_assistant.top_pages')}</h2>
                        <table className={styles.pages}>
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
