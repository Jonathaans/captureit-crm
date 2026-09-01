<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectInternalCommunicationUi
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response =
            $next(
                $request
            );

        if (
            ! $request->is(
                'admin*'
            )
            || ! auth()
                ->guard('user')
                ->check()
            || $response->getStatusCode() >= 300
        ) {
            return $response;
        }

        $contentType =
            strtolower(
                (string) $response
                    ->headers
                    ->get(
                        'Content-Type',
                        ''
                    )
            );

        if (
            ! str_contains(
                $contentType,
                'text/html'
            )
        ) {
            return $response;
        }

        $content =
            $response->getContent();

        if (
            ! is_string(
                $content
            )
            || $content === ''
            || ! str_contains(
                strtolower(
                    $content
                ),
                '</body>'
            )
            || str_contains(
                $content,
                'CRM_INTERNAL_COMMUNICATION_WIDGET'
            )
        ) {
            return $response;
        }

        $widget =
            view(
                'admin::internal-communication.widget'
            )->render();

        $position =
            strripos(
                $content,
                '</body>'
            );

        if ($position === false) {
            return $response;
        }

        $content =
            substr_replace(
                $content,
                $widget,
                $position,
                0
            );

        $response->setContent(
            $content
        );

        return $response;
    }
}
