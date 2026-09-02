<?php

return [
    /*
     * Only these CRM role names may open Operational Dashboard > Chat Audit.
     *
     * Override in .env when needed:
     * INTERNAL_CHAT_AUDIT_ROLES="Administrator,Super Admin,Management,Director"
     */
    'role_names' =>
        array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        (string) env(
                            'INTERNAL_CHAT_AUDIT_ROLES',
                            'Administrator,Super Admin,Management,Director'
                        )
                    )
                )
            )
        ),
];
