<style>
    .ins-contract-modal {
        direction: rtl;
        padding: 0.25rem 0.5rem 0.5rem;
    }

    .ins-contract-modal__intro {
        margin-bottom: 1rem;
        color: rgb(75 85 99);
        font-size: 0.925rem;
        line-height: 1.6;
    }

    .ins-contract-modal__summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .ins-contract-modal__summary-card {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: rgb(249 250 251);
        padding: 0.875rem 1rem;
        text-align: center;
    }

    .ins-contract-modal__summary-label {
        display: block;
        margin-bottom: 0.35rem;
        color: rgb(107 114 128);
        font-size: 0.85rem;
    }

    .ins-contract-modal__summary-value {
        display: block;
        color: rgb(17 24 39);
        font-size: 1.125rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .ins-contract-modal__table-wrap {
        overflow: auto;
        max-height: min(60vh, 28rem);
        border: 1px solid rgb(209 213 219);
        border-radius: 0.75rem;
    }

    .ins-contract-modal__table {
        width: 100%;
        min-width: 36rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.95rem;
    }

    .ins-contract-modal__table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #9dc1d3;
        color: rgb(17 24 39);
        padding: 0.85rem 1rem;
        text-align: center;
        font-weight: 700;
        border-bottom: 1px solid rgb(156 163 175);
        white-space: nowrap;
    }

    .ins-contract-modal__table tbody td {
        padding: 0.8rem 1rem;
        text-align: center;
        border-bottom: 1px solid rgb(229 231 235);
        vertical-align: middle;
        line-height: 1.5;
    }

    .ins-contract-modal__table tbody tr:nth-child(even) {
        background: rgb(249 250 251);
    }

    .ins-contract-modal__table tbody tr:hover {
        background: rgb(239 246 255);
    }

    .ins-contract-modal__amount {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
    }

    .ins-contract-modal__empty {
        padding: 2rem 1rem;
        text-align: center;
        color: rgb(107 114 128);
        font-size: 0.95rem;
    }

    .dark .ins-contract-modal__intro {
        color: rgb(156 163 175);
    }

    .dark .ins-contract-modal__summary-card {
        border-color: rgb(55 65 81);
        background: rgb(31 41 55);
    }

    .dark .ins-contract-modal__summary-label {
        color: rgb(156 163 175);
    }

    .dark .ins-contract-modal__summary-value {
        color: rgb(243 244 246);
    }

    .dark .ins-contract-modal__table-wrap {
        border-color: rgb(75 85 99);
    }

    .dark .ins-contract-modal__table tbody td {
        border-bottom-color: rgb(55 65 81);
    }

    .dark .ins-contract-modal__table tbody tr:nth-child(even) {
        background: rgb(31 41 55);
    }

    .dark .ins-contract-modal__table tbody tr:hover {
        background: rgb(30 58 95);
    }
</style>
