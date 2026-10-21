import React from 'react';
import { createRoot } from 'react-dom/client';
import { App } from '@/react/spa/App';
import type { SpaBootData } from '@/react/spa/session';

function readBootData(): SpaBootData {
    const el = document.getElementById('app');
    const raw = el?.dataset.props;

    if (!raw) {
        return {};
    }

    try {
        return JSON.parse(raw) as SpaBootData;
    } catch (error) {
        console.error('Gagal membaca data boot SPA:', error);
        return {};
    }
}

const root = document.getElementById('app');

if (root) {
    createRoot(root).render(
        <React.StrictMode>
            <App boot={readBootData()} />
        </React.StrictMode>
    );
}