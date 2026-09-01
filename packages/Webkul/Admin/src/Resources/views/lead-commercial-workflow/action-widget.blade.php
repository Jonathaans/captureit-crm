<!-- CRM_LEAD_COMMERCIAL_ACTION_WIDGET -->
<style>
    #crm-lead-commercial-action {
        position: fixed;
        right: 22px;
        top: 88px;
        z-index: 8900;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid #fde68a;
        border-radius: 12px;
        background: #fffbeb;
        box-shadow: 0 10px 28px rgba(0,0,0,.12);
        color: #111827;
        font-family: inherit;
    }

    #crm-lead-commercial-action .crm-stage {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        color: #92400e;
    }

    #crm-lead-commercial-action .crm-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 11px;
        border-radius: 8px;
        background: #f59e0b;
        color: #ffffff;
        text-decoration: none;
        font-size: 12px;
        font-weight: 800;
    }

    @media (max-width: 720px) {
        #crm-lead-commercial-action {
            right: 12px;
            top: 72px;
        }

        #crm-lead-commercial-action .crm-stage {
            display: none;
        }
    }
</style>

<div id="crm-lead-commercial-action">
    <span class="crm-stage">
        Quotation Stage
    </span>

    <a
        href="{{ $actionUrl }}"
        class="crm-action"
    >
        {{ $quoteId ? 'Open Quotation' : 'Generate Quotation' }}
    </a>
</div>
