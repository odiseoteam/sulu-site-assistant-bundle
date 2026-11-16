// @flow
import React from 'react';
import {action, computed, observable, toJS} from 'mobx';
import {observer} from 'mobx-react';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Form, Input, Loader, SingleSelect, Toggler} from 'sulu-admin-bundle/components';
// Not re-exported by the components index.
import TextArea from 'sulu-admin-bundle/components/TextArea';
import {Requester, resourceRouteRegistry} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import type {ViewProps} from 'sulu-admin-bundle/containers';
import {formatDate} from '../../utils/format';
import styles from './editableItem.scss';

// A house rule or a knowledge document. What the assistant uses is the published version; a
// draft changes nothing until it is published. A configured item can go back to its version 1.
@observer
class EditableItem extends React.Component<ViewProps> {
    @observable item: ?Object = undefined;
    @observable data: Object = {};
    @observable code: string = '';
    @observable note: string = '';
    @observable saving: ?string = undefined;
    @observable errors: Array<string> = [];
    @observable compared: ?Object = undefined;
    @observable failed: boolean = false;
    showSuccess = observable.box(false);

    @computed get isNew(): boolean {
        return !this.props.router.attributes.id;
    }

    @computed get resourceKey(): string {
        return this.props.router.route.options.resourceKey;
    }

    componentDidMount() {
        // A new item starts from an empty one: the list endpoint has no schema, so the fields
        // come from the first existing item, or from the section's defaults.
        if (this.isNew) {
            this.loadSchema();

            return;
        }
        this.load();
    }

    detailUrl(id: string = this.props.router.attributes.id, parameters: Object = {}): string {
        return resourceRouteRegistry.getUrl('detail', this.resourceKey, {id, ...parameters});
    }

    @action load() {
        Requester.get(this.detailUrl())
            .then(action((item) => this.setItem(item)))
            .catch(action(() => {
                this.failed = true;
            }));
    }

    @action loadSchema() {
        Requester.get(resourceRouteRegistry.getUrl('list', this.resourceKey))
            .then((list) => {
                const first = (list._embedded[this.resourceKey] || [])[0];

                return first ? Requester.get(this.detailUrl(first.id)) : null;
            })
            .then(action((item) => {
                const fields = item ? item.fields : FALLBACK_FIELDS[this.resourceKey];
                this.setItem({fields, data: defaults(fields), history: [], draft: null, hasDefault: false, origin: 'admin'});
            }));
    }

    @action setItem(item: Object) {
        this.item = item;
        this.data = {...item.data};
        this.note = '';
        this.compared = undefined;
    }

    @action change(name: string, value: mixed) {
        this.data[name] = value;
    }

    @action save = (saveAction: string) => {
        const {router} = this.props;
        this.saving = saveAction;
        this.errors = [];
        const body = {data: toJS(this.data), note: this.note || null};

        const request = this.isNew
            ? Requester.post(`${resourceRouteRegistry.getUrl('list', this.resourceKey, {action: saveAction})}`, {...body, id: this.code})
            : Requester.put(this.detailUrl(undefined, {action: saveAction}), body);

        request
            .then(action((item) => {
                this.saving = undefined;
                this.showSuccess.set(true);
                if (this.isNew) {
                    router.navigate(router.route.name, {id: item.id});
                    return;
                }
                this.setItem(item);
            }))
            .catch(action((response) => this.fail(response)));
    };

    @action change_version = (versionAction: string, version?: number) => {
        this.saving = versionAction;
        this.errors = [];
        Requester.put(this.detailUrl(undefined, {action: versionAction}), {version, note: this.note || null})
            .then(action((item) => {
                this.saving = undefined;
                this.showSuccess.set(true);
                this.setItem(item);
            }))
            .catch(action((response) => this.fail(response)));
    };

    @action fail(response: Object) {
        this.saving = undefined;
        this.errors = [translate('sulu_admin.unexpected_error')];
        if (response && typeof response.json === 'function') {
            response.json().then(action((body) => {
                this.errors = body.problems || [body.message || translate('sulu_admin.unexpected_error')];
            }));
        }
    }

    @action compare = (number: number) => {
        const base = this.detailUrl().replace(/\.json(\?.*)?$/, '');
        Requester.get(`${base}/versions/${number}.json`).then(action((version) => {
            this.compared = version;
        }));
    };

    @action closeComparison = () => {
        this.compared = undefined;
    };

    render() {
        const {item, failed} = this;
        const canEdit = this.isNew ? this.props.router.route.options.canAdd : this.props.router.route.options.canEdit;

        if (failed) {
            return <p className={styles.empty}>{translate('sulu_admin.unexpected_error')}</p>;
        }
        if (!item) {
            return <Loader />;
        }

        return (
            <div className={styles.item}>
                <h1 className={styles.title}>
                    {this.isNew ? translate('odiseo_site_assistant.new_item') : item.title}
                </h1>

                {!this.isNew &&
                    <p className={styles.meta}>
                        <span className={styles.badge}>{translate(`odiseo_site_assistant.origin.${item.origin}`)}</span>
                        {' '}
                        {item.version > 0
                            ? translate('odiseo_site_assistant.version_in_use', {number: item.version})
                            : translate('odiseo_site_assistant.never_published')}
                        {item.draft &&
                            <span className={styles.draft}>
                                {' · '}
                                {translate('odiseo_site_assistant.draft_by', {
                                    author: item.draft.author || '—',
                                    date: formatDate(item.draft.at) || '',
                                })}
                            </span>
                        }
                    </p>
                }

                <Form>
                    {this.isNew &&
                        <Form.Field
                            description={translate('odiseo_site_assistant.code_info')}
                            label={translate('odiseo_site_assistant.code')}
                            required={true}
                        >
                            <Input disabled={!canEdit} onChange={action((value) => { this.code = value || ''; })} value={this.code} />
                        </Form.Field>
                    }
                    {item.fields.map((field) => (
                        <Form.Field
                            description={field.name === 'when' ? translate('odiseo_site_assistant.when_info') : undefined}
                            key={field.name}
                            label={translate(field.label)}
                            required={!!field.required}
                        >
                            {this.renderField(field, !canEdit)}
                        </Form.Field>
                    ))}
                    <Form.Field
                        description={translate('odiseo_site_assistant.note_info')}
                        label={translate('odiseo_site_assistant.note')}
                    >
                        <Input disabled={!canEdit} onChange={action((value) => { this.note = value || ''; })} value={this.note} />
                    </Form.Field>
                </Form>

                {this.compared && this.renderComparison(this.compared)}

                {!this.isNew && item.history.length > 0 && this.renderHistory(item, canEdit)}
            </div>
        );
    }

    renderField(field: Object, disabled: boolean) {
        const value = this.data[field.name];

        switch (field.type) {
            case 'select':
                return (
                    <SingleSelect disabled={disabled} onChange={(selected) => this.change(field.name, selected)} value={value}>
                        {Object.keys(field.options).map((option) => (
                            <SingleSelect.Option key={option} value={option}>
                                {translate(field.options[option])}
                            </SingleSelect.Option>
                        ))}
                    </SingleSelect>
                );
            case 'toggle':
                return (
                    <Toggler checked={!!value} disabled={disabled} onChange={(checked) => this.change(field.name, checked)}>
                        {translate('odiseo_site_assistant.enabled_label')}
                    </Toggler>
                );
            case 'textarea':
            case 'markdown':
                return (
                    <TextArea
                        disabled={disabled}
                        maxCharacters={field.max}
                        onChange={(text) => this.change(field.name, text || '')}
                        rows={field.type === 'markdown' ? 16 : 5}
                        value={value || ''}
                    />
                );
            default:
                return (
                    <Input
                        disabled={disabled}
                        maxCharacters={field.max}
                        onChange={(text) => this.change(field.name, text || '')}
                        value={value || ''}
                    />
                );
        }
    }

    renderComparison(version: Object) {
        const fields = this.item ? this.item.fields : [];

        return (
            <section className={styles.comparison}>
                <header className={styles.comparisonHead}>
                    <h2 className={styles.heading}>
                        {translate('odiseo_site_assistant.comparing', {number: version.number})}
                    </h2>
                    <button className={styles.link} onClick={this.closeComparison} type="button">
                        {translate('sulu_admin.close')}
                    </button>
                </header>
                <p className={styles.hint}>{translate('odiseo_site_assistant.comparison_hint')}</p>
                {fields.map((field) => (
                    <div className={styles.diff} key={field.name}>
                        <strong>{translate(field.label)}</strong>
                        {/* Escaped on the server: only <del> and <ins> are markup. */}
                        <div className={styles.diffText} dangerouslySetInnerHTML={{__html: version.diff[field.name] || ''}} />
                    </div>
                ))}
            </section>
        );
    }

    renderHistory(item: Object, canEdit: boolean) {
        return (
            <section>
                <h2 className={styles.heading}>{translate('odiseo_site_assistant.history')}</h2>
                <table className={styles.history}>
                    <thead>
                        <tr>
                            <th>{translate('odiseo_site_assistant.version')}</th>
                            <th>{translate('odiseo_site_assistant.changed_by')}</th>
                            <th>{translate('odiseo_site_assistant.last_change')}</th>
                            <th>{translate('odiseo_site_assistant.note')}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {item.history.map((version) => (
                            <tr className={version.number === item.version ? styles.current : undefined} key={version.number}>
                                <td>
                                    {version.number}
                                    {version.isDefault && ` · ${translate('odiseo_site_assistant.configured')}`}
                                </td>
                                <td>{version.author || '—'}</td>
                                <td>{formatDate(version.at) || translate('odiseo_site_assistant.configured')}</td>
                                <td>{version.note || ''}</td>
                                <td className={styles.actions}>
                                    <button className={styles.link} onClick={() => this.compare(version.number)} type="button">
                                        {translate('odiseo_site_assistant.compare')}
                                    </button>
                                    {canEdit && version.number !== item.version &&
                                        <button
                                            className={styles.link}
                                            onClick={() => this.change_version('restore', version.number)}
                                            type="button"
                                        >
                                            {translate('odiseo_site_assistant.copy_to_draft')}
                                        </button>
                                    }
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        );
    }
}

// The fields of a section with no item yet; the server's schema replaces them once one exists.
const FALLBACK_FIELDS = {
    site_assistant_rules: [
        {name: 'title', type: 'text', label: 'odiseo_site_assistant.field.title', required: true},
        {name: 'category', type: 'select', label: 'odiseo_site_assistant.field.category', options: {
            style: 'odiseo_site_assistant.rule_category.style',
            sales: 'odiseo_site_assistant.rule_category.sales',
            clarification: 'odiseo_site_assistant.rule_category.clarification',
            other: 'odiseo_site_assistant.rule_category.other',
        }},
        {name: 'text', type: 'textarea', label: 'odiseo_site_assistant.field.text', required: true},
        {name: 'enabled', type: 'toggle', label: 'odiseo_site_assistant.field.enabled'},
    ],
    site_assistant_documents: [
        {name: 'title', type: 'text', label: 'odiseo_site_assistant.field.title', required: true},
        {name: 'kind', type: 'select', label: 'odiseo_site_assistant.field.kind', options: {
            policy: 'odiseo_site_assistant.document_kind.policy',
            guide: 'odiseo_site_assistant.document_kind.guide',
            snippet: 'odiseo_site_assistant.document_kind.snippet',
        }},
        {name: 'when', type: 'text', label: 'odiseo_site_assistant.field.when'},
        {name: 'body', type: 'markdown', label: 'odiseo_site_assistant.field.body', required: true},
        {name: 'enabled', type: 'toggle', label: 'odiseo_site_assistant.field.enabled'},
    ],
};

function defaults(fields: Array<Object>): Object {
    const data = {};
    for (const field of fields) {
        data[field.name] = field.type === 'toggle' ? true : field.type === 'select' ? Object.keys(field.options)[0] : '';
    }

    return data;
}

export default withToolbar(EditableItem, function() {
    const {router} = this.props;
    const options = router.route.options;
    const canEdit = this.isNew ? options.canAdd : options.canEdit;
    const items = [];

    if (canEdit && this.item) {
        items.push(
            {
                disabled: !!this.saving,
                icon: 'su-save',
                label: translate('odiseo_site_assistant.save_draft'),
                loading: this.saving === 'draft',
                onClick: () => this.save('draft'),
                type: 'button',
            },
            {
                disabled: !!this.saving,
                icon: 'su-check',
                label: translate('odiseo_site_assistant.publish'),
                loading: this.saving === 'publish',
                onClick: () => this.save('publish'),
                type: 'button',
            },
        );
        if (!this.isNew && this.item.draft) {
            items.push({
                disabled: !!this.saving,
                icon: 'su-trash-alt',
                label: translate('odiseo_site_assistant.discard_draft'),
                loading: this.saving === 'discard',
                onClick: () => this.change_version('discard'),
                type: 'button',
            });
        }
        if (!this.isNew && this.item.hasDefault && this.item.origin === 'file_edited') {
            items.push({
                disabled: !!this.saving,
                icon: 'su-sync',
                label: translate('odiseo_site_assistant.reset'),
                loading: this.saving === 'reset',
                onClick: () => this.change_version('reset'),
                type: 'button',
            });
        }
    }

    return {
        backButton: {
            onClick: () => router.restore(options.backView),
        },
        errors: this.errors,
        items,
        showSuccess: this.showSuccess,
    };
});
