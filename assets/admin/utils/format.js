// @flow
import {translate} from 'sulu-admin-bundle/utils';

export function formatDate(value: ?string): ?string {
    return value ? new Date(value).toLocaleString() : null;
}

export function formatUsd(value: number, digits: number = 2): string {
    return `US$ ${value.toFixed(digits)}`;
}

export function formatPercent(value: number): string {
    return `${Math.round(value * 100)}%`;
}

// `odiseo_site_assistant.<group>.<key>` when it is translated, else the key made readable.
export function label(group: string, key: ?string): string {
    if (!key) {
        return '—';
    }
    const id = `odiseo_site_assistant.${group}.${key}`;
    const text = translate(id);

    return text === id ? key.replace(/[_-]/g, ' ') : text;
}
